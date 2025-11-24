<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        @if (!empty($title))
            {{ $title }} | {{ config('app.name') }}
        @else
            {{ config('app.name') }}
        @endif
    </title>
    @include('util.favicons')
    @include('util.fonts')
    @yield('styles')
    <link rel="stylesheet" href="{{ asset('assets/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/fontawesome/css/fontawesome.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/login.css') }}">
    @yield('extra-styles')
    <noscript>
        <meta http-equiv="refresh" content="0;url={{ route('require') }}">
    </noscript>
</head>
<body class="auth-body">
    <div class="auth-container">
        @yield('content')
    </div>

    <script src="{{ asset('assets/js/popper.min.js') }}"></script>
    <script src="{{ asset('assets/bootstrap/js/bootstrap.min.js') }}"></script>
    <script src="{{ asset('assets/fontawesome/js/all.min.js') }}"></script>
    @yield('scripts')
</body>
</html>