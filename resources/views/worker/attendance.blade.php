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
            font-size: 0.9rem;
            color: #64748b;
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
            font-size: 0.9rem;
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
                <div class="worker-name">John Smith</div>
                <div class="worker-id">ID: EMP-2024-001</div>
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
                            <th>Check In</th>
                            <th>Check Out</th>
                            <th>Hours</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>2024-11-22</td>
                            <td>07:58 AM</td>
                            <td>05:02 PM</td>
                            <td>9h</td>
                            <td><span class="status present">Present</span></td>
                        </tr>
                        <tr>
                            <td>2024-11-21</td>
                            <td>08:02 AM</td>
                            <td>05:05 PM</td>
                            <td>9h</td>
                            <td><span class="status present">Present</span></td>
                        </tr>
                        <tr>
                            <td>2024-11-20</td>
                            <td>07:55 AM</td>
                            <td>06:30 PM</td>
                            <td>10.5h</td>
                            <td><span class="status present">Present</span></td>
                        </tr>
                        <tr>
                            <td>2024-11-19</td>
                            <td>08:00 AM</td>
                            <td>05:00 PM</td>
                            <td>9h</td>
                            <td><span class="status present">Present</span></td>
                        </tr>
                        <tr>
                            <td>2024-11-18</td>
                            <td>08:05 AM</td>
                            <td>05:03 PM</td>
                            <td>9h</td>
                            <td><span class="status present">Present</span></td>
                        </tr>
                        <tr>
                            <td>2024-11-15</td>
                            <td>-</td>
                            <td>-</td>
                            <td>0h</td>
                            <td><span class="status weekend">Weekend</span></td>
                        </tr>
                        <tr>
                            <td>2024-11-14</td>
                            <td>08:00 AM</td>
                            <td>05:00 PM</td>
                            <td>9h</td>
                            <td><span class="status present">Present</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
