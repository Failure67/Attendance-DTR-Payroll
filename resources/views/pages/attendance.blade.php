@extends('layouts.app')

@section('content')

    @include('partials.menu')

    <div class="wrapper {{ $pageClass }}">

        <h1>{{ $title }}</h1>

        <div class="container {{ $pageClass }} tab">

            @include('components.search', [
                'searchClass' => 'attendance',
                'searchId' => 'attendance-search',
            ])

            <div class="crud-buttons">

                @include('components.button', [
                    'buttonType' => 'main',
                    'buttonVar' => 'add',
                    'buttonSrc' => 'attendance',
                    'buttonIcon' => '<i class="fa-solid fa-plus"></i>',
                    'buttonLabel' => 'New',
                ])

                @include('components.button', [
                    'buttonType' => 'danger',
                    'buttonVar' => 'delete',
                    'buttonSrc' => 'attendance',
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
                    'time-in',
                    'time-out',
                    'date-modified',
                ],
                'tableLabel' => [
                    'Name of employee',
                    'Time-in',
                    'Time-out',
                    'Date modified',
                ],
                'tableData' => [
                    ['CELESTIAL, ROMAR JABEZ M.', '06:00 AM', '04:00 PM', 'September 1, 2025'],
                    
                ]
            ])

        </div>

        <div class="container {{ $pageClass }} pagination">

            @include('components.pagination', [
                'paginationClass' => 'attendance',    
            ])

        </div>

    </div>

@endsection