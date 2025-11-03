<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        @if (!empty($title))
            {{ $title }} | {{ config('app.name') }} v{{ config('app.version') }}
        @else
            {{ config('app.name') }} v{{ config('app.version') }}
        @endif
    </title>
    @include('util.favicons')
    @include('util.fonts')
    @yield('styles')
    @include('util.styles')
    <noscript>
        <meta http-equiv="refresh" content="0;url={{ route('require') }}">
    </noscript>
    @include('util.scripts')
    @yield('scripts')
</head>
<body>
    
    @include('partials.header')

    <main>
        @yield('content')
    </main>

    @include('partials.footer')

    @yield('modal')

</body>
</html>