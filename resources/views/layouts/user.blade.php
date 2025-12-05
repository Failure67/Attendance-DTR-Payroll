<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        @if (!empty($title))
            {{ $title }} | Romar Construction Services
        @else
            Romar Construction Services
        @endif
    </title>
    @include('util.favicons')
    @include('util.fonts')
    @yield('styles')
    @include('user.util.styles')
    @include('user.util.scripts')
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