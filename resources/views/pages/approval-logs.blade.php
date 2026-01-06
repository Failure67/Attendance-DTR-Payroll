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

            <form id="approval-logs-filter-form" method="GET" action="{{ route('approval-logs') }}" class="d-flex align-items-end flex-wrap" style="gap: 8px;">
                @include('components.search', [
                    'searchClass' => 'approval-logs',
                    'searchId' => 'approval-logs-search',
                    'searchValue' => request('action'),
                ])
                <input type="hidden" name="action" id="approval-logs-filter-action" value="{{ request('action') }}">

                <select name="resource_type" id="approval-logs-resource-type" class="tab-select">
                    <option value="">All resources</option>
                    <option value="attendance" {{ request('resource_type') === 'attendance' ? 'selected' : '' }}>Attendance</option>
                    <option value="cash_advance_request" {{ request('resource_type') === 'cash_advance_request' ? 'selected' : '' }}>Cash advance requests</option>
                    <option value="payroll" {{ request('resource_type') === 'payroll' ? 'selected' : '' }}>Payroll</option>
                </select>

                <select name="actor_role" id="approval-logs-actor-role" class="tab-select">
                    <option value="">All roles</option>
                    <option value="HR" {{ request('actor_role') === 'HR' ? 'selected' : '' }}>HR</option>
                    <option value="Supervisor" {{ request('actor_role') === 'Supervisor' ? 'selected' : '' }}>Supervisor</option>
                    <option value="Manager" {{ request('actor_role') === 'Manager' ? 'selected' : '' }}>Manager</option>
                    <option value="Admin" {{ request('actor_role') === 'Admin' ? 'selected' : '' }}>Admin</option>
                    <option value="Accounting" {{ request('actor_role') === 'Accounting' ? 'selected' : '' }}>Accounting</option>
                </select>
            </form>

            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    const form = document.getElementById('approval-logs-filter-form');
                    const searchInput = document.getElementById('approval-logs-search');
                    const searchHidden = document.getElementById('approval-logs-filter-action');
                    const resourceSelect = document.getElementById('approval-logs-resource-type');
                    const roleSelect = document.getElementById('approval-logs-actor-role');

                    let timer = null;

                    function syncSearch() {
                        if (searchHidden && searchInput) {
                            searchHidden.value = searchInput.value;
                        }
                    }

                    function submit() {
                        if (!form) {
                            return;
                        }
                        syncSearch();
                        form.submit();
                    }

                    if (searchInput) {
                        searchInput.addEventListener('input', function () {
                            syncSearch();

                            if (timer) {
                                clearTimeout(timer);
                            }

                            timer = setTimeout(function () {
                                submit();
                            }, 400);
                        });
                    }

                    if (resourceSelect) {
                        resourceSelect.addEventListener('change', submit);
                    }

                    if (roleSelect) {
                        roleSelect.addEventListener('change', submit);
                    }

                    if (form) {
                        form.addEventListener('submit', function () {
                            syncSearch();
                        });
                    }

                    syncSearch();
                });
            </script>

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
