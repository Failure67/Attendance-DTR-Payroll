<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    /**
     * Get a paginated list of users for select2
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function list(Request $request)
    {
        $search = $request->input('q');
        $page = $request->input('page', 1);
        $perPage = 10;

        // Users table uses `full_name` (and `username` as fallback) and has no `status` column.
        // We only need to exclude admin-type accounts; everyone else is a selectable employee.
        $query = User::select([
                'id',
                DB::raw("COALESCE(full_name, username) as name"),
                'email',
                'role',
            ])
            ->whereNotIn('role', ['Admin', 'admin', 'Superadmin', 'superadmin']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => $users->items(),
            'total' => $users->total(),
            'per_page' => $perPage,
            'current_page' => $users->currentPage(),
            'last_page' => $users->lastPage(),
            'next_page_url' => $users->nextPageUrl(),
            'prev_page_url' => $users->previousPageUrl(),
            'from' => $users->firstItem(),
            'to' => $users->lastItem(),
        ]);
    }
}
