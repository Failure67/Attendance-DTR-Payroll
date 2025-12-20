<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use DB;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function viewUsers()
    {
        $currentRole = strtolower(auth()->user()->role ?? '');

        $activeQuery = User::with('userCredential')->whereNull('deleted_at');
        $archivedQuery = User::with('userCredential')->onlyTrashed();

        $perPage = 10;

        if ($currentRole === 'superadmin') {
            $users = $activeQuery
                ->whereNotIn('role', ['Superadmin', 'superadmin'])
                ->orderBy('full_name')
                ->orderBy('username')
                ->paginate($perPage);

            $archivedUsers = $archivedQuery
                ->whereNotIn('role', ['Superadmin', 'superadmin'])
                ->orderBy('full_name')
                ->orderBy('username')
                ->paginate($perPage, ['*'], 'archived_page');
        } else {
            $users = $activeQuery
                ->whereNotIn('role', ['Admin', 'admin', 'Superadmin', 'superadmin'])
                ->orderBy('full_name')
                ->orderBy('username')
                ->paginate($perPage);

            $archivedUsers = $archivedQuery
                ->whereNotIn('role', ['Admin', 'admin', 'Superadmin', 'superadmin'])
                ->orderBy('full_name')
                ->orderBy('username')
                ->paginate($perPage, ['*'], 'archived_page');
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
            'role' => 'required|in:Admin,HR,Manager,Supervisor,Worker',
            'password' => 'required|string|min:12',
            'employment_type' => 'nullable|in:regular,part_time',
            'employment_start_date' => 'nullable|date',
            'birthdate' => 'required|date',
            'gender' => 'required|in:Male,Female',
            'sss_number' => 'nullable|string|max:30',
            'philhealth_number' => 'nullable|string|max:30',
            'pagibig_number' => 'nullable|string|max:30',
        ]);

        $currentUser = $request->user();
        $currentRoleKey = strtolower($currentUser->role ?? '');

        DB::beginTransaction();

        try {
            $baseUsername = strtolower(str_replace(' ', '.', $validated['full_name']));
            $username = $baseUsername;
            $counter = 1;

            while (User::where('username', $username)->exists()) {
                $username = $baseUsername . $counter;
                $counter++;
            }

            // Only Admin may specify employment_type on create; everyone else
            // (e.g. Superadmin/dev roles) defaults to regular and must use
            // the governed change workflow to alter it later.
            $employmentType = User::EMPLOYMENT_TYPE_REGULAR;
            if ($currentRoleKey === 'admin' && !empty($validated['employment_type'])) {
                $employmentType = $validated['employment_type'];
            }

            $employmentStartDate = now()->toDateString();
            if ($currentRoleKey === 'admin' && !empty($validated['employment_start_date'])) {
                $employmentStartDate = $validated['employment_start_date'];
            }

            $user = User::create([
                'username' => $username,
                'full_name' => $validated['full_name'],
                'email' => $validated['email'],
                'password' => \Hash::make($validated['password']),
                'role' => $validated['role'],
                'employment_type' => $employmentType,
                'employment_start_date' => $employmentStartDate,
            ]);

            $fullName = trim((string) $validated['full_name']);
            $nameParts = preg_split('/\s+/', $fullName) ?: [];
            $firstname = $nameParts[0] ?? $fullName;
            $lastname = count($nameParts) > 1 ? $nameParts[count($nameParts) - 1] : $firstname;
            $middlename = count($nameParts) > 2 ? implode(' ', array_slice($nameParts, 1, -1)) : null;

            $user->userCredential()->create([
                'firstname' => substr((string) $firstname, 0, 30),
                'middlename' => $middlename ? substr((string) $middlename, 0, 30) : null,
                'lastname' => substr((string) $lastname, 0, 30),
                'birthdate' => $validated['birthdate'],
                'gender' => $validated['gender'],
                'sss_number' => $validated['sss_number'] ?? null,
                'philhealth_number' => $validated['philhealth_number'] ?? null,
                'pagibig_number' => $validated['pagibig_number'] ?? null,
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
            'role' => 'required|in:Admin,HR,Manager,Supervisor,Worker',
            'password' => 'nullable|string|min:12',
            'employment_type' => 'nullable|in:regular,part_time',
            'employment_start_date' => 'nullable|date',
            'birthdate' => 'required|date',
            'gender' => 'required|in:Male,Female',
            'sss_number' => 'nullable|string|max:30',
            'philhealth_number' => 'nullable|string|max:30',
            'pagibig_number' => 'nullable|string|max:30',
        ]);

        $currentUser = $request->user();
        $currentRoleKey = strtolower($currentUser->role ?? '');

        DB::beginTransaction();

        try {
            $oldEmploymentType = $user->employment_type ?? User::EMPLOYMENT_TYPE_REGULAR;

            $user->full_name = $validated['full_name'];
            $user->email = $validated['email'];
            $user->role = $validated['role'];

            // Only Admin can directly edit employment_type via the Users
            // management UI. Other roles (including Superadmin/dev) must use
            // the governed employment-type change workflow.
            if (
                $currentRoleKey === 'admin'
                && array_key_exists('employment_type', $validated)
                && !empty($validated['employment_type'])
            ) {
                $user->employment_type = $validated['employment_type'];
            }

            if (
                $currentRoleKey === 'admin'
                && array_key_exists('employment_start_date', $validated)
                && !empty($validated['employment_start_date'])
            ) {
                $user->employment_start_date = $validated['employment_start_date'];
            }

            if (!empty($validated['password'])) {
                $user->password = \Hash::make($validated['password']);
            }

            $user->save();

            $credData = [
                'birthdate' => $validated['birthdate'],
                'gender' => $validated['gender'],
                'sss_number' => $validated['sss_number'] ?? null,
                'philhealth_number' => $validated['philhealth_number'] ?? null,
                'pagibig_number' => $validated['pagibig_number'] ?? null,
            ];

            if ($user->userCredential) {
                $user->userCredential->fill($credData);
                $user->userCredential->save();
            } else {
                $fullName = trim((string) $validated['full_name']);
                $nameParts = preg_split('/\s+/', $fullName) ?: [];
                $firstname = $nameParts[0] ?? $fullName;
                $lastname = count($nameParts) > 1 ? $nameParts[count($nameParts) - 1] : $firstname;
                $middlename = count($nameParts) > 2 ? implode(' ', array_slice($nameParts, 1, -1)) : null;

                $user->userCredential()->create(array_merge([
                    'firstname' => substr((string) $firstname, 0, 30),
                    'middlename' => $middlename ? substr((string) $middlename, 0, 30) : null,
                    'lastname' => substr((string) $lastname, 0, 30),
                ], $credData));
            }

            $newEmploymentType = $user->employment_type ?? User::EMPLOYMENT_TYPE_REGULAR;

            if ($newEmploymentType !== $oldEmploymentType && $currentUser) {
                ActivityLog::create([
                    'user_id' => $currentUser->id,
                    'role' => $currentUser->role ?? null,
                    'action' => 'employment_type_changed_direct',
                    'description' => 'Changed employment type for ' . ($user->full_name ?? $user->username)
                        . ' from ' . $oldEmploymentType . ' to ' . $newEmploymentType . ' via Users management.',
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            }

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
