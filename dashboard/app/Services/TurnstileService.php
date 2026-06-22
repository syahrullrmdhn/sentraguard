<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TurnstileService
{
    private string $secretKey;

    public function __construct()
    {
        $this->secretKey = config('services.turnstile.secret_key');
    }

    /**
     * Verify Cloudflare Turnstile token
     *
     * @param string $token
     * @param string|null $ip
     * @return bool
     */
    public function verify(string $token, ?string $ip = null): bool
    {
        if (empty($this->secretKey)) {
            Log::warning('Turnstile secret key not configured');
            return false;
        }

        try {
            $response = Http::asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => $this->secretKey,
                'response' => $token,
                'remoteip' => $ip,
            ]);

            $result = $response->json();

            if (!$result || !isset($result['success'])) {
                Log::error('Turnstile verification failed: Invalid response', ['response' => $result]);
                return false;
            }

            if (!$result['success']) {
                Log::warning('Turnstile verification failed', [
                    'error_codes' => $result['error-codes'] ?? [],
                ]);
                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Turnstile verification exception', ['error' => $e->getMessage()]);
            return false;
        }
    }
}
