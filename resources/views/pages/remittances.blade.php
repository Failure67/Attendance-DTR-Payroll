@extends('layouts.app')

@section('content')

    @include('partials.menu')

    <div class="wrapper {{ $pageClass }}">

        <div class="page-header">
            <div class="page-title">
                <span class="page-icon"><i class="fa-solid fa-file-invoice-dollar"></i></span>
                <div class="page-title-text">
                    <h1>{{ $title }}</h1>
                    <p>Generate and track monthly government remittances</p>
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

        @php
            $currentRole = strtolower(auth()->user()->role ?? '');
            $canGenerate = in_array($currentRole, ['admin', 'superadmin', 'hr', 'accounting'], true);
            $tableData = $tableData ?? [];
            $batches = $batches ?? null;
            $agencies = $agencies ?? [];
            $statuses = $statuses ?? [];
            $filters = $filters ?? [];
        @endphp

        <div class="container {{ $pageClass }} mb-2 mt-2">
            <form method="GET" action="{{ route('remittances') }}" class="row g-3 align-items-end remittances-filter-row">
                <div class="col-12 col-md-6 col-lg">
                    <label for="remittances_filter_month" class="input-label mb-1">Month</label>
                    <input type="month" name="month" id="remittances_filter_month" value="{{ $filters['month'] ?? '' }}" class="date-field">
                </div>

                <div class="col-12 col-md-6 col-lg">
                    <label for="remittances_filter_agency" class="input-label mb-1">Agency</label>
                    <select name="agency" id="remittances_filter_agency" class="select w-100">
                        <option value="">All</option>
                        @foreach($agencies as $agency)
                            <option value="{{ $agency }}" {{ ($filters['agency'] ?? '') === $agency ? 'selected' : '' }}>{{ $agency }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12 col-md-6 col-lg">
                    <label for="remittances_filter_status" class="input-label mb-1">Status</label>
                    <select name="status" id="remittances_filter_status" class="select w-100">
                        <option value="">All</option>
                        @foreach($statuses as $status)
                            <option value="{{ $status }}" {{ ($filters['status'] ?? '') === $status ? 'selected' : '' }}>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12 col-md-6 col-lg-auto d-flex align-items-end justify-content-lg-end">
                    <button type="submit" class="button main filter">Filter</button>
                </div>

                <div class="col-12 col-md-6 col-lg-auto d-flex align-items-end justify-content-lg-end">
                    <a href="{{ route('remittances') }}" class="button secondary filter">Reset</a>
                </div>
            </form>
        </div>

        @if($canGenerate)
            <div class="container {{ $pageClass }} mb-3">
                <div class="card">
                    <div class="card-body">
                        <div class="fw-semibold mb-2">Generate batch from Released payrolls</div>

                        <form method="POST" action="{{ route('remittances.generate') }}" class="row g-3 align-items-end remittances-generate-row">
                            @csrf

                            <div class="col-12 col-md-6 col-lg">
                                <label for="remittances_generate_month" class="input-label mb-1">Month</label>
                                <input type="month" name="month" id="remittances_generate_month" class="date-field" required>
                            </div>

                            <div class="col-12 col-md-6 col-lg">
                                <label for="remittances_generate_agency" class="input-label mb-1">Agency</label>
                                <select name="agency" id="remittances_generate_agency" class="select w-100" required>
                                    @foreach($agencies as $agency)
                                        <option value="{{ $agency }}">{{ $agency }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-12 col-md-6 col-lg-auto d-flex align-items-end justify-content-lg-end">
                                <button type="submit" class="button main filter">Generate</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif

        <div class="container {{ $pageClass }} table-component">
            @include('components.table', [
                'tableClass' => 'remittances-table',
                'tableCol' => ['month', 'agency', 'status', 'employee_total', 'employer_total', 'grand_total', 'actions'],
                'tableLabel' => ['Month', 'Agency', 'Status', 'EE total', 'ER total', 'Total', 'Actions'],
                'tableData' => $tableData,
                'rawColumns' => ['actions'],
            ])

            <div class="mt-2">
                @include('components.pagination', [
                    'paginationClass' => 'remittances',
                    'paginator' => $batches,
                ])
            </div>
        </div>

    </div>

@endsection
