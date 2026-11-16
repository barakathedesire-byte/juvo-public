<?php

namespace App\Http\Middleware;

use App\Models\SalesRep;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts the agent workspace to authenticated staff who hold an approved,
 * active (operational) agent profile. The resolved profile is bound to the
 * request so controllers and views never have to re-query it.
 */
class EnsureAgent
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('agent.login');
        }

        $agent = SalesRep::where('user_id', $user->id)->first();

        if (! $agent || ! $agent->canOperate()) {
            abort(403, 'You do not have an active agent account.');
        }

        $request->attributes->set('agent', $agent);
        view()->share('agentProfile', $agent);

        return $next($request);
    }
}
