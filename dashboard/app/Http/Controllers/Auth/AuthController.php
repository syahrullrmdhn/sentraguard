<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    /**
     * Show the login form.
     */
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    /**
     * Handle a login attempt.
     */
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $remember = $request->boolean('remember');

        if (Auth::attempt($credentials, $remember)) {
            $request->session()->regenerate();

            AuditLog::log([
                'action' => 'auth.login',
                'description' => 'User logged in',
                'result' => 'success',
            ]);

            return redirect()->intended(route('dashboard'));
        }

        AuditLog::log([
            'user_id' => null,
            'actor_identifier' => $credentials['email'],
            'action' => 'auth.login',
            'description' => 'Failed login attempt',
            'result' => 'failed',
        ]);

        return back()
            ->withErrors(['email' => 'Email atau password salah.'])
            ->onlyInput('email');
    }

    /**
     * Log the user out.
     */
    public function logout(Request $request): RedirectResponse
    {
        AuditLog::log([
            'action' => 'auth.logout',
            'description' => 'User logged out',
        ]);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
