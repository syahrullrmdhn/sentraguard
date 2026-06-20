<?php

namespace App\Http\Middleware;

use App\Services\AgentTokenService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateAgent
{
    public function __construct(
        protected AgentTokenService $tokens
    ) {}

    /**
     * Authenticate an agent request using its Bearer runtime token.
     * On success, the resolved Agent is attached to the request as 'agent'.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $bearer = $request->bearerToken();

        if (! $bearer) {
            return response()->json([
                'message' => 'Missing agent runtime token.',
            ], 401);
        }

        $agent = $this->tokens->resolveAgentByRuntimeToken($bearer);

        if (! $agent) {
            return response()->json([
                'message' => 'Invalid or revoked agent token.',
            ], 401);
        }

        if ($agent->status === 'revoked') {
            return response()->json([
                'message' => 'Agent has been revoked.',
            ], 401);
        }

        // Attach the authenticated agent for downstream controllers.
        $request->attributes->set('agent', $agent);

        return $next($request);
    }
}
