<!DOCTYPE html>
<html lang="en" data-theme="modern">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
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

    {{-- Global confirm modal used by JS helper (appConfirm) for styled confirmations --}}
    <div class="modal fade" id="globalConfirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog confirm">
            <div class="modal-content confirm">
                <div class="modal-body confirm">
                    <div class="modal-container confirm">
                        <div class="confirm-icon">
                            <i class="fa-regular fa-circle-question"></i>
                        </div>
                        <div class="confirm-label" data-confirm-message>
                            Are you sure you want to proceed?
                        </div>
                    </div>
                    <div class="modal-container confirm-buttons">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" data-confirm-ok>Confirm</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

</body>
</html>