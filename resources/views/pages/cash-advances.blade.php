@extends('layouts.app')

@section('content')

    @include('partials.menu')

    <div class="wrapper cash-advances">

        <h1>Cash advances</h1>

        <div class="container cash-advances tab">

            @include('components.search', [
                'searchClass' => 'cash-advances',
                'searchId' => 'cash-advances-search',
            ])

            <div class="crud-buttons">

                @include('components.button', [
                    'buttonType' => 'main',
                    'buttonVar' => 'add',
                    'buttonSrc' => 'cash-advances',
                    'buttonIcon' => '<i class="fa-solid fa-plus"></i>',
                    'buttonLabel' => 'New',
                    'buttonModal' => true,
                    'buttonTarget' => 'cashAdvanceModal'
                ])

                @include('components.button', [
                    'buttonType' => 'secondary',
                    'buttonVar' => 'employee-balance',
                    'buttonSrc' => 'cash-advances',
                    'buttonLabel' => 'Employee Balance',
                    'buttonModal' => false,
                ])

            </div>

        </div>

        <div class="container cash-advances table-component" id="cash-advances-summary-container" style="display: none;">

            @include('components.table', [
                'tableClass' => 'cash-advances-summary-table',
                'tableCol' => [
                    'employee-name',
                    'total-advances',
                    'total-repayments',
                    'outstanding-balance',
                ],
                'tableLabel' => [
                    'Employee',
                    'Total advances',
                    'Total repayments',
                    'Outstanding balance',
                ],
                'tableData' => $cashAdvanceSummaryTableData ?? [],
                'rawColumns' => [],
            ])

        </div>

        <div class="container cash-advances table-component">

            @include('components.table', [
                'tableClass' => 'cash-advances-table',
                'tableCol' => [
                    'employee-name',
                    'type',
                    'amount',
                    'source',
                    'payroll',
                    'description',
                    'date',
                ],
                'tableLabel' => [
                    'Employee',
                    'Type',
                    'Amount',
                    'Source',
                    'Payroll',
                    'Description',
                    'Date',
                ],
                'tableData' => $cashAdvanceTableData ?? [],
                'rawColumns' => [],
            ])

        </div>

    </div>

@endsection

@section('scripts')
    @if ($errors->any())
        <script>
            $(document).ready(function() {
                const $modal = $('#cashAdvanceModal');
                if ($modal.length) {
                    $modal.modal('show');
                }
            });
        </script>
    @endif
@endsection

@section('modal')

    <div class="modal fade cash-advance-modal" id="cashAdvanceModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="cashAdvanceForm" method="POST" action="{{ route('cash-advances.store') }}">
                    @csrf

                    <div class="modal-header">
                        <div class="modal-title">
                            New cash advance entry
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">

                        @include('components.modal-error')

                        @include('components.select', [
                            'selectType' => 'select2',
                            'selectSrc' => 'cash-advances',
                            'selectVar' => 'employee',
                            'selectName' => 'user_id',
                            'selectLabel' => 'Employee',
                            'selectPlaceholder' => 'Select employee',
                            'selectData' => $employeeOptions ?? [],
                            'isShort' => false,
                        ])

                        @include('components.select', [
                            'selectType' => 'normal',
                            'selectSrc' => 'cash-advances',
                            'selectVar' => 'type',
                            'selectName' => 'type',
                            'selectLabel' => 'Entry type',
                            'selectData' => [
                                'advance' => 'Advance (issue to employee)',
                                'repayment' => 'Repayment (paid by employee)',
                            ],
                            'isShort' => false,
                        ])

                        @include('components.input-field', [
                            'inputType' => 'amount',
                            'inputSrc' => 'cash-advances',
                            'inputVar' => 'amount',
                            'inputName' => 'amount',
                            'inputLabel' => 'Amount',
                            'inputPlaceholder' => '0.00',
                            'inputInDecrement' => false,
                        ])

                        <div class="mb-3 mt-2">
                            <label for="cash-advance-description" class="form-label">Description</label>
                            <textarea name="description" id="cash-advance-description" class="form-control" rows="2" maxlength="255"></textarea>
                        </div>

                        @include('components.input-field', [
                            'inputType' => 'textarea',
                            'inputSrc' => 'cash-advance',
                            'inputVar' => 'reason',
                            'inputName' => 'reason',
                            'inputLabel' => 'Reason',
                            'inputPlaceholder' => 'Enter reason...',
                            'isRequired' => true,
                        ])

                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

@endsection
