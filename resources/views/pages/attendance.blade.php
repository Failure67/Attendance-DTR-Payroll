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

            <span class="crud-buttons">

                @include('components.button', [
                    'buttonType' => 'main',
                    'buttonId' => 'attendance-add',
                    'buttonIcon' => '<i class="fa-solid fa-plus"></i>',
                    'buttonLabel' => 'New',
                ])

                @include('components.button', [
                    'buttonType' => 'danger',
                    'buttonId' => 'attendance-delete',
                    'buttonIcon' => '<i class="fa-solid fa-trash"></i>',
                    'buttonLabel' => 'Delete',
                ])

            </span>

        </div>
        
        <div class="container {{ $pageClass }} table">
            
            

        </div>

    </div>

@endsection