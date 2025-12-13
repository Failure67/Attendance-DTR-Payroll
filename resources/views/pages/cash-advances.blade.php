@extends('layouts.app')

@section('content')

    @include('partials.menu')

    <div class="wrapper cash-advances" data-archived="{{ ($showArchived ?? false) ? '1' : '0' }}">

        <div class="page-header">
            <div class="page-title">
                <span class="page-icon"><i class="fa-solid fa-money-bill-wave"></i></span>
                <div class="page-title-text">
                    <h1>Cash Advance</h1>
                    <p>Manage employee cash advances and repayments</p>
                </div>
            </div>
        </div>

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

                @include('components.button', [
                    'buttonType' => 'secondary',
                    'buttonVar' => 'view',
                    'buttonSrc' => 'cash-advances',
                    'buttonIcon' => '<i class="fa-solid fa-list-check"></i>',
                    'buttonLabel' => 'View requests',
                    'buttonModal' => false,
                ])

                @include('components.button', [
                    'buttonType' => 'danger',
                    'buttonVar' => 'delete',
                    'buttonSrc' => 'cash-advances',
                    'buttonIcon' => '<i class="fa-solid fa-clock-rotate-left"></i>',
                    'buttonLabel' => ($showArchived ?? false) ? 'Back to cash advances' : 'View archived',
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

            <div class="cash-advances-table-views">

                <div class="cash-advances-view cash-advances-view-ledger">

                    @php
                        $isArchivedView = $showArchived ?? false;
                        $ledgerTableCols = [
                            'employee-name',
                            'type',
                            'amount',
                            'source',
                            'payroll',
                            'description',
                            'date',
                        ];
                        $ledgerTableLabels = [
                            'Employee',
                            'Type',
                            'Amount',
                            'Source',
                            'Payroll',
                            'Description',
                            'Date',
                        ];
                        $ledgerRawColumns = ['employee-name'];

                        if ($isArchivedView) {
                            $ledgerTableCols[] = 'actions';
                            $ledgerTableLabels[] = 'Actions';
                            $ledgerRawColumns[] = 'actions';
                        }
                    @endphp

                    @include('components.table', [
                        'tableClass' => 'cash-advances-table',
                        'tableCol' => $ledgerTableCols,
                        'tableLabel' => $ledgerTableLabels,
                        'tableData' => $cashAdvanceTableData ?? [],
                        'rawColumns' => $ledgerRawColumns,
                    ])
                </div>

                <div class="cash-advances-view cash-advances-view-requests" style="display: none;">
                    @php
                        $caRequestTableData = $cashAdvanceRequestsTableData ?? [];
                    @endphp

                    @include('components.table', [
                        'tableClass' => 'cash-advance-requests-table',
                        'tableCol' => [
                            'employee-name',
                            'amount',
                            'status',
                            'created-at',
                            'actions',
                        ],
                        'tableLabel' => [
                            'Employee',
                            'Amount',
                            'Status',
                            'Requested on',
                            'Actions',
                        ],
                        'tableData' => $caRequestTableData,
                        'rawColumns' => ['actions'],
                    ])

                    @if(isset($cashAdvanceRequests) && ($cashAdvanceRequests instanceof \Illuminate\Pagination\LengthAwarePaginator || $cashAdvanceRequests instanceof \Illuminate\Pagination\Paginator))
                        <div class="mt-3 d-flex justify-content-end">
                            {{ $cashAdvanceRequests->onEachSide(1)->links('pagination::bootstrap-4') }}
                        </div>
                    @endif

                </div>

            </div>

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

                        {{-- 
                        <div class="mb-3 mt-2">
                            <label for="cash-advance-description" class="form-label">Description</label>
                            <textarea name="description" id="cash-advance-description" class="form-control" rows="2" maxlength="255"></textarea>
                        </div>
                        --}}

                        @include('components.input-field', [
                            'inputType' => 'textarea',
                            'inputSrc' => 'description',
                            'inputVar' => 'reason',
                            'inputName' => 'cash-advance',
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

    {{-- delete / archive confirm modal for cash advances --}}
    @include('components.confirm', [
        'confirmClass' => 'delete-cash-advances',
        'confirmModalId' => 'deleteCashAdvancesModal',
        'confirmType' => 'archive',
        'confirmRoute' => 'cash-advances.delete',
        'confirmRouteParams' => ['id' => 0],
        'confirmLabel' => 'archive',
        'confirmButtons' =>
            view('components.button', [
                'buttonType' => 'secondary',
                'buttonVar' => 'cancel-delete',
                'buttonSrc' => 'cash-advances',
                'buttonLabel' => 'Cancel',
                'isModalClose' => true,
            ])->render() .
            view('components.button', [
                'buttonType' => 'danger',
                'buttonVar' => 'confirm-delete',
                'buttonSrc' => 'cash-advances',
                'buttonLabel' => 'Delete',
                'isSubmit' => false,
            ])
    ])

@endsection
