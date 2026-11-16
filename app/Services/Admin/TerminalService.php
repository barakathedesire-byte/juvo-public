<?php

namespace App\Services\Admin;

use RuntimeException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * Admin terminal primitives: run shell/artisan commands and read/write project
 * files. This is an intentionally powerful tool — it is gated by the dedicated
 * `terminal.use` permission, every action is audited, and writes require the
 * caller to name (confirm) the exact target file first, proving the file was
 * opened before it was modified. File contents are never written to the audit
 * log; only paths, sizes and hashes are recorded.
 */
class TerminalService
{
    public const MAX_OUTPUT = 200000;

    public const MAX_FILE_BYTES = 2097152; // 2 MB per read/write

    public const NEW_FILE_TOKEN = '__NEW__';

    protected string $root;

    public function __construct()
    {
        $this->root = rtrim(realpath(base_path()) ?: base_path(), '/');
    }

    public function root(): string
    {
        return $this->root;
    }

    /**
     * Run a raw shell command from the project root.
     *
     * @return array{command:string,exit_code:int,stdout:string,stderr:string,timed_out:bool,duration_ms:int}
     */
    public function run(string $command, int $timeout = 30): array
    {
        $command = trim($command);

        if ($command === '') {
            throw new RuntimeException('Command must not be empty.');
        }

        $process = Process::fromShellCommandline($command, $this->root, null, null, $timeout);

        $start = microtime(true);
        $timedOut = false;

        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            $timedOut = true;
        }

        return [
            'command' => $command,
            'exit_code' => $process->getExitCode() ?? -1,
            'stdout' => mb_substr($process->getOutput(), 0, self::MAX_OUTPUT),
            'stderr' => mb_substr($process->getErrorOutput(), 0, self::MAX_OUTPUT),
            'timed_out' => $timedOut,
            'duration_ms' => (int) round((microtime(true) - $start) * 1000),
        ];
    }

    /**
     * Run an artisan command. Only the argument string is accepted; the binary
     * and working directory are supplied by this service.
     *
     * @return array<string, mixed>
     */
    public function artisan(string $arguments, int $timeout = 60): array
    {
        $arguments = trim($arguments);

        if ($arguments === '' || str_contains($arguments, "\0")) {
            throw new RuntimeException('Artisan arguments must not be empty.');
        }

        $result = $this->run($this->binary().' artisan '.$arguments, $timeout);
        $result['artisan'] = $arguments;

        return $result;
    }

    protected function binary(): string
    {
        return PHP_SAPI === 'cli' ? escapeshellarg(PHP_BINARY) : 'php';
    }

    /**
     * Resolve a user-supplied path to an absolute path, refusing anything that
     * escapes the project root (path traversal, absolute paths outside, and
     * symlinked files that point outside).
     */
    public function resolvePath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));

        if ($path === '' || str_contains($path, "\0")) {
            throw new RuntimeException('Path is required.');
        }

        $candidate = str_starts_with($path, '/')
            ? $path
            : $this->root.'/'.ltrim($path, '/');

        $normalized = $this->normalize($candidate);

        if (! $this->within($normalized)) {
            throw new RuntimeException('Path escapes the project root: '.$path);
        }

        if (file_exists($normalized)) {
            $real = realpath($normalized);

            if ($real === false || ! $this->within($real)) {
                throw new RuntimeException('Resolved path escapes the project root: '.$path);
            }

            return $real;
        }

        return $normalized;
    }

    protected function normalize(string $path): string
    {
        $parts = [];
        $absolute = str_starts_with($path, '/');

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($parts);

                continue;
            }

            $parts[] = $segment;
        }

        $out = implode('/', $parts);
        if ($absolute) {
            return '/'.$out;
        }

        return $out === '' ? '.' : $out;
    }

    protected function within(string $path): bool
    {
        if ($path === $this->root) {
            return true;
        }

        return str_starts_with($path, $this->root.'/');
    }

    public function relative(string $absolute): string
    {
        return ltrim(substr($absolute, strlen($this->root)), '/') ?: './';
    }

    /**
     * Read a project file (used by the editor and to hash the file before a
     * confirmed write).
     *
     * @return array<string, mixed>
     */
    public function read(string $path): array
    {
        $absolute = $this->resolvePath($path);

        if (! is_file($absolute)) {
            throw new RuntimeException('File not found: '.$path);
        }

        if (is_dir($absolute)) {
            throw new RuntimeException('That path is a directory, not a file.');
        }

        $size = (int) filesize($absolute);

        if ($size > self::MAX_FILE_BYTES) {
            throw new RuntimeException(
                'File is larger than the '.round(self::MAX_FILE_BYTES / 1048576).' MB editor limit ('.$size.' bytes).'
            );
        }

        $content = file_get_contents($absolute);

        if ($content === false) {
            throw new RuntimeException('Unable to read file: '.$path);
        }

        return [
            'path' => $this->relative($absolute),
            'absolute' => $absolute,
            'content' => $content,
            'size' => $size,
            'sha256' => hash('sha256', $content),
            'mtime' => date('Y-m-d H:i:s', (int) filemtime($absolute)),
            'writable' => is_writable($absolute),
        ];
    }

    /**
     * Write a project file. The caller must confirm the target first: the
     * confirmation path must match the path being written, and the caller must
     * supply the SHA-256 of the content it previously loaded (or the NEW_FILE
     * token for a brand new file). This guarantees the file is opened and
     * identified before it is modified, and prevents blind overwrites of files
     * that changed underneath the editor.
     *
     * @return array<string, mixed>
     */
    public function write(string $path, string $content, string $confirmPath, ?string $expectedSha): array
    {
        $confirmPath = trim($confirmPath);

        if ($confirmPath === '') {
            throw new RuntimeException('Confirm the target file first: no target was confirmed.');
        }

        if ($confirmPath !== trim($path)) {
            throw new RuntimeException('Confirmed target "'.$confirmPath.'" does not match the file being written.');
        }

        if (strlen($content) > self::MAX_FILE_BYTES) {
            throw new RuntimeException('Content exceeds the '.round(self::MAX_FILE_BYTES / 1048576).' MB write limit.');
        }

        $absolute = $this->resolvePath($path);
        $exists = is_file($absolute);
        $before = '';

        if ($exists) {
            $current = file_get_contents($absolute);

            if ($current === false) {
                throw new RuntimeException('Unable to read the current file: '.$path);
            }

            $before = $current;

            if ($expectedSha === null || $expectedSha === '' || ! hash_equals(hash('sha256', $current), $expectedSha)) {
                throw new RuntimeException(
                    'The file was never loaded or has changed since it was opened. Re-open it and confirm the target before saving.'
                );
            }
        } elseif ($expectedSha !== self::NEW_FILE_TOKEN) {
            throw new RuntimeException('Confirm the new file target first (new files must be confirmed before creation).');
        }

        $directory = dirname($absolute);

        if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to create directory: '.$this->relative($directory));
        }

        $written = @file_put_contents($absolute, $content, LOCK_EX);

        if ($written === false) {
            throw new RuntimeException('Write failed (permissions?): '.$path);
        }

        clearstatcache(true, $absolute);

        return [
            'path' => $this->relative($absolute),
            'created' => ! $exists,
            'bytes' => $written,
            'before_bytes' => strlen($before),
            'before_sha256' => $exists ? hash('sha256', $before) : null,
            'sha256' => hash('sha256', $content),
            'mtime' => date('Y-m-d H:i:s', (int) @filemtime($absolute)),
            'writable' => is_writable($absolute),
        ];
    }

    /**
     * List the immediate children of a directory for the file explorer.
     *
     * @return array<string, mixed>
     */
    public function tree(?string $path = null): array
    {
        $absolute = $path ? $this->resolvePath($path) : $this->root;

        if (! is_dir($absolute)) {
            throw new RuntimeException('Not a directory: '.($path ?: '/'));
        }

        if (! is_readable($absolute)) {
            throw new RuntimeException('Directory is not readable: '.$this->relative($absolute));
        }

        $entries = [];
        $items = @scandir($absolute);

        if ($items === false) {
            throw new RuntimeException('Unable to list directory: '.$this->relative($absolute));
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $full = $absolute.'/'.$item;
            $isDir = is_dir($full);

            $entries[] = [
                'name' => $item,
                'type' => $isDir ? 'dir' : 'file',
                'path' => $this->relative($full),
                'size' => $isDir ? null : (int) @filesize($full),
                'mtime' => date('Y-m-d H:i:s', (int) @filemtime($full)),
                'ext' => $isDir ? null : pathinfo($item, PATHINFO_EXTENSION),
                'writable' => is_writable($full),
            ];
        }

        usort($entries, function (array $a, array $b) {
            if ($a['type'] !== $b['type']) {
                return $a['type'] === 'dir' ? -1 : 1;
            }

            return strcasecmp($a['name'], $b['name']);
        });

        return [
            'path' => $this->relative($absolute),
            'parent' => $absolute === $this->root
                ? null
                : $this->relative(dirname($absolute)),
            'count' => count($entries),
            'entries' => array_slice($entries, 0, 500),
        ];
    }
}
