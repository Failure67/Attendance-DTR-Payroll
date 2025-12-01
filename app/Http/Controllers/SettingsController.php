<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redirect;

class SettingsController extends Controller
{
    /**
     * Show settings page
     */
    public function show(Request $request)
    {
        $user = $this->resolveSettingsUser($request);
        return view('settings.show', compact('user'));
    }

    /**
     * Update password
     */
    public function updatePassword(Request $request)
    {
        $user = $this->resolveSettingsUser($request);

        $validated = $request->validate([
            'current_password' => ['required', function ($attribute, $value, $fail) use ($user) {
                if (!Hash::check($value, $user->password)) {
                    $fail('The current password is incorrect.');
                }
            }],
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user->update(['password' => Hash::make($validated['password'])]);

        $roleKey = strtolower($user->role ?? '');

        $guard = $roleKey === 'superadmin'
            ? 'superadmin'
            : (in_array($roleKey, ['admin', 'hr manager', 'payroll officer', 'accounting', 'project manager', 'supervisor']) ? 'admin' : 'worker');

        return Redirect::route('profile.show', [
                'guard' => $guard,
            ])
            ->with('password_success', 'Password changed successfully!');
    }

    /**
     * Resolve which guard's user record should be used for settings.
     */
    private function resolveSettingsUser(Request $request)
    {
        // Prefer explicit guard hints first (query string or form input)
        $guardHint = $request->query('guard') ?? $request->input('guard');

        if ($guardHint === 'superadmin' && Auth::guard('superadmin')->check()) {
            return Auth::guard('superadmin')->user();
        }

        if ($guardHint === 'admin' && Auth::guard('admin')->check()) {
            return Auth::guard('admin')->user();
        }

        if ($guardHint === 'worker' && Auth::guard('web')->check()) {
            return Auth::guard('web')->user();
        }

        // Worker area URLs ("/worker" prefix) should always use the worker guard
        if ($request->is('worker*') && Auth::guard('web')->check()) {
            return Auth::guard('web')->user();
        }

        // Default priority when both are logged in: prefer superadmin, then admin, for non-worker pages
        if (Auth::guard('superadmin')->check()) {
            return Auth::guard('superadmin')->user();
        }

        if (Auth::guard('admin')->check()) {
            return Auth::guard('admin')->user();
        }

        if (Auth::guard('web')->check()) {
            return Auth::guard('web')->user();
        }

        abort(403);
    }
}
