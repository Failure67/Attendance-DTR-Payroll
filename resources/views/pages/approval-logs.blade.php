@extends('layouts.app')

@section('content')

    @include('partials.menu')

    <div class="wrapper {{ $pageClass }}">

        <div class="page-header">
            <div class="page-title">
                <span class="page-icon"><i class="fa-solid fa-clipboard-list"></i></span>
                <div class="page-title-text">
                    <h1>{{ $title }}</h1>
                    <p>View approval decisions across attendance, cash advances, and payroll</p>
                </div>
            </div>
        </div>

        <div class="container {{ $pageClass }} tab">

            <div class="d-flex align-items-center" style="gap: 10px;">
                <form method="GET" action="{{ route('approval-logs') }}" class="d-flex align-items-center flex-wrap" style="gap: 8px;">
                    <select name="resource_type" class="tab-select" onchange="this.form.submit()">
                        <option value="">All resources</option>
                        <option value="attendance" {{ request('resource_type') === 'attendance' ? 'selected' : '' }}>Attendance</option>
                        <option value="cash_advance_request" {{ request('resource_type') === 'cash_advance_request' ? 'selected' : '' }}>Cash advance requests</option>
                        <option value="payroll" {{ request('resource_type') === 'payroll' ? 'selected' : '' }}>Payroll</option>
                    </select>

                    <select name="actor_role" class="tab-select" onchange="this.form.submit()">
                        <option value="">All roles</option>
                        <option value="HR" {{ request('actor_role') === 'HR' ? 'selected' : '' }}>HR</option>
                        <option value="Supervisor" {{ request('actor_role') === 'Supervisor' ? 'selected' : '' }}>Supervisor</option>
                        <option value="Manager" {{ request('actor_role') === 'Manager' ? 'selected' : '' }}>Manager</option>
                        <option value="Admin" {{ request('actor_role') === 'Admin' ? 'selected' : '' }}>Admin</option>
                        <option value="Accounting" {{ request('actor_role') === 'Accounting' ? 'selected' : '' }}>Accounting</option>
                    </select>

                    <input
                        type="text"
                        name="action"
                        class="form-control form-control-sm"
                        style="max-width: 220px;"
                        placeholder="Filter by action (e.g. approved)"
                        value="{{ request('action') }}"
                    >

                    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                </form>
            </div>

        </div>

        <div class="container {{ $pageClass }} table-component">

            <div class="tab-content active">
                @php
                    $tableData = $logs->map(function ($log) {
                        $actor = $log->actor;
                        $type = (string) ($log->resource_type ?? '');
                        $typeLabel = $type !== '' ? ucwords(str_replace('_', ' ', $type)) : 'Unknown';
                        $resourceDisplay = trim($typeLabel . ' #' . $log->resource_id);

                        $meta = is_array($log->meta ?? null) ? $log->meta : [];
                        $metaSummary = '';
                        if (!empty($meta)) {
                            $metaSummary = collect($meta)
                                ->map(function ($value, $key) {
                                    if (is_scalar($value) || $value === null) {
                                        return $key . ': ' . (string) $value;
                                    }

                                    return $key . ': ' . json_encode($value);
                                })
                                ->take(3)
                                ->implode('; ');
                        }

                        return [
                            $resourceDisplay,
                            $log->action,
                            $actor?->full_name ?? $actor?->username ?? 'Unknown user',
                            $log->actor_role ?? 'N/A',
                            $metaSummary,
                            optional($log->created_at)->format('Y-m-d H:i:s'),
                        ];
                    })->toArray();
                @endphp

                @include('components.table', [
                    'tableClass' => 'approval-logs-table',
                    'tableCol' => ['resource', 'action', 'actor', 'actor_role', 'meta', 'when'],
                    'tableLabel' => ['Resource', 'Action', 'Actor', 'Actor role', 'Meta', 'When'],
                    'tableData' => $tableData,
                    'rawColumns' => [],
                ])

                <div class="mt-2">
                    @include('components.pagination', [
                        'paginationClass' => 'approval-logs',
                        'paginator' => $logs ?? null,
                    ])
                </div>
            </div>

        </div>

    </div>

@endsection
