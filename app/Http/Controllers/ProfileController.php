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
    public function show()
    {
        $user = Auth::user();
        return view('profile.show', compact('user'));
    }

    /**
     * Update user profile
     */
    public function update(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'username' => 'required|string|max:255|unique:users,username,' . $user->id,
            'email' => 'required|email|max:255|unique:users,email,' . $user->id,
        ]);

        $user->update($validated);

        return Redirect::route('profile.show')
                       ->with('success', 'Profile updated successfully!');
    }

    /**
     * Upload profile picture
     */
    public function uploadPicture(Request $request)
    {
        $user = Auth::user();

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

            return Redirect::route('profile.show')
                           ->with('success', 'Profile picture updated successfully!');
        } catch (\Exception $e) {
            return Redirect::route('profile.show')
                           ->withErrors(['profile_picture' => 'Error uploading picture: ' . $e->getMessage()]);
        }
    }
}
