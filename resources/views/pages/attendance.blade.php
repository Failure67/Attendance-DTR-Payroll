@extends('layouts.app')

@section('content')

    @include('partials.menu')

    <div class="wrapper {{ $pageClass }}">

        <h1>{{ $title }}</h1>

        <div class="container {{ $pageClass }}">

            @include('components.search', [
                'searchClass' => 'attendance',
                'searchId' => 'attendance-search',
            ])

        </div>

    </div>

@endsection