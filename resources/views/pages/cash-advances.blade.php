@extends('layouts.app')

@section('content')

    @include('partials.menu')

    <div class="wrapper cash-advances" data-archived="{{ ($showArchived ?? false) ? '1' : '0' }}" data-requests-only="{{ ($requestsOnly ?? false) ? '1' : '0' }}">

        <div class="page-header">
            <div class="page-title">
                <span class="page-icon"><i class="fa-solid fa-money-bill-wave"></i></span>
                <div class="page-title-text">
                    <h1>Cash Advance</h1>
                    <p>Manage employee cash advances and repayments</p>
                </div>
            </div>
        </div>

    {{-- Details modal for cash advance requests (view-only) --}}
    <div class="modal fade" id="cashAdvanceRequestDetailsModal" tabindex="-1" aria-labelledby="cashAdvanceRequestDetailsLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="cashAdvanceRequestDetailsLabel">Cash advance request details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Employee</dt>
                        <dd class="col-sm-8" id="ca-detail-employee">—</dd>

                        <dt class="col-sm-4">Amount</dt>
                        <dd class="col-sm-8" id="ca-detail-amount">—</dd>

                        <dt class="col-sm-4">Status</dt>
                        <dd class="col-sm-8" id="ca-detail-status">—</dd>

                        <dt class="col-sm-4">Requested on</dt>
                        <dd class="col-sm-8" id="ca-detail-requested">—</dd>

                        <dt class="col-sm-4">Reason</dt>
                        <dd class="col-sm-8" id="ca-detail-reason">—</dd>
                    </dl>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    @php
        $currentRoleLocal = strtolower($currentRole ?? '');
    @endphp

    @if ($currentRoleLocal === 'supervisor')
    <div class="modal fade cash-advance-modal" id="cashAdvanceRequestModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="cashAdvanceRequestForm" method="POST" action="{{ route('cash-advance-requests.store-supervisor') }}">
                    @csrf

                    <div class="modal-header">
                        <div class="modal-title">
                            New cash advance request
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">

                        @include('components.modal-error')

                        @include('components.select', [
                            'selectType' => 'select2',
                            'selectSrc' => 'cash-advance-requests',
                            'selectVar' => 'employee-request',
                            'selectName' => 'user_id',
                            'selectLabel' => 'Employee',
                            'selectPlaceholder' => 'Select employee',
                            'selectData' => $employeeOptions ?? [],
                            'isShort' => false,
                        ])

                        @include('components.input-field', [
                            'inputType' => 'amount',
                            'inputSrc' => 'cash-advance-requests',
                            'inputVar' => 'amount-request',
                            'inputName' => 'amount',
                            'inputLabel' => 'Amount',
                            'inputPlaceholder' => '0.00',
                            'inputInDecrement' => false,
                            'isRequired' => true,
                        ])

                        @include('components.input-field', [
                            'inputType' => 'textarea',
                            'inputSrc' => 'cash-advance-requests',
                            'inputVar' => 'reason-request',
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
    @endif

        <div class="container cash-advances tab">

            @include('components.search', [
                'searchClass' => 'cash-advances',
                'searchId' => 'cash-advances-search',
            ])

            <div class="crud-buttons">

                @php
                    $canManageLedgerLocal = $canManageLedger ?? false;
                    $currentRoleLocal = strtolower($currentRole ?? '');
                @endphp

                @if ($canManageLedgerLocal)
                    @php
                        $isSupervisor = $currentRoleLocal === 'supervisor';
                        $buttonLabel = 'NEW';
                        $formAction = $isSupervisor ? route('cash-advance-requests.store-supervisor') : route('cash-advances.store');
                    @endphp

                    @include('components.button', [
                        'buttonType' => 'main',
                        'buttonVar' => 'add',
                        'buttonSrc' => 'cash-advances',
                        'buttonIcon' => '<i class="fa-solid fa-plus"></i>',
                        'buttonLabel' => $buttonLabel,
                        'buttonModal' => true,
                        'buttonTarget' => 'cashAdvanceModal'
                    ])

                    <div class="dropdown">
                        @include('components.button', [
                            'buttonType' => 'secondary',
                            'buttonVar' => 'view',
                            'buttonSrc' => 'cash-advances',
                            'buttonIcon' => '<i class="fa-solid fa-list-check"></i>',
                            'buttonLabel' => 'View',
                            'btnAttribute' => 'data-bs-toggle="dropdown" aria-expanded="false"',
                        ])
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <button type="button" class="dropdown-item" id="view-transactions-cash-advances">Transactions</button>
                            </li>
                            <li>
                                <button type="button" class="dropdown-item" id="view-balance-cash-advances">Employee Balance</button>
                            </li>
                            <li>
                                <button type="button" class="dropdown-item" id="view-requests-cash-advances">Requests</button>
                            </li>
                        </ul>
                    </div>

                    @include('components.button', [
                        'buttonType' => 'danger',
                        'buttonVar' => 'delete',
                        'buttonSrc' => 'cash-advances',
                        'buttonIcon' => '<i class="fa-solid fa-clock-rotate-left"></i>',
                        'buttonLabel' => ($showArchived ?? false) ? 'Back to cash advances' : 'View archived',
                        'buttonModal' => false,
                    ])

                @else
                    {{-- HR and Manager: No View button, just show the Requests table --}}
                    @include('components.button', [
                        'buttonType' => 'secondary',
                        'buttonVar' => 'view-requests',
                        'buttonSrc' => 'cash-advances',
                        'buttonIcon' => '<i class="fa-solid fa-list-check"></i>',
                        'buttonLabel' => 'Requests',
                        'btnAttribute' => 'id="view-requests-cash-advances" style="visibility: hidden;"',
                    ])
                @endif

            </div>

        </div>

        @if ($canManageLedger ?? false)
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
        @endif

        <div class="container cash-advances table-component">

            <div class="cash-advances-table-views">

                @if ($canManageLedger ?? false)
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
                @endif

                @php
                    $caRequestTableData = $cashAdvanceRequestsTableData ?? [];
                @endphp

                <div class="cash-advances-view cash-advances-view-requests" @if($canManageLedger ?? false) style="display: none;" @endif>

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
                        'rawColumns' => ['employee-name', 'actions'],
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
                @if ($errors->has('reason'))
                    const $modal = $('#cashAdvanceRequestModal');
                @else
                    const $modal = $('#cashAdvanceModal');
                @endif
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
                <form id="cashAdvanceForm" method="POST" action="{{ $formAction ?? route('cash-advances.store') }}">
                    @csrf

                    <div class="modal-header">
                        <div class="modal-title">
                            {{ ($isSupervisor ?? false) ? 'New Cash Advance Request' : 'New Cash Advance Entry' }}
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
                        --}}

                        @if($isSupervisor ?? false)
                            @include('components.input-field', [
                                'inputType' => 'textarea',
                                'inputSrc' => 'cash-advance-requests',
                                'inputVar' => 'reason',
                                'inputName' => 'reason',
                                'inputLabel' => 'Reason',
                                'inputPlaceholder' => 'Enter reason for cash advance...',
                                'isRequired' => true,
                            ])
                        @else
                            @include('components.input-field', [
                                'inputType' => 'textarea',
                                'inputSrc' => 'description',
                                'inputVar' => 'reason',
                                'inputName' => 'description',
                                'inputLabel' => 'Notes',
                                'inputPlaceholder' => 'Enter notes...',
                                'isRequired' => false,
                            ])
                        @endif

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
