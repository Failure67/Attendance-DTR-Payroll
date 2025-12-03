<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $logsQuery = ActivityLog::with('user')
            ->orderByDesc('created_at');

        if ($request->filled('role')) {
            $role = $request->input('role');
            $logsQuery->where('role', $role);
        }

        $logs = $logsQuery->paginate(50)->withQueryString();

        return view('pages.activity-logs', [
            'title' => 'Activity logs',
            'pageClass' => 'activity-logs',
            'logs' => $logs,
        ]);
    }
}
