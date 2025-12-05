@extends('layouts.app')

@section('content')

    @include('partials.menu')

    <div class="wrapper {{ $pageClass }}">

        <h1>{{ $title }}</h1>

        <div class="crew-assignment-wrapper">

            <div class="crew-assignment-container">

                <div class="container {{ $pageClass }} mb-2">

                    @php
                        $currentRole = strtolower(auth()->user()->role ?? '');
                    @endphp

                    @if ($canChooseSupervisor)
                        <form method="GET" action="{{ route('crew.assignments') }}" class="row g-2 align-items-end">

                            <div class="supervisor">

                                <div class="supervisor-container input">

                                    <label for="crew_supervisor" class="input-label">Supervisor</label>

                                    <div class="supervisor-wrapper">

                                        <select name="supervisor_id" id="crew_supervisor" class="select w-100">
                                        @forelse ($supervisors as $supervisor)
                                            <option value="{{ $supervisor->id }}" @if($selectedSupervisorId == $supervisor->id) selected @endif>
                                                {{ $supervisor->full_name ?? $supervisor->username }}
                                            </option>
                                        @empty
                                            <option value="">No supervisors found</option>
                                        @endforelse
                                        </select>

                                        <div class="supervisor-container">

                                            <button type="submit" class="button main filter crew-assignment">Load crew</button>

                                            <button id="worker-add-btn" type="button" class="button main filter crew-assignment" @if(!$selectedSupervisorId) disabled @endif>
                                                Add to crew
                                            </button>

                                        </div>

                                    </div>

                                </div>

                            </div>

                        </form>
                    @else
                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-4">
                                <label class="input-label mb-1">Supervisor</label>
                                <div class="form-control-plaintext fw-semibold">
                                    {{ $currentSupervisor->full_name ?? $currentSupervisor->username ?? 'Unknown supervisor' }}
                                </div>
                            </div>
                        </div>
                    @endif

                </div>

                <div class="container {{ $pageClass }} mb-3">
                    <form method="POST" action="{{ route('crew.assignments.store') }}" id="add-workers-form" class="row g-3 align-items-end">
                        @csrf

                        <input type="hidden" name="supervisor_id" value="{{ $selectedSupervisorId }}">

                        <div class="add-workers">
                            <label for="crew_workers" class="input-label">Add workers to crew</label>

                            <div class="add-workers-container">

                                <select name="worker_ids[]" id="crew_workers" class="select" multiple size="20" @if(!$selectedSupervisorId) disabled @endif>
                                    @forelse ($availableWorkers as $worker)
                                        <option value="{{ $worker->id }}">
                                            {{ $worker->full_name ?? $worker->username }}
                                        </option>
                                    @empty
                                        <option value="" disabled>No available workers to add</option>
                                    @endforelse
                                </select>

                            </div>
                        </div>

                    </form>
                </div>

                <script>
                    $(document).ready(function() {
                        $('#worker-add-btn').on('click', function(e) {
                            e.preventDefault();

                            if ($(this).prop('disabled')) {
                                return false;
                            }

                            const selectedWorkers = $('#crew_workers').val();

                            if (!selectedWorkers || selectedWorkers.length === 0) {
                                alert('Please select at least one worker to add to the crew.');
                                return false;
                            }

                            const supervisorId = $('#crew_supervisor').val();
                            
                            if (!supervisorId) {
                                alert('Please select a supervisor first.');
                                return false;
                            }
                            
                            // Submit the POST form directly by ID
                            $('#add-workers-form').submit();
                        });
                    });
                </script>

            </div>

            <div class="crew-assignment-container table-field">

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

        </div>

    </div>

@endsection