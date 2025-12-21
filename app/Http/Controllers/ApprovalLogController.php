<?php

namespace App\Http\Controllers;

use App\Models\ApprovalLog;
use Illuminate\Http\Request;

class ApprovalLogController extends Controller
{
    public function index(Request $request)
    {
        $logsQuery = ApprovalLog::with('actor')
            ->orderByDesc('created_at');

        if ($request->filled('resource_type')) {
            $logsQuery->where('resource_type', $request->input('resource_type'));
        }

        if ($request->filled('action')) {
            $logsQuery->where('action', 'like', '%' . $request->input('action') . '%');
        }

        if ($request->filled('actor_role')) {
            $logsQuery->where('actor_role', $request->input('actor_role'));
        }

        $logs = $logsQuery->paginate(25)->withQueryString();

        return view('pages.approval-logs', [
            'title' => 'Approval logs',
            'pageClass' => 'approval-logs',
            'logs' => $logs,
        ]);
    }
}
