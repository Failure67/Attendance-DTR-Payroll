@extends('layouts.app')

@section('content')

    @include('partials.menu')

    <div class="wrapper {{ $pageClass }}">

        <h1>{{ $title }}</h1>

        <div class="container {{ $pageClass }} tab">

            <div class="d-flex align-items-center" style="gap: 10px;">
                {{-- Simple role filter --}}
                <form method="GET" action="{{ route('activity-logs') }}" class="d-flex align-items-center" style="gap: 8px;">
                    <select name="role" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All roles</option>
                        <option value="HR" {{ request('role') === 'HR' ? 'selected' : '' }}>HR</option>
                        <option value="Supervisor" {{ request('role') === 'Supervisor' ? 'selected' : '' }}>Supervisor</option>
                    </select>
                </form>
            </div>

        </div>

        <div class="container {{ $pageClass }} table-component">

            <div class="tab-content active">
                @php
                    $tableData = $logs->map(function ($log) {
                        $user = $log->user;
                        $meta = [];
                        if ($log->description) {
                            $decoded = json_decode($log->description, true);
                            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                $meta = $decoded;
                            }
                        }

                        return [
                            $user?->full_name ?? $user?->username ?? 'Unknown user',
                            $log->role ?? 'N/A',
                            $log->action,
                            $meta['route'] ?? ($meta['path'] ?? ''),
                            optional($log->created_at)->format('Y-m-d H:i:s'),
                        ];
                    })->toArray();
                @endphp

                @include('components.table', [
                    'tableClass' => 'activity-logs-table',
                    'tableCol' => ['user', 'role', 'action', 'route', 'when'],
                    'tableLabel' => ['User', 'Role', 'Action', 'Route', 'When'],
                    'tableData' => $tableData,
                    'rawColumns' => [],
                ])

                <div class="mt-2">
                    {{ $logs->links() }}
                </div>
            </div>

        </div>

    </div>

@endsection
