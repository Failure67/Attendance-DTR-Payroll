@extends('layouts.app')

@section('content')

    @include('partials.menu')

    <div class="wrapper {{ $pageClass }}">

        <h1>{{ $title }}</h1>

        <div class="container {{ $pageClass }} mb-3">

            @php
                $currentRole = strtolower(auth()->user()->role ?? '');
            @endphp

            @if ($canChooseSupervisor)
                <form method="GET" action="{{ route('crew.assignments') }}" class="row g-3 align-items-end">
                    <div class="col-12 col-md-6 col-lg-4">
                        <label for="crew_supervisor" class="form-label mb-1">Supervisor</label>
                        <select name="supervisor_id" id="crew_supervisor" class="form-select">
                            @forelse ($supervisors as $supervisor)
                                <option value="{{ $supervisor->id }}" @if($selectedSupervisorId == $supervisor->id) selected @endif>
                                    {{ $supervisor->full_name ?? $supervisor->username }}
                                </option>
                            @empty
                                <option value="">No supervisors found</option>
                            @endforelse
                        </select>
                    </div>
                    <div class="col-12 col-md-6 col-lg-auto d-flex align-items-end justify-content-md-end">
                        <button type="submit" class="btn btn-primary w-100 w-md-auto">Load crew</button>
                    </div>
                </form>
            @else
                <div class="row">
                    <div class="col-12 col-md-6 col-lg-4">
                        <label class="form-label mb-1">Supervisor</label>
                        <div class="form-control-plaintext fw-semibold">
                            {{ $currentSupervisor->full_name ?? $currentSupervisor->username ?? 'Unknown supervisor' }}
                        </div>
                    </div>
                </div>
            @endif

        </div>

        <div class="container {{ $pageClass }} mb-3">
            <form method="POST" action="{{ route('crew.assignments.store') }}" class="row g-3 align-items-end">
                @csrf

                <input type="hidden" name="supervisor_id" value="{{ $selectedSupervisorId }}">

                <div class="col-12 col-md-8 col-lg-6">
                    <label for="crew_workers" class="form-label mb-1">Add workers to crew</label>
                    <select name="worker_ids[]" id="crew_workers" class="form-select" multiple size="5" @if(!$selectedSupervisorId) disabled @endif>
                        @forelse ($availableWorkers as $worker)
                            <option value="{{ $worker->id }}">
                                {{ $worker->full_name ?? $worker->username }}
                            </option>
                        @empty
                            <option value="" disabled>No available workers to add</option>
                        @endforelse
                    </select>
                    <small class="text-muted">Hold Ctrl (or Cmd on Mac) to select multiple workers.</small>
                </div>

                <div class="col-12 col-md-4 col-lg-auto d-flex align-items-end justify-content-md-end">
                    <button type="submit" class="btn btn-primary w-100 w-md-auto" @if(!$selectedSupervisorId) disabled @endif>
                        Add to crew
                    </button>
                </div>

            </form>
        </div>

        <div class="container {{ $pageClass }} table-component">

            @php
                $tableData = $crewTableData ?? [];

                if (empty($tableData)) {
                    $tableData = [[
                        '<span class="text-muted">No workers assigned to this crew yet.</span>',
                        '',
                    ]];
                }
            @endphp

            @include('components.table', [
                'tableClass' => 'crew-assignments-table',
                'tableCol' => [
                    'worker',
                    'actions',
                ],
                'tableLabel' => [
                    'Worker',
                    'Actions',
                ],
                'tableData' => $tableData,
                'rawColumns' => ['worker', 'actions'],
            ])

        </div>

    </div>

@endsection
