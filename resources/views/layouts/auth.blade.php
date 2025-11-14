<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>

    </title>
    @include('util.favicons')
    @include('util.fonts')
    @yield('styles')
    @include('auth.util.styles')
    <noscript>
        <meta http-equiv="refresh" content="0;url={{ route('require') }}">
    </noscript>
</head>
<body>
    


</body>
</html>