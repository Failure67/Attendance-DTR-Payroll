<?php

namespace App\Http\Controllers;

use App\Models\User;
use DB;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function viewUsers()
    {
        $currentRole = strtolower(auth()->user()->role ?? '');

        $activeQuery = User::whereNull('deleted_at');
        $archivedQuery = User::onlyTrashed();

        if ($currentRole === 'superadmin') {
            $users = $activeQuery
                ->whereNotIn('role', ['Superadmin', 'superadmin'])
                ->get();

            $archivedUsers = $archivedQuery
                ->whereNotIn('role', ['Superadmin', 'superadmin'])
                ->get();
        } else {
            $users = $activeQuery
                ->whereNotIn('role', ['Admin', 'admin', 'Superadmin', 'superadmin'])
                ->get();

            $archivedUsers = $archivedQuery
                ->whereNotIn('role', ['Admin', 'admin', 'Superadmin', 'superadmin'])
                ->get();
        }
        
        return view('pages.users', [
            'title' => 'Users',
            'pageClass' => 'users',
            'users' => $users,
            'archivedUsers' => $archivedUsers,
        ]);
    }

    public function storeUser(Request $request)
    {
        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'role' => 'required|in:Admin,HR,Supervisor,Worker',
            'password' => 'required|string|min:12',
        ]);

        DB::beginTransaction();

        try {
            $baseUsername = strtolower(str_replace(' ', '.', $validated['full_name']));
            $username = $baseUsername;
            $counter = 1;

            while (User::where('username', $username)->exists()) {
                $username = $baseUsername . $counter;
                $counter++;
            }

            User::create([
                'username' => $username,
                'full_name' => $validated['full_name'],
                'email' => $validated['email'],
                'password' => \Hash::make($validated['password']),
                'role' => $validated['role'],
            ]);

            DB::commit();

            return redirect()->route('users')->with('success', 'User added successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withInput()->withErrors(['error' => 'An error occurred while adding user: ' . $e->getMessage()]);
        }
    }

    public function updateUser(Request $request, User $user)
    {
        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'role' => 'required|in:Admin,HR,Supervisor,Worker',
            'password' => 'nullable|string|min:12',
        ]);

        DB::beginTransaction();

        try {
            $user->full_name = $validated['full_name'];
            $user->email = $validated['email'];
            $user->role = $validated['role'];

            if (!empty($validated['password'])) {
                $user->password = \Hash::make($validated['password']);
            }

            $user->save();

            DB::commit();

            return redirect()->route('users')->with('success', 'User updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()
                ->withInput()
                ->withErrors(['error' => 'An error occurred while updating user: ' . $e->getMessage()]);
        }
    }

    public function archiveUser(User $user)
    {
        $user->delete();
        return response()->json(['success' => true]);
    }

    public function restoreUser($id)
    {
        $user = User::withTrashed()->findOrFail($id);
        $user->restore();
        return response()->json(['success' => true]);
    }

    public function deleteUser(Request $request, $id)
    {
        $user = User::withTrashed()->findOrFail($id);
        
        if ($user->trashed()) {
            $user->forceDelete();
        } else {
            $user->delete();
        }

        return response()->json(['success' => true]);
    }

    public function deleteMultipleUsers(Request $request)
    {
        $validated = $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
        ]);

        $users = User::whereIn('id', $validated['user_ids'])->get();
        $users->each->delete();

        return redirect()->route('users')->with('success', 'Selected users successfully deleted.');
    }
}
