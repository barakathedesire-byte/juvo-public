<?php

namespace App\Http\Middleware;

use App\Services\Admin\TerminalGate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks terminal actions until the administrator PIN has been entered for the
 * current session. Covers exec, read, write and tree; the terminal landing page
 * itself stays reachable so it can present the unlock prompt.
 */
class EnsureTerminalUnlocked
{
    public function handle(Request $request, Closure $next): Response
    {
        $gate = app(TerminalGate::class);

        if (! $gate->configured()) {
            return $this->locked($request, 'The admin terminal PIN has not been configured. An administrator must set it before the terminal can be used.');
        }

        if (! $gate->unlocked()) {
            return $this->locked($request, 'The admin terminal is locked. Enter the administrator PIN to continue.');
        }

        return $next($request);
    }

    protected function locked(Request $request, string $message): Response
    {
        if ($request->expectsJson() || $request->isJson()) {
            return response()->json(['ok' => false, 'locked' => true, 'message' => $message], 423);
        }

        return back()->with('error', $message);
    }
}
