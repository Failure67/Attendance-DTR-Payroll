<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Show login form
     */
    public function showLoginForm()
    {
        return view('auth.login');
    }

    /**
     * Handle login
     */
    public function handleLogin(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:8',
        ]);

        if (Auth::attempt($credentials, $request->filled('remember'))) {
            $request->session()->regenerate();

            return redirect()->intended(route('index'));
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    /**
     * Show register form
     */
    public function showRegisterForm()
    {
        return view('auth.signup');
    }

    /**
     * Handle register
     */
    public function handleRegister(Request $request)
    {
        $validated = $request->validate([
            'username' => 'required|string|unique:users|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'username' => $validated['username'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        Auth::login($user);

        return redirect()->intended(route('index'));
    }

    /**
     * Handle logout
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect(route('auth.login.show'));
    }

    /**
     * Show forgot password form
     */
    public function showForgotPasswordForm()
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle forgot password request
     */
    public function handleForgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email|exists:users']);

        $user = User::where('email', $request->email)->first();
        
        if (!$user) {
            return back()->withErrors(['email' => 'Email not found in our system.']);
        }

        $token = bin2hex(random_bytes(32));
        
        cache()->put('password_reset_' . $token, $request->email, now()->addMinutes(60));

        // Redirect to reset form with token
        return redirect()->route('auth.reset.show', ['token' => $token])
                        ->with('email', $request->email);
    }

    /**
     * Show password reset form
     */
    public function showResetForm($token)
    {
        $email = cache()->get('password_reset_' . $token);
        
        if (!$email) {
            return redirect(route('auth.login.show'))
                   ->withErrors(['token' => 'This password reset link has expired.']);
        }

        return view('auth.reset-password', ['token' => $token, 'email' => $email]);
    }

    /**
     * Handle password reset
     */
    public function handleReset(Request $request)
    {
        $validated = $request->validate([
            'token' => 'required|string',
            'email' => 'required|email|exists:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $email = cache()->get('password_reset_' . $validated['token']);
        
        if (!$email || $email !== $validated['email']) {
            return back()->withErrors(['token' => 'This password reset link is invalid.']);
        }

        $user = User::where('email', $validated['email'])->first();
        $user->update(['password' => Hash::make($validated['password'])]);

        cache()->forget('password_reset_' . $validated['token']);

        return redirect(route('auth.login.show'))
               ->with('success', 'Your password has been reset. Please log in.');
    }
}
