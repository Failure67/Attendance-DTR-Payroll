<?php

namespace App\Http\Controllers;

use App\Models\CrewAssignment;
use App\Models\User;
use Illuminate\Http\Request;

class CrewAssignmentController extends Controller
{
    public function viewCrewAssignments(Request $request)
    {
        $currentUser = auth()->user();
        $currentRole = strtolower($currentUser->role ?? '');

        $supervisors = User::whereNull('deleted_at')
            ->where('role', 'Supervisor')
            ->orderBy('full_name')
            ->orderBy('username')
            ->get();

        $canChooseSupervisor = $currentRole !== 'supervisor';

        if ($currentRole === 'supervisor') {
            $selectedSupervisorId = $currentUser->id;
        } else {
            $selectedSupervisorId = $request->input('supervisor_id');
            if (empty($selectedSupervisorId) && $supervisors->isNotEmpty()) {
                $selectedSupervisorId = $supervisors->first()->id;
            }
        }

        $currentSupervisor = null;
        if ($selectedSupervisorId) {
            if ($currentRole === 'supervisor' && $currentUser->id === (int) $selectedSupervisorId) {
                $currentSupervisor = $currentUser;
            } else {
                $currentSupervisor = $supervisors->firstWhere('id', (int) $selectedSupervisorId);
            }
        }

        $crewAssignments = collect();
        $availableWorkers = collect();
        $crewTableData = [];

        if ($selectedSupervisorId) {
            $crewAssignments = CrewAssignment::with('worker')
                ->where('supervisor_id', $selectedSupervisorId)
                ->get();

            $assignedWorkerIds = $crewAssignments->pluck('worker_id');

            $availableWorkers = User::whereNull('deleted_at')
                ->where('role', 'Worker')
                ->whereNotIn('id', $assignedWorkerIds)
                ->orderBy('full_name')
                ->orderBy('username')
                ->get();

            $crewTableData = $crewAssignments->map(function ($assignment) {
                $worker = $assignment->worker;
                $name = $worker ? ($worker->full_name ?? $worker->username) : 'Unknown worker';

                $workerCell = e($name);

                $csrf = csrf_token();
                $deleteForm = "<form method=\"POST\" action=\"" . route('crew.assignments.delete', ['id' => $assignment->id]) . "\" style=\"display:inline-block;\" onsubmit=\"return confirm('Remove this worker from the crew?');\">"
                    . '<input type="hidden" name="_token" value="' . $csrf . '">' .
                    '<input type="hidden" name="_method" value="DELETE">'
                    . '<button type="submit" class="btn btn-outline-danger btn-sm" title="Remove from crew">'
                    . '<i class="fa-solid fa-user-minus"></i>' .
                    '</button>' .
                    '</form>';

                return [
                    $workerCell,
                    $deleteForm,
                ];
            })->toArray();
        }

        return view('pages.crew-assignments', [
            'title' => 'Crew assignments',
            'pageClass' => 'crew-assignments',
            'supervisors' => $supervisors,
            'currentSupervisor' => $currentSupervisor,
            'selectedSupervisorId' => $selectedSupervisorId,
            'canChooseSupervisor' => $canChooseSupervisor,
            'availableWorkers' => $availableWorkers,
            'crewTableData' => $crewTableData,
        ]);
    }

    public function storeCrewAssignments(Request $request)
    {
        $currentUser = auth()->user();
        $currentRole = strtolower($currentUser->role ?? '');

        $validated = $request->validate([
            'supervisor_id' => 'required|exists:users,id',
            'worker_ids' => 'required|array',
            'worker_ids.*' => 'exists:users,id',
        ]);

        $supervisorId = (int) $validated['supervisor_id'];

        if ($currentRole === 'supervisor' && $currentUser->id !== $supervisorId) {
            abort(403, 'You are not allowed to modify another supervisor\'s crew.');
        }

        foreach ($validated['worker_ids'] as $workerId) {
            CrewAssignment::firstOrCreate([
                'supervisor_id' => $supervisorId,
                'worker_id' => $workerId,
            ]);
        }

        return redirect()->route('crew.assignments', ['supervisor_id' => $supervisorId])
            ->with('success', 'Crew assignments updated successfully.');
    }

    public function deleteCrewAssignment(Request $request, $id)
    {
        $currentUser = auth()->user();
        $currentRole = strtolower($currentUser->role ?? '');

        $assignment = CrewAssignment::findOrFail($id);

        if ($currentRole === 'supervisor' && $assignment->supervisor_id !== $currentUser->id) {
            abort(403, 'You are not allowed to modify another supervisor\'s crew.');
        }

        $supervisorId = $assignment->supervisor_id;
        $assignment->delete();

        return redirect()->route('crew.assignments', ['supervisor_id' => $supervisorId])
            ->with('success', 'Worker removed from crew successfully.');
    }
}
