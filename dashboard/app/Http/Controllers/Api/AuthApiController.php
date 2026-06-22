<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthApiController extends Controller
{
    /**
     * POST /api/auth/login
     * Sanctum SPA cookie-based login. Requires CSRF cookie first.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'cf_turnstile_response' => ['required', 'string'],
        ]);

        // Verify Turnstile
        $turnstileService = app(\App\Services\TurnstileService::class);
        if (!$turnstileService->verify($request->input('cf_turnstile_response'), $request->ip())) {
            throw ValidationException::withMessages([
                'email' => ['Verifikasi keamanan gagal. Silakan coba lagi.'],
            ]);
        }

        // Only email + password for Auth::attempt
        $credentials = $request->only(['email', 'password']);
        $remember = $request->boolean('remember');

        if (! Auth::attempt($credentials, $remember)) {
            AuditLog::log([
                'user_id' => null,
                'actor_identifier' => $credentials['email'],
                'action' => 'auth.login',
                'description' => 'Failed login attempt',
                'result' => 'failed',
            ]);

            throw ValidationException::withMessages([
                'email' => ['Email atau password salah.'],
            ]);
        }

        $request->session()->regenerate();

        AuditLog::log([
            'action' => 'auth.login',
            'description' => 'User logged in (SPA)',
            'result' => 'success',
        ]);

        return response()->json([
            'user' => $this->userPayload($request->user()),
        ]);
    }

    /**
     * GET /api/auth/me
     * Return the currently authenticated user.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $this->userPayload($request->user()),
        ]);
    }

    /**
     * POST /api/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        AuditLog::log([
            'action' => 'auth.logout',
            'description' => 'User logged out (SPA)',
        ]);

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out']);
    }

    /**
     * Shape the user object returned to the SPA.
     */
    protected function userPayload($user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role?->name,
            'is_super_admin' => $user->isSuperAdmin(),
        ];
    }
}
