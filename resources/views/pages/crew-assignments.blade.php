@extends('layouts.app')

@section('content')

    @include('partials.menu')

    <div class="wrapper {{ $pageClass }}">

        <div class="crew-header">
            <div class="crew-title">
                <span class="crew-icon"><i class="fa-solid fa-people-group"></i></span>
                <div class="crew-title-text">
                    <h1>Crew Assignments</h1>
                    <p>Manage and assign workers to supervisor crews</p>
                </div>
            </div>
        </div>

        <div class="crew-cards">

            {{-- Load crew card --}}
            <div class="crew-card load-crew">
                <div class="crew-card-header">
                    <div class="crew-card-title">Load Crew</div>
                    <div class="crew-card-subtitle">Select a supervisor to view and manage their crew</div>
                </div>

                <div class="crew-card-body">
                    @php
                        $currentRole = strtolower(auth()->user()->role ?? '');
                    @endphp

                    @if ($canChooseSupervisor)
                        <form method="GET" action="{{ route('crew.assignments') }}" class="crew-form">
                            <div class="crew-form-field">
                                <label for="crew_supervisor" class="crew-label">Supervisor</label>
                                <select name="supervisor_id" id="crew_supervisor" class="crew-select">
                                    @forelse ($supervisors as $supervisor)
                                        <option value="{{ $supervisor->id }}" @if($selectedSupervisorId == $supervisor->id) selected @endif>
                                            {{ $supervisor->full_name ?? $supervisor->username }}
                                        </option>
                                    @empty
                                        <option value="">No supervisors found</option>
                                    @endforelse
                                </select>
                            </div>

                            <div class="crew-form-actions">
                                <button type="submit" class="button main crew-btn crew-btn-secondary">
                                    <i class="fa-solid fa-rotate-right me-1"></i> Load crew
                                </button>
                            </div>
                        </form>
                    @else
                        <div class="crew-form">
                            <div class="crew-form-field">
                                <label class="crew-label">Supervisor</label>
                                <div class="crew-readonly">
                                    {{ $currentSupervisor->full_name ?? $currentSupervisor->username ?? 'Unknown supervisor' }}
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Add workers card --}}
            <div class="crew-card add-workers-card">
                <div class="crew-card-header">
                    <div class="crew-card-title">Add Workers</div>
                    <div class="crew-card-subtitle">Select workers to add to the current crew</div>
                </div>

                <div class="crew-card-body">
                    <form method="POST" action="{{ route('crew.assignments.store') }}" id="add-workers-form" class="crew-form">
                        @csrf
                        <input type="hidden" name="supervisor_id" value="{{ $selectedSupervisorId }}">

                        <div class="crew-form-field">

                            <label class="crew-label">Available workers</label>

                            {{-- Selected worker chips --}}
                            <div class="crew-chips" id="selected-workers-chips" @if(!$selectedSupervisorId) data-disabled="true" @endif>
                                {{-- Chips will be managed by JS using hidden select values --}}
                            </div>

                            <div class="crew-workers-list-wrapper">
                                <ul class="crew-workers-list" id="crew_workers_list" @if(!$selectedSupervisorId) data-disabled="true" @endif>
                                    @forelse ($availableWorkers as $worker)
                                        @php
                                            $name = $worker->full_name ?? $worker->username;
                                        @endphp
                                        <li class="crew-worker-row" data-worker-id="{{ $worker->id }}" data-worker-name="{{ $name }}">
                                            <span class="crew-worker-label">{{ $name }}</span>
                                            <span class="crew-worker-check"><i class="fa-solid fa-check"></i></span>
                                        </li>
                                    @empty
                                        <li class="crew-worker-row disabled">
                                            <span class="crew-worker-label text-muted">No available workers to add</span>
                                        </li>
                                    @endforelse
                                </ul>

                                {{-- Hidden select used only for form submission --}}
                                <select name="worker_ids[]" id="crew_workers" multiple style="display: none;">
                                    @foreach ($availableWorkers as $worker)
                                        <option value="{{ $worker->id }}">{{ $worker->full_name ?? $worker->username }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="crew-help-text">Click rows to select multiple workers.</div>
                        </div>

                        <div class="crew-form-actions">
                            <button id="worker-add-btn" type="button" class="button main crew-btn crew-btn-primary" @if(!$selectedSupervisorId) disabled @endif>
                                <i class="fa-solid fa-plus me-1"></i> Add to crew
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Current crew card --}}
            <div class="crew-card current-crew-card">
                <div class="crew-card-header">
                    <div class="crew-card-title">
                        Current Crew Members
                        @if(!empty($crewAssignments) && $crewAssignments->count() > 0)
                            <span class="crew-count">({{ $crewAssignments->count() }})</span>
                        @endif
                    </div>
                    <div class="crew-card-subtitle">Workers currently assigned to this supervisor's crew</div>
                </div>

                <div class="crew-card-body">
                    @if(empty($crewAssignments) || $crewAssignments->count() === 0)
                        <div class="crew-empty">No workers assigned to this crew yet.</div>
                    @else
                        <div class="crew-table">
                            <div class="crew-table-header">
                                <div class="crew-table-col worker">Worker</div>
                                <div class="crew-table-col actions">Actions</div>
                            </div>
                            <div class="crew-table-body">
                                @foreach($crewAssignments as $assignment)
                                    @php
                                        $worker = $assignment->worker;
                                        $name = $worker ? ($worker->full_name ?? $worker->username) : 'Unknown worker';
                                        $initials = '';
                                        if ($worker) {
                                            $parts = explode(' ', trim($worker->full_name ?? $worker->username));
                                            $initials = strtoupper(mb_substr($parts[0] ?? '', 0, 1) . mb_substr(end($parts) ?: '', 0, 1));
                                        }
                                    @endphp
                                    <div class="crew-table-row">
                                        <div class="crew-table-cell worker">
                                            <div class="crew-avatar">{{ $initials }}</div>
                                            <span class="crew-worker-name">{{ $name }}</span>
                                        </div>
                                        <div class="crew-table-cell actions">
                                            <form method="POST" action="{{ route('crew.assignments.delete', ['id' => $assignment->id]) }}" onsubmit="return confirm('Remove this worker from the crew?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="crew-remove-btn" title="Remove from crew">
                                                    <i class="fa-solid fa-user-minus"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>

        </div>

    </div>

@endsection

@section('scripts')
        <script>
            $(document).ready(function() {
                const $select = $('#crew_workers');
                const $chips = $('#selected-workers-chips');
                const $list = $('#crew_workers_list');

                function syncChipsFromSelection() {
                    $chips.empty();

                    const selectedOptions = $select.find('option:selected');

                    selectedOptions.each(function() {
                        const $opt = $(this);
                        const id = $opt.val();
                        const name = $opt.text();

                        const $chip = $('<button type="button" class="crew-chip" data-worker-id="' + id + '"></button>');
                        const $label = $('<span class="crew-chip-label"></span>').text(name);
                        const $remove = $('<span class="crew-chip-remove">&times;</span>');

                        $chip.append($label).append($remove);
                        $chips.append($chip);
                    });

                    // Sync selected state on rows
                    $list.find('.crew-worker-row').each(function() {
                        const $row = $(this);
                        const id = String($row.data('worker-id'));
                        const opt = $select.find('option[value="' + id + '"]').get(0);
                        $row.toggleClass('selected', !!opt && opt.selected);
                    });
                }

                // Toggle selection when clicking on custom rows (no Shift required)
                $list.on('click', '.crew-worker-row', function(e) {
                    const $row = $(this);
                    if ($row.hasClass('disabled') || $list.data('disabled')) {
                        return;
                    }

                    const workerId = String($row.data('worker-id'));
                    const opt = $select.find('option[value="' + workerId + '"]').get(0);
                    if (!opt) return;

                    opt.selected = !opt.selected;
                    $row.toggleClass('selected', opt.selected);
                    syncChipsFromSelection();
                });

                // Remove from selection when chip X is clicked
                $chips.on('click', '.crew-chip-remove', function() {
                    const $chip = $(this).closest('.crew-chip');
                    const workerId = $chip.data('worker-id');
                    const option = $select.find('option[value="' + workerId + '"]')[0];
                    if (option) {
                        option.selected = false;
                    }
                    $list.find('.crew-worker-row[data-worker-id="' + workerId + '"]').removeClass('selected');
                    $chip.remove();
                });

                // Submit add-to-crew
                $('#worker-add-btn').on('click', function(e) {
                    e.preventDefault();

                    if ($(this).prop('disabled')) {
                        return false;
                    }

                    const selectedWorkers = $select.val();

                    if (!selectedWorkers || selectedWorkers.length === 0) {
                        alert('Please select at least one worker to add to the crew.');
                        return false;
                    }

                    const supervisorId = $('#crew_supervisor').val();

                    if (!supervisorId) {
                        alert('Please select a supervisor first.');
                        return false;
                    }

                    $('#add-workers-form').submit();
                });

                // Initialize chips if there are pre-selected options (rare case)
                syncChipsFromSelection();
            });
        </script>
    @endsection