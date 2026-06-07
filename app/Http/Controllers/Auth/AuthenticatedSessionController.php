<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\ActivityLog;
use Throwable;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => false,
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'loginname' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $userModel = config('auth.providers.users.model');

        $user = $userModel::where('loginname', $request->loginname)->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'loginname' => 'The provided credentials do not match our records.',
            ]);
        }

        $passwordIsValid = false;

        /*
         * Check Laravel bcrypt / hashed password safely.
         * Some old passwords may throw an error here, so we catch it.
         */
        try {
            $passwordIsValid = Hash::check($request->password, $user->password);
        } catch (Throwable $e) {
            $passwordIsValid = false;
        }

        /*
         * Fallback for old systems with plain-text passwords.
         * Example: username.password = mypassword
         */
        if (! $passwordIsValid && (string) $user->password === (string) $request->password) {
            $passwordIsValid = true;
        }

        /*
         * Fallback for old systems with MD5 passwords.
         * Example: username.password = md5(mypassword)
         */
        if (! $passwordIsValid && (string) $user->password === md5((string) $request->password)) {
            $passwordIsValid = true;
        }

        if (! $passwordIsValid) {
            throw ValidationException::withMessages([
                'loginname' => 'The provided credentials do not match our records.',
            ]);
        }

        Auth::login($user, $request->boolean('remember'));

        $request->session()->regenerate();

        ActivityLog::record(
            'login',
            'Authentication',
            'User logged in successfully.',
            'username',
            $user->ID ?? $user->id ?? null
        );

        /*
         * Role / rights redirect:
         * rights = 4 should see Monitoring Dashboard immediately after login.
         * Other users will continue to the normal DTS dashboard.
         */
        $rights = (int) ($user->rights ?? 0);

        if ($rights === 4) {
            return redirect('/dts/monitoring-dashboard');
        }

        return redirect()->intended('/dts');
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        ActivityLog::record(
            'logout',
            'Authentication',
            'User logged out.',
            'username',
            auth()->user()->ID ?? auth()->user()->id ?? null
        );

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
