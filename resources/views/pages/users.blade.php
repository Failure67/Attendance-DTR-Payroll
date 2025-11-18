@extends('layouts.app')

@section('content')

    @include('partials.menu')

    <div class="wrapper {{ $pageClass }}">

        <h1>{{ $title }}</h1>

        <div class="container {{ $pageClass }} tab">

            @include('components.search', [
                'searchClass' => 'users',
                'searchId' => 'users-search',    
            ])

            <div class="crud-buttons">

                @include('components.button', [
                    'buttonType' => 'main',
                    'buttonVar' => 'add',
                    'buttonSrc' => 'users',
                    'buttonIcon' => '<i class="fa-solid fa-plus"></i>',
                    'buttonLabel' => 'New',
                ])

                @include('components.button', [
                    'buttonType' => 'danger',
                    'buttonVar' => 'delete',
                    'buttonSrc' => 'users',
                    'buttonIcon' => '<i class="fa-solid fa-trash"></i>',
                    'buttonLabel' => 'Delete',
                ])

            </div>

        </div>

    </div>

@endsection