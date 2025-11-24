@extends('layouts.app')

@section('styles')
    <style>
        .worker-dashboard {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 1.5rem 2rem 2.5rem;
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
            margin-bottom: 1.75rem;
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
            display: flex;
            flex-direction: column;
            gap: 1.75rem;
        }

        .worker-cards {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1rem;
        }

        .worker-card {
            background: #ffffff;
            border-radius: 14px;
            padding: 1rem 1.1rem;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.12);
            display: flex;
            flex-direction: column;
            gap: 0.3rem;
        }

        .worker-card .label {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #94a3b8;
        }

        .worker-card .value {
            font-size: 1.3rem;
            font-weight: 700;
            color: #0f172a;
        }

        .worker-card .sub {
            font-size: 0.85rem;
            color: #64748b;
        }

        .worker-payroll-summary {
            background: #ffffff;
            border-radius: 18px;
            padding: 1.3rem 1.5rem 1.6rem;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.12);
        }

        .worker-payroll-summary .section-title {
            font-weight: 600;
            margin-bottom: 1rem;
            color: #0f172a;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1.5rem;
        }

        .summary-col {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
        }

        .summary-col .row {
            display: flex;
            justify-content: space-between;
            font-size: 0.9rem;
            color: #0f172a;
        }

        .summary-col .row span:first-child {
            color: #64748b;
        }

        .summary-col .row.positive span:last-child {
            color: #16a34a;
        }

        .summary-col .row.negative span:last-child {
            color: #dc2626;
        }

        .summary-col .row.highlight {
            background: linear-gradient(90deg, #2563eb, #3b82f6);
            padding: 0.55rem 0.75rem;
            border-radius: 999px;
            color: #ffffff;
            font-weight: 600;
        }

        @media (max-width: 1024px) {
            .worker-cards {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
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

            .worker-cards {
                grid-template-columns: 1fr;
            }

            .summary-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endsection

@section('content')
    <div class="worker-dashboard overview">
        <div class="worker-header">
            <div class="worker-profile">
                <div class="worker-name">John Smith</div>
                <div class="worker-id">ID: EMP-2024-001</div>
            </div>
            <div class="worker-tabs">
                <a href="{{ route('worker.dashboard') }}" class="tab active">Overview</a>
                <a href="{{ route('worker.payroll-history') }}" class="tab">Payroll History</a>
                <a href="{{ route('worker.attendance') }}" class="tab">Attendance</a>
            </div>
        </div>

        <div class="worker-content">
            <div class="worker-cards">
                <div class="worker-card">
                    <div class="label">Latest Payment</div>
                    <div class="value">$5,000</div>
                    <div class="sub">November 2024</div>
                </div>
                <div class="worker-card">
                    <div class="label">Hours Worked</div>
                    <div class="value">176h</div>
                    <div class="sub">This month</div>
                </div>
                <div class="worker-card">
                    <div class="label">Overtime</div>
                    <div class="value">12h</div>
                    <div class="sub">Extra hours</div>
                </div>
                <div class="worker-card">
                    <div class="label">Position</div>
                    <div class="value">Site Worker</div>
                    <div class="sub">Construction</div>
                </div>
            </div>

            <div class="worker-payroll-summary">
                <div class="section-title">Latest Payroll Breakdown</div>
                <div class="summary-grid">
                    <div class="summary-col">
                        <div class="row">
                            <span>Basic Salary</span><span>$4,500</span>
                        </div>
                        <div class="row positive">
                            <span>Overtime Pay</span><span>+$450</span>
                        </div>
                        <div class="row positive">
                            <span>Allowances</span><span>+$300</span>
                        </div>
                    </div>
                    <div class="summary-col">
                        <div class="row negative">
                            <span>Deductions</span><span>-$250</span>
                        </div>
                        <div class="row highlight">
                            <span>Net Pay</span><span>$5,000</span>
                        </div>
                        <div class="row">
                            <span>Payment Date</span><span>2024-11-01</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
