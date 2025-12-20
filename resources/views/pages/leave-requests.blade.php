@extends('layouts.app')

@section('content')

    @include('partials.menu')

    <div class="wrapper leave-requests">

        <div class="page-header">
            <div class="page-title">
                <span class="page-icon"><i class="fa-solid fa-person-walking-arrow-right"></i></span>
                <div class="page-title-text">
                    <h1>Leave Requests</h1>
                    <p>Review and process employee leave requests</p>
                </div>
            </div>
        </div>

        <div class="modal fade" id="leaveRequestDetailsModal" tabindex="-1" aria-labelledby="leaveRequestDetailsLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="leaveRequestDetailsLabel">Leave request details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-4">Employee</dt>
                            <dd class="col-sm-8" id="leave-detail-employee">&mdash;</dd>

                            <dt class="col-sm-4">Period</dt>
                            <dd class="col-sm-8" id="leave-detail-period">&mdash;</dd>

                            <dt class="col-sm-4">Type</dt>
                            <dd class="col-sm-8" id="leave-detail-type">&mdash;</dd>

                            <dt class="col-sm-4">Paid / unpaid</dt>
                            <dd class="col-sm-8" id="leave-detail-paid">&mdash;</dd>

                            <dt class="col-sm-4">Status</dt>
                            <dd class="col-sm-8" id="leave-detail-status">&mdash;</dd>

                            <dt class="col-sm-4">Requested on</dt>
                            <dd class="col-sm-8" id="leave-detail-requested">&mdash;</dd>

                            <dt class="col-sm-4">Reason</dt>
                            <dd class="col-sm-8" id="leave-detail-reason">&mdash;</dd>
                        </dl>

                        <hr class="my-3">

                        <h6 class="fw-semibold">Approval timeline</h6>
                        <ul class="list-unstyled small mb-0" id="leave-detail-approvals">
                            <li class="text-muted">No approvals yet.</li>
                        </ul>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="container leave-requests filter mb-3">
            <form method="GET" action="{{ route('leave-requests') }}" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label for="employee_id" class="form-label">Employee</label>
                    <select name="employee_id" id="employee_id" class="form-select select2">
                        <option value="">All employees</option>
                        @foreach (($employeeOptions ?? []) as $id => $name)
                            <option value="{{ $id }}" {{ (string)($filters['employee_id'] ?? '') === (string)$id ? 'selected' : '' }}>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select name="status" id="status" class="form-select">
                        <option value="">All statuses</option>
                        @foreach (($statusOptions ?? []) as $value => $label)
                            <option value="{{ $value }}" {{ ($filters['status'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="period_start" class="form-label">Period start</label>
                    <input type="date" name="period_start" id="period_start" class="form-control" value="{{ $filters['period_start'] ?? '' }}">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary mt-auto">Apply filters</button>
                    <a href="{{ route('leave-requests') }}" class="btn btn-outline-secondary mt-auto">Reset</a>
                </div>
            </form>
        </div>

        <div class="container leave-requests table-component">

            @php
                $tableData = $requestTableData ?? [];
            @endphp

            @include('components.table', [
                'tableClass' => 'leave-requests-table',
                'tableCol' => [
                    'employee-name',
                    'date-range',
                    'type',
                    'duration',
                    'paidness',
                    'status',
                    'actions',
                ],
                'tableLabel' => [
                    'Employee',
                    'Period',
                    'Type',
                    'Days',
                    'Paid / unpaid',
                    'Status',
                    'Actions',
                ],
                'tableData' => $tableData,
                'rawColumns' => ['employee-name', 'status', 'actions'],
                'sortableColumns' => [
                    'date-range' => 'date_start',
                    'status' => 'status',
                ],
                'currentSortBy' => $filters['sort_by'] ?? null,
                'currentSortDir' => $filters['sort_dir'] ?? 'desc',
            ])

            @if($requests instanceof \Illuminate\Pagination\LengthAwarePaginator || $requests instanceof \Illuminate\Pagination\Paginator)
                <div class="mt-3 d-flex justify-content-end">
                    {{ $requests->onEachSide(1)->links('pagination::bootstrap-4') }}
                </div>
            @endif

        </div>

    </div>

@endsection

@section('scripts')
    <script>
        $(document).on('click', '.leave-requests-table tbody tr', function (e) {
            if ($(e.target).closest('form,button,a,.leave-actions').length) {
                return;
            }

            var $trigger = $(this).find('.leave-request-row-trigger').first();
            if (!$trigger.length) {
                return;
            }

            var empName = $trigger.data('leave-employee') || '—';
            var empType = $trigger.data('leave-employment-type') || '';
            var empDisplay = empType ? (empName + ' (' + empType + ')') : empName;

            $('#leave-detail-employee').text(empDisplay);
            $('#leave-detail-period').text($trigger.data('leave-period') || '—');
            $('#leave-detail-type').text($trigger.data('leave-type') || '—');
            $('#leave-detail-paid').text($trigger.data('leave-paid') || '—');
            $('#leave-detail-status').text($trigger.data('leave-status') || '—');
            $('#leave-detail-requested').text($trigger.data('leave-requested') || '—');
            $('#leave-detail-reason').text($trigger.data('leave-reason') || '—');

            var approvals = $trigger.data('leave-approval') || '';
            var $list = $('#leave-detail-approvals');
            $list.empty();

            if (approvals) {
                approvals.split(' | ').forEach(function (item) {
                    if (!item) {
                        return;
                    }
                    var li = document.createElement('li');
                    li.textContent = item;
                    $list.append(li);
                });
            } else {
                var li = document.createElement('li');
                li.className = 'text-muted';
                li.textContent = 'No approvals yet.';
                $list.append(li);
            }

            var modalEl = document.getElementById('leaveRequestDetailsModal');
            if (!modalEl) {
                return;
            }
            var modal = new bootstrap.Modal(modalEl);
            modal.show();
        });
    </script>
@endsection
