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
    @include('components.titlebar')
    
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

    <div class="modal fade" id="statCardDetailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" data-stat-modal-title>Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <div class="small text-muted">Current value</div>
                        <div class="fw-semibold" data-stat-modal-value>—</div>
                    </div>

                    <div class="mb-3">
                        <div class="small text-muted">How this was calculated</div>
                        <div data-stat-modal-details>—</div>
                    </div>

                    <div class="mb-0">
                        <div class="small text-muted">Data source</div>
                        <div class="small" data-stat-modal-source>—</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="#" class="btn btn-outline-primary" target="_blank" rel="noopener" data-stat-modal-link style="display:none;">Open page</a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

</body>
</html>