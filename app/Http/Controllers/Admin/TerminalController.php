<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminSettingsService;
use App\Services\Admin\AuditLogger;
use App\Services\Admin\TerminalGate;
use App\Services\Admin\TerminalService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Staff-facing terminal: run shell/artisan commands, inspect project files and
 * edit code. Requires the dedicated `terminal.use` permission; every command,
 * read and write is audited. File writes are refused unless the caller confirms
 * the target file first and presents the hash of the content it opened.
 */
class TerminalController extends Controller
{
    protected const PRESETS = [
        'shell' => [
            ['label' => 'Who am I', 'command' => 'whoami && pwd'],
            ['label' => 'PHP version', 'command' => 'php -v'],
            ['label' => 'Git status', 'command' => 'git status --short'],
            ['label' => 'Git log', 'command' => 'git log --oneline -n 15'],
            ['label' => 'Disk usage', 'command' => 'du -sh . 2>/dev/null'],
            ['label' => 'Listening ports', 'command' => 'ss -tlnp 2>/dev/null | head -n 30'],
        ],
        'artisan' => [
            ['label' => 'Route list', 'command' => 'route:list --except-vendor'],
            ['label' => 'About', 'command' => 'about'],
            ['label' => 'Migrate status', 'command' => 'migrate:status'],
            ['label' => 'Config cache', 'command' => 'config:cache'],
            ['label' => 'Queue status', 'command' => 'queue:failed'],
            ['label' => 'List commands', 'command' => 'list'],
        ],
        'pentest' => [
            ['label' => 'Nmap scan', 'command' => 'nmap -sV -T4 127.0.0.1'],
            ['label' => 'DNS/hosts', 'command' => 'cat /etc/hosts'],
            ['label' => 'Env exposure check', 'command' => 'grep -rn "APP_DEBUG" .env config/app.php 2>/dev/null'],
            ['label' => 'SQL tables', 'command' => 'php artisan tinker --execute="echo implode(\',\', \Illuminate\Support\Facades\DB::select(\'SHOW TABLES\')[0]);" 2>/dev/null | head -c 2000'],
            ['label' => 'Curl headers', 'command' => 'curl -sS -I http://127.0.0.1 2>/dev/null | head -n 20'],
            ['label' => 'robots.txt', 'command' => 'curl -sS http://127.0.0.1/robots.txt 2>/dev/null | head -n 40'],
        ],
    ];

    public function __construct(
        protected TerminalService $terminal,
        protected AdminSettingsService $settings,
        protected TerminalGate $gate,
    ) {}

    public function index()
    {
        $root = $this->terminal->root();

        return view('admin.terminal.index', [
            'root' => $root,
            'presets' => self::PRESETS,
            'initialPath' => $this->terminal->tree(null),
            'serverPhp' => PHP_VERSION,
            'configured' => $this->gate->configured(),
            'unlocked' => $this->gate->unlocked(),
            'remainingSeconds' => $this->gate->unlocked() ? $this->gate->remainingSeconds() : 0,
            'canConfigurePin' => (bool) auth()->user()?->can('admin_settings.manage'),
        ]);
    }

    public function unlock(Request $request)
    {
        $data = $request->validate([
            'pin' => ['required', 'string', 'max:32'],
        ]);

        if (! $this->gate->configured()) {
            return response()->json([
                'ok' => false,
                'message' => 'No terminal PIN has been configured. An administrator must set one first.',
            ], 422);
        }

        if (! $this->gate->verify($data['pin'])) {
            AuditLogger::record('terminal.unlock.failed', null, [], [
                'reason' => 'invalid_pin',
            ], 'incorrect terminal PIN');

            return response()->json(['ok' => false, 'message' => 'Incorrect PIN.'], 422);
        }

        $this->gate->unlock();

        AuditLogger::record('terminal.unlock', null, [], [
            'expires_in' => TerminalGate::TTL_MINUTES * 60,
        ], 'terminal unlocked for this session');

        return response()->json([
            'ok' => true,
            'remaining_seconds' => $this->gate->remainingSeconds(),
        ]);
    }

    public function lock()
    {
        $this->gate->lock();

        AuditLogger::record('terminal.lock', null, [], [], 'terminal locked');

        return response()->json(['ok' => true]);
    }

    public function setPin(Request $request)
    {
        $data = $request->validate([
            'current_pin' => [$this->gate->configured() ? 'required' : 'nullable', 'string', 'max:32'],
            'pin' => ['required', 'digits_between:6,12', 'confirmed'],
        ], [], ['pin' => 'terminal PIN', 'current_pin' => 'current PIN']);

        if ($this->gate->configured() && ! $this->gate->verify($data['current_pin'] ?? '')) {
            AuditLogger::record('terminal.pin.set.failed', null, [], [
                'reason' => 'invalid_current_pin',
            ], 'incorrect current PIN when changing terminal PIN');

            throw ValidationException::withMessages([
                'current_pin' => 'The current PIN is incorrect.',
            ]);
        }

        $this->gate->setPin($data['pin']);
        $this->gate->lock();

        AuditLogger::record('terminal.pin.set', null, [], [
            'length' => strlen($data['pin']),
        ], 'terminal PIN configured');

        return back()->with('success', 'Terminal PIN saved. Staff must enter it to unlock the terminal.');
    }

    public function clearPin(Request $request)
    {
        $data = $request->validate([
            'current_pin' => [$this->gate->configured() ? 'required' : 'nullable', 'string', 'max:32'],
        ], [], ['current_pin' => 'current PIN']);

        if ($this->gate->configured() && ! $this->gate->verify($data['current_pin'] ?? '')) {
            AuditLogger::record('terminal.pin.clear.failed', null, [], [
                'reason' => 'invalid_current_pin',
            ], 'incorrect current PIN when clearing terminal PIN');

            throw ValidationException::withMessages([
                'current_pin' => 'The current PIN is incorrect.',
            ]);
        }

        $this->gate->clearPin();
        $this->gate->lock();

        AuditLogger::record('terminal.pin.cleared', null, [], [], 'terminal PIN removed');

        return back()->with('success', 'Terminal PIN removed. The terminal is now inaccessible until a new PIN is set.');
    }

    public function exec(Request $request)
    {
        $data = $request->validate([
            'mode' => ['required', 'in:shell,artisan'],
            'command' => ['required', 'string', 'max:4000'],
            'timeout' => ['nullable', 'integer', 'min:1', 'max:120'],
        ]);

        $timeout = (int) ($data['timeout'] ?? 30);

        try {
            $result = $data['mode'] === 'artisan'
                ? $this->terminal->artisan($data['command'], $timeout)
                : $this->terminal->run($data['command'], $timeout);
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        AuditLogger::record(
            'terminal.'.$data['mode'],
            null,
            [],
            [
                'command' => $data['command'],
                'exit_code' => $result['exit_code'],
                'timed_out' => $result['timed_out'],
                'duration_ms' => $result['duration_ms'],
            ],
        );

        return response()->json(['ok' => true] + $result);
    }

    public function read(Request $request)
    {
        $data = $request->validate([
            'path' => ['required', 'string', 'max:500'],
        ]);

        try {
            $file = $this->terminal->read($data['path']);
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        AuditLogger::record('terminal.read', null, [], [
            'path' => $file['path'],
            'bytes' => $file['size'],
            'sha256' => $file['sha256'],
        ]);

        return response()->json(['ok' => true] + $file);
    }

    public function write(Request $request)
    {
        $data = $request->validate([
            'path' => ['required', 'string', 'max:500'],
            'content' => ['required', 'string', 'max:2097152'],
            'confirm_path' => ['required', 'string', 'max:500'],
            'expected_sha256' => ['nullable', 'string', 'max:64'],
        ]);

        try {
            $result = $this->terminal->write(
                $data['path'],
                $data['content'],
                $data['confirm_path'],
                $data['expected_sha256'] ?? null,
            );
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        AuditLogger::record(
            'terminal.write',
            null,
            ['path' => $result['path'], 'bytes' => $result['before_bytes'], 'sha256' => $result['before_sha256']],
            ['path' => $result['path'], 'bytes' => $result['bytes'], 'sha256' => $result['sha256']],
            $result['created'] ? 'created new file' : 'edited file',
        );

        return response()->json(['ok' => true] + $result);
    }

    public function tree(Request $request)
    {
        $data = $request->validate([
            'path' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $listing = $this->terminal->tree($data['path'] ?? null);
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true] + $listing);
    }
}
