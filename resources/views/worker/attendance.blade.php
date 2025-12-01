@extends('layouts.app')

@section('styles')
    <style>
        .worker-dashboard {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 1.5rem 2rem 2.2rem;
            background: linear-gradient(135deg, #f3f6ff, #e5f0ff);
            border-radius: 18px;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.15);
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        .worker-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .worker-profile {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            color: #0f172a;
        }

        .worker-name {
            font-size: 1.4rem;
            font-weight: 700;
        }

        .worker-id {
            font-size: 0.95rem;
            color: #4b5563;
        }

        .worker-tabs {
            display: inline-flex;
            gap: 0.5rem;
            padding: 0.25rem;
            background: rgba(255, 255, 255, 0.7);
            border-radius: 999px;
        }

        .worker-tabs .tab {
            padding: 0.4rem 1.2rem;
            border-radius: 999px;
            font-size: 0.9rem;
            text-decoration: none;
            color: #0f172a;
            transition: all 0.15s ease-in-out;
        }

        .worker-tabs .tab:hover {
            background: rgba(59, 130, 246, 0.12);
        }

        .worker-tabs .tab.active {
            background: #2563eb;
            color: #ffffff;
            box-shadow: 0 8px 20px rgba(37, 99, 235, 0.4);
        }

        .worker-content {
            margin-top: 0.5rem;
        }

        .worker-table {
            background: #ffffff;
            border-radius: 18px;
            padding: 1.3rem 1.5rem 1.6rem;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.12);
        }

        .worker-table .section-title {
            font-weight: 600;
            margin-bottom: 1rem;
            color: #0f172a;
        }

        .worker-table table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.95rem;
        }

        .worker-table thead {
            background: #2563eb;
            color: #ffffff;
        }

        .worker-table th,
        .worker-table td {
            padding: 0.6rem 0.75rem;
            text-align: left;
            white-space: nowrap;
        }

        .worker-table tbody tr:nth-child(even) {
            background: #f9fafb;
        }

        .worker-table tbody tr:nth-child(odd) {
            background: #ffffff;
        }

        .status.present {
            display: inline-flex;
            align-items: center;
            padding: 0.15rem 0.6rem;
            border-radius: 999px;
            background: #dcfce7;
            color: #166534;
            font-size: 0.78rem;
            font-weight: 600;
        }

        .status.weekend {
            display: inline-flex;
            align-items: center;
            padding: 0.15rem 0.6rem;
            border-radius: 999px;
            background: #e5e7eb;
            color: #374151;
            font-size: 0.78rem;
            font-weight: 600;
        }

        @media (max-width: 768px) {
            .worker-dashboard {
                margin: 1rem;
                padding: 1.2rem 1.1rem 1.6rem;
            }

            .worker-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .worker-table table {
                font-size: 0.8rem;
            }
        }
    </style>
@endsection

@section('content')
    <div class="worker-dashboard attendance">
        <div class="worker-header">
            <div class="worker-profile">
                <div class="worker-name">{{ $user->full_name ?? $user->username }}</div>
                <div class="worker-id">ID: EMP-{{ str_pad($user->id, 4, '0', STR_PAD_LEFT) }}</div>
            </div>
            <div class="worker-tabs">
                <a href="{{ route('worker.dashboard') }}" class="tab">Overview</a>
                <a href="{{ route('worker.payroll-history') }}" class="tab">Payroll History</a>
                <a href="{{ route('worker.attendance') }}" class="tab active">Attendance</a>
            </div>
        </div>

        <div class="worker-content">
            <div class="worker-table">
                <div class="section-title">Recent Attendance</div>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Check in</th>
                            <th>Check out</th>
                            <th>Hours</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($attendances as $attendance)
                            @php
                                $date = $attendance->date
                                    ? $attendance->date->format('Y-m-d')
                                    : ($attendance->time_in ? $attendance->time_in->format('Y-m-d') : 'N/A');
                                $timeIn = $attendance->time_in ? $attendance->time_in->format('g:i A') : '—';
                                $timeOut = $attendance->time_out ? $attendance->time_out->format('g:i A') : '—';
                                $hours = number_format((float) ($attendance->total_hours ?? 0), 2) . 'h';
                                $status = $attendance->status ?? 'Present';
                                $statusClass = in_array($status, ['Present', 'Late', 'On leave'], true) ? 'present' : 'weekend';
                            @endphp
                            <tr>
                                <td>{{ $date }}</td>
                                <td>{{ $timeIn }}</td>
                                <td>{{ $timeOut }}</td>
                                <td>{{ $hours }}</td>
                                <td><span class="status {{ $statusClass }}">{{ $status }}</span></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">No attendance records found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
