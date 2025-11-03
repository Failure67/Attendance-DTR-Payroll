@extends('layouts.app')

@section('content')

    @include('partials.menu')

    <div class="wrapper {{ $pageClass }}">

        <h1>{{ $title }}</h1>

        <div class="container {{ $pageClass }} tab">

            @include('components.search', [
                'searchClass' => 'payroll',
                'searchId' => 'payroll-search',
            ])

            <div class="crud-buttons">

                @include('components.button', [
                    'buttonType' => 'main',
                    'buttonId' => 'payroll-add',
                    'buttonIcon' => '<i class="fa-solid fa-plus"></i>',
                    'buttonLabel' => 'New',
                ])

                @include('components.button', [
                    'buttonType' => 'danger',
                    'buttonId' => 'payroll-delete',
                    'buttonIcon' => '<i class="fa-solid fa-trash"></i>',
                    'buttonLabel' => 'Delete',
                ])

            </div>

        </div>

        <div class="container {{ $pageClass }} table-component">

            @include('components.table', [
                'tableClass' => 'payroll-table',
                'tableCol' => [
                    'employee-name',
                    'wage-type',
                    'min-wage',
                    'units-worked',
                    'gross-pay',
                    'deductions',
                    'net-pay',
                    'status',
                ],
                'tableLabel' => [
                    'Name of employee',
                    'Type of wage',
                    'Minimum wage',
                    'Units worked',
                    'Gross pay',
                    'Deductions',
                    'Net pay',
                    'Status',
                ],
                'tableData' => [
                    ['CELESTIAL, ROMAR JABEZ M.', 'Weekly', 'P600', '3 days', 'P1,800', 'P0', 'P1,800', 'Pending'],
                    ['CELESTIAL, ROMAR JABEZ M.', 'Weekly', 'P600', '3 days', 'P1,800', 'P0', 'P1,800', 'Pending'],
                    ['CELESTIAL, ROMAR JABEZ M.', 'Weekly', 'P600', '3 days', 'P1,800', 'P0', 'P1,800', 'Pending'],
                    ['CELESTIAL, ROMAR JABEZ M.', 'Weekly', 'P600', '3 days', 'P1,800', 'P0', 'P1,800', 'Pending'],
                ]
            ])

        </div>

    </div>

@endsection