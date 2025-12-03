<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;

class ProfileController extends Controller
{
    /**
     * Show user profile
     */
    public function show(Request $request)
    {
        $user = $this->resolveProfileUser($request);
        return view('profile.show', compact('user'));
    }

    /**
     * Update user profile
     */
    public function update(Request $request)
    {
        $user = $this->resolveProfileUser($request);

        $roleKey = strtolower($user->role ?? '');

        $validated = $request->validate([
            'username' => 'required|string|max:255|unique:users,username,' . $user->id,
            'email' => 'required|email|max:255|unique:users,email,' . $user->id,
        ]);

        $user->update($validated);

        $guard = $roleKey === 'superadmin'
            ? 'superadmin'
            : (in_array($roleKey, ['admin', 'hr', 'accounting', 'project manager', 'supervisor']) ? 'admin' : 'worker');

        return Redirect::route('profile.show', [
                'guard' => $guard,
            ])
            ->with('success', 'Profile updated successfully!');
    }

    /**
     * Upload profile picture
     */
    public function uploadPicture(Request $request)
    {
        $user = $this->resolveProfileUser($request);

        $roleKey = strtolower($user->role ?? '');

        try {
            $validated = $request->validate([
                'profile_picture' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120', // 5MB max
            ]);

            // Create uploads directory if it doesn't exist
            $uploadPath = public_path('uploads/profiles');
            if (!is_dir($uploadPath)) {
                mkdir($uploadPath, 0755, true);
            }

            // Delete old picture if exists
            if ($user->profile_picture && file_exists(public_path('uploads/profiles/' . $user->profile_picture))) {
                @unlink(public_path('uploads/profiles/' . $user->profile_picture));
            }

            // Store new picture
            $file = $request->file('profile_picture');
            $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file->getClientOriginalName());
            $file->move($uploadPath, $filename);

            // Update user record
            $user->update(['profile_picture' => $filename]);

            $guard = $roleKey === 'superadmin'
                ? 'superadmin'
                : (in_array($roleKey, ['admin', 'hr', 'accounting', 'project manager', 'supervisor']) ? 'admin' : 'worker');

            return Redirect::route('profile.show', [
                    'guard' => $guard,
                ])
                ->with('success', 'Profile picture updated successfully!');
        } catch (\Exception $e) {
            $guard = $roleKey === 'superadmin'
                ? 'superadmin'
                : (in_array($roleKey, ['admin', 'hr', 'accounting', 'project manager', 'supervisor']) ? 'admin' : 'worker');

            return Redirect::route('profile.show', [
                    'guard' => $guard,
                ])
                ->withErrors(['profile_picture' => 'Error uploading picture: ' . $e->getMessage()]);
        }
    }

    private function resolveProfileUser(Request $request)
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

        // Default priority when both are logged in: prefer admin for non-worker pages
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
