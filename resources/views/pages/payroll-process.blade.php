@extends('layouts.app')

@section('content')

    @include('partials.menu')

    <div class="wrapper payroll-process">

        <h1>Process payroll</h1>

        <div class="container payroll-process filter mb-3">
            <form method="GET" action="{{ route('payroll.process') }}" class="row g-2 align-items-end">
                <div class="col-sm-4">
                    <label for="period_start" class="form-label">Period start</label>
                    <input type="date" name="period_start" id="period_start" class="form-control" value="{{ $period_start ?? '' }}">
                </div>
                <div class="col-sm-4">
                    <label for="period_end" class="form-label">Period end</label>
                    <input type="date" name="period_end" id="period_end" class="form-control" value="{{ $period_end ?? '' }}">
                </div>
                <div class="col-sm-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary mt-auto">Preview from attendance</button>
                    <a href="{{ route('payroll') }}" class="btn btn-outline-secondary mt-auto">Back to payroll</a>
                </div>
            </form>
        </div>

        @if ($errors->any())
            <div class="container payroll-process mb-3">
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        @if(!empty($previewSummary))
            <div class="container payroll-process summary mb-3">
                <div class="row g-3">
                    <div class="col-md-3 col-6">
                        <div class="card shadow-sm border-0">
                            <div class="card-body py-2">
                                <div class="small text-muted">Period</div>
                                <div class="fw-semibold">{{ $previewSummary['period_start'] }} to {{ $previewSummary['period_end'] }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="card shadow-sm border-0">
                            <div class="card-body py-2">
                                <div class="small text-muted">Employees</div>
                                <div class="fw-semibold">{{ $previewSummary['employee_count'] }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="card shadow-sm border-0">
                            <div class="card-body py-2">
                                <div class="small text-muted">Total hours</div>
                                <div class="fw-semibold">{{ number_format($previewSummary['total_hours'], 2) }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="card shadow-sm border-0">
                            <div class="card-body py-2">
                                <div class="small text-muted">Overtime hours</div>
                                <div class="fw-semibold">{{ number_format($previewSummary['total_ot'], 2) }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if (!empty($previewRows))
            <div class="container payroll-process table-component">
                <form method="POST" action="{{ route('payroll.process.run') }}">
                    @csrf
                    <input type="hidden" name="period_start" value="{{ $previewSummary['period_start'] ?? '' }}">
                    <input type="hidden" name="period_end" value="{{ $previewSummary['period_end'] ?? '' }}">

                    @php
                        $previewTableData = collect($previewRows)->map(function ($row, $index) {
                            $includeCell = '<button type="button" class="btn btn-sm btn-outline-primary payroll-include-toggle" data-index="' . $index . '">Include</button>'
                                . '<input type="hidden" name="rows[' . $index . '][include]" value="1">'
                                . '<input type="hidden" name="rows[' . $index . '][user_id]" value="' . $row['user_id'] . '">' 
                                . '<input type="hidden" name="rows[' . $index . '][regular_hours]" value="' . $row['regular_hours'] . '">' 
                                . '<input type="hidden" name="rows[' . $index . '][overtime_hours]" value="' . $row['overtime_hours'] . '">' 
                                . '<input type="hidden" name="rows[' . $index . '][absent_days]" value="' . $row['absent_days'] . '">' 
                                . '<input type="hidden" name="rows[' . $index . '][present_days]" value="' . $row['present_days'] . '">';

                            $regular = number_format($row['regular_hours'], 2);
                            $ot = number_format($row['overtime_hours'], 2);
                            $absent = number_format($row['absent_days'], 2);
                            $present = number_format($row['present_days'], 2);

                            $selectedType = $row['last_wage_type'] ?? 'Daily';

                            $wageSelect = '<select name="rows[' . $index . '][wage_type]" class="form-select form-select-sm">'
                                . '<option value="Hourly"' . ($selectedType === 'Hourly' ? ' selected' : '') . '>Hourly</option>'
                                . '<option value="Daily"' . ($selectedType === 'Daily' ? ' selected' : '') . '>Daily</option>'
                                . '<option value="Weekly"' . ($selectedType === 'Weekly' ? ' selected' : '') . '>Weekly</option>'
                                . '<option value="Monthly"' . ($selectedType === 'Monthly' ? ' selected' : '') . '>Monthly</option>'
                                . '<option value="Piece rate"' . ($selectedType === 'Piece rate' ? ' selected' : '') . '>Piece rate</option>'
                                . '</select>';

                            $minWageValue = number_format($row['last_min_wage'], 2, '.', '');
                            $minWageInput = '<input type="number" step="0.01" min="0" name="rows[' . $index . '][min_wage]" class="form-control form-control-sm" value="' . $minWageValue . '">';

                            $caBalance = number_format($row['ca_balance'] ?? 0, 2);
                            $caBalanceCell = $caBalance;

                            $caInputName = 'rows[' . $index . '][ca_deduction]';
                            $caInput = '<input type="number" step="0.01" min="0" name="' . $caInputName . '" class="form-control form-control-sm" value="">';

                            $anomalies = $row['anomalies'] ?? [];
                            if (!empty($anomalies)) {
                                $flags = collect($anomalies)->map(function ($msg) {
                                    return '<div class="text-danger small">' . e($msg) . '</div>';
                                })->implode('');
                            } else {
                                $flags = '<span class="text-muted small">None</span>';
                            }

                            return [
                                $includeCell,
                                e($row['employee_name']),
                                $regular,
                                $ot,
                                $absent,
                                $present,
                                $wageSelect,
                                $minWageInput,
                                $caBalanceCell,
                                $caInput,
                                $flags,
                            ];
                        })->toArray();
                    @endphp

                    @include('components.table', [
                        'tableClass' => 'payroll-process-table',
                        'tableCol' => [
                            'include',
                            'employee',
                            'regular-hours',
                            'overtime-hours',
                            'absent-days',
                            'present-days',
                            'wage-type',
                            'min-wage',
                            'ca-balance',
                            'ca-deduction',
                            'flags',
                        ],
                        'tableLabel' => [
                            'Action',
                            'Employee',
                            'Regular hours',
                            'Overtime hours',
                            'Absent days',
                            'Present days',
                            'Wage type',
                            'Minimum wage',
                            'CA balance',
                            'CA to deduct',
                            'Flags',
                        ],
                        'tableData' => $previewTableData,
                        'rawColumns' => ['include', 'employee', 'wage-type', 'min-wage', 'ca-deduction', 'flags'],
                    ])

                    <div class="small text-muted mt-1">
                        Use the <strong>Action</strong> button to toggle between <strong>Include</strong> and <strong>Skip</strong> for each employee in this period.
                        Rows with issues will show details in the <strong>Flags</strong> column.
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-3">
                        <a href="{{ route('payroll') }}" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Create payrolls</button>
                    </div>
                </form>
            </div>
        @elseif(!empty($previewSummary))
            <div class="container payroll-process mt-3">
                <div class="alert alert-info mb-0">
                    No attendance records found for the selected period.
                </div>
            </div>
        @endif

    </div>

@endsection

@section('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.payroll-include-toggle').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var index = this.getAttribute('data-index');
                    var hidden = document.querySelector('input[type="hidden"][name="rows[' + index + '][include]"]');
                    if (!hidden) return;

                    var current = hidden.value === '0' ? '0' : '1';
                    if (current === '1') {
                        hidden.value = '0';
                        this.classList.remove('btn-outline-primary');
                        this.classList.add('btn-outline-secondary');
                        this.textContent = 'Skip';
                    } else {
                        hidden.value = '1';
                        this.classList.remove('btn-outline-secondary');
                        this.classList.add('btn-outline-primary');
                        this.textContent = 'Include';
                    }
                });
            });
        });
    </script>
@endsection
