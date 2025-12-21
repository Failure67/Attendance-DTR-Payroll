@extends('layouts.app')

@section('content')

    @include('partials.menu')

    <div class="wrapper {{ $pageClass }}">

        <div class="page-header">
            <div class="page-title">
                <span class="page-icon"><i class="fa-solid fa-file-invoice-dollar"></i></span>
                <div class="page-title-text">
                    <h1>{{ $title }}</h1>
                    <p>Agency: {{ $batch->agency }} | Month: {{ $batch->period_month ? $batch->period_month->format('Y-m') : '' }} | Status: {{ $batch->status }}</p>
                </div>
            </div>
        </div>

        @if (session('success') || session('error'))
            <div class="container {{ $pageClass }} mb-3">
                @if (session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                @if (session('error'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif
            </div>
        @endif

        <div class="container {{ $pageClass }} mb-3">
            <div class="card">
                <div class="card-body d-flex flex-wrap gap-3 justify-content-between">
                    <div>
                        <div class="small text-muted">EE total</div>
                        <div class="fw-semibold">{{ number_format((float) $batch->employee_total, 2) }}</div>
                    </div>
                    <div>
                        <div class="small text-muted">ER total</div>
                        <div class="fw-semibold">{{ number_format((float) $batch->employer_total, 2) }}</div>
                    </div>
                    <div>
                        <div class="small text-muted">Grand total</div>
                        <div class="fw-semibold">{{ number_format((float) $batch->grand_total, 2) }}</div>
                    </div>
                    <div>
                        <a href="{{ route('remittances') }}" class="btn btn-outline-secondary btn-sm">Back</a>
                        <a href="{{ route('remittances.export', $batch) }}" class="btn btn-outline-success btn-sm">Export CSV</a>
                    </div>
                </div>
            </div>
        </div>

        @php
            $currentRole = strtolower(auth()->user()->role ?? '');
            $canManage = in_array($currentRole, ['admin', 'superadmin', 'hr', 'accounting'], true);
        @endphp

        @if($canManage)
            <div class="container {{ $pageClass }} mb-3">
                <div class="card">
                    <div class="card-body">
                        <div class="fw-semibold mb-2">Update status / payment</div>

                        <form method="POST" action="{{ route('remittances.update', $batch) }}" enctype="multipart/form-data" class="row g-2 align-items-end">
                            @csrf
                            @method('PUT')

                            <div class="col-md-3">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select" required>
                                    @foreach(['draft' => 'Draft', 'posted' => 'Posted', 'paid' => 'Paid', 'submitted' => 'Submitted'] as $value => $label)
                                        <option value="{{ $value }}" {{ ($batch->status ?? 'draft') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-5">
                                <label class="form-label">Payment reference</label>
                                <input type="text" name="payment_reference" class="form-control" value="{{ $batch->payment_reference ?? '' }}" placeholder="Reference no. (optional)">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Proof (PDF/JPG/PNG, max 5MB)</label>
                                <input type="file" name="proof" class="form-control" accept=".pdf,image/*">
                            </div>

                            <div class="col-12 d-flex flex-wrap gap-2 justify-content-between mt-2">
                                <div class="small text-muted">
                                    Paid at: {{ $batch->paid_at ? $batch->paid_at->format('Y-m-d H:i') : '—' }}
                                    | Submitted at: {{ $batch->submitted_at ? $batch->submitted_at->format('Y-m-d H:i') : '—' }}
                                    @if($batch->proof_path)
                                        | Proof: <a href="{{ asset($batch->proof_path) }}" target="_blank" rel="noopener">View</a>
                                    @endif
                                </div>
                                <button type="submit" class="btn btn-outline-primary btn-sm">Save</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif

        @php
            $items = $items ?? null;
            $itemTableData = $itemTableData ?? [];
        @endphp

        <div class="container {{ $pageClass }} table-component">
            @include('components.table', [
                'tableClass' => 'remittance-items-table',
                'tableCol' => ['employee', 'membership', 'missing', 'employee_amount', 'employer_amount', 'total_amount'],
                'tableLabel' => ['Employee', 'Membership no.', 'Missing?', 'EE amount', 'ER amount', 'Total'],
                'tableData' => $itemTableData,
                'rawColumns' => [],
            ])

            @if(isset($items) && ($items instanceof \Illuminate\Pagination\LengthAwarePaginator || $items instanceof \Illuminate\Pagination\Paginator))
                <div class="mt-3 d-flex justify-content-end">
                    {{ $items->onEachSide(1)->links('pagination::bootstrap-4') }}
                </div>
            @endif
        </div>

    </div>

@endsection
