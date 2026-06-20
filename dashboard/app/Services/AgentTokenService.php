<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\Server;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AgentTokenService
{
    /**
     * Generate a one-time registration token for a server.
     * Returns the plaintext token (shown once); only the bcrypt hash is stored.
     */
    public function generateRegistrationToken(Server $server): string
    {
        $plaintext = 'AGT_'.Str::random(40);

        $server->update([
            'registration_token_hash' => Hash::make($plaintext),
            'token_generated_at' => now(),
            'token_used' => false,
        ]);

        return $plaintext;
    }

    /**
     * Find a server whose registration token matches the supplied plaintext.
     * Token must be unused. Returns null when no match.
     */
    public function resolveServerByToken(string $plaintext): ?Server
    {
        // Token format guard
        if (! str_starts_with($plaintext, 'AGT_')) {
            return null;
        }

        // Candidates: tokens that were generated and not yet used.
        $candidates = Server::whereNotNull('registration_token_hash')
            ->where('token_used', false)
            ->get();

        foreach ($candidates as $server) {
            if (Hash::check($plaintext, $server->registration_token_hash)) {
                return $server;
            }
        }

        return null;
    }

    /**
     * Mark a server's registration token as consumed.
     */
    public function markTokenUsed(Server $server): void
    {
        $server->update(['token_used' => true]);
    }

    /**
     * Generate a runtime token for an agent.
     * Returns the plaintext (shown once to the agent); only the hash is stored.
     */
    public function generateRuntimeToken(Agent $agent): string
    {
        $plaintext = 'RT_'.Str::random(48);

        $agent->update([
            'runtime_token_hash' => Hash::make($plaintext),
        ]);

        return $plaintext;
    }

    /**
     * Resolve an agent by its runtime token (Bearer).
     * Returns null when no active agent matches.
     */
    public function resolveAgentByRuntimeToken(string $plaintext): ?Agent
    {
        if (! str_starts_with($plaintext, 'RT_')) {
            return null;
        }

        $candidates = Agent::whereNotNull('runtime_token_hash')
            ->where('status', '!=', 'revoked')
            ->get();

        foreach ($candidates as $agent) {
            if (Hash::check($plaintext, $agent->runtime_token_hash)) {
                return $agent;
            }
        }

        return null;
    }
}
