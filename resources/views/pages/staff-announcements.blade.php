@extends('layouts.app')

@section('content')

    @include('partials.menu')

    <div class="wrapper staff-announcements">

        <div class="page-header">
            <div class="page-title">
                <span class="page-icon"><i class="fa-solid fa-bullhorn"></i></span>
                <div class="page-title-text">
                    <h1>Announcements</h1>
                    <p>Read-only list of company-wide announcements</p>
                </div>
            </div>
        </div>

        <div class="container announcements table-component">

            @php
                $tableData = $announcementTableData ?? [];
            @endphp

            @include('components.table', [
                'tableClass' => 'staff-announcements-table',
                'tableCol' => [
                    'title',
                    'effective',
                ],
                'tableLabel' => [
                    'Title',
                    'Effective',
                ],
                'tableData' => $tableData,
                'rawColumns' => ['title'],
            ])

            @if($announcements instanceof \Illuminate\Pagination\LengthAwarePaginator || $announcements instanceof \Illuminate\Pagination\Paginator)
                <div class="mt-3 d-flex justify-content-end">
                    {{ $announcements->onEachSide(1)->links('pagination::bootstrap-4') }}
                </div>
            @endif

        </div>

    </div>

@endsection

@section('modal')
    {{-- Staff announcement detail modal (preview only) --}}
    <div class="modal fade" id="staffAnnouncementModal" tabindex="-1" aria-labelledby="staffAnnouncementModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="staffAnnouncementModalLabel">Announcement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-1 small text-muted" id="staff-announcement-period"></div>
                    <div class="fw-semibold mb-2" id="staff-announcement-title"></div>
                    <div id="staff-announcement-body"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (!window.jQuery) {
                return;
            }

            const $ = window.jQuery;

            // Ensure staff announcement rows always look clickable
            document.querySelectorAll('.staff-announcements-table tbody tr').forEach(function (row) {
                row.style.cursor = 'pointer';
            });

            // Ensure the staff announcement modal close button always shows an X
            const staffCloseBtn = document.querySelector('#staffAnnouncementModal .modal-header .btn-close');
            if (staffCloseBtn && !staffCloseBtn.textContent.trim()) {
                staffCloseBtn.textContent = '×';
                staffCloseBtn.style.opacity = '1';
            }

            $(document).on('click', '.staff-announcements-table tbody tr', function () {
                const $preview = $(this).find('.announcement-preview').first();
                if (!$preview.length) return;

                const title = $preview.data('title') || '';
                const body = $preview.data('body') || '';
                const period = $preview.data('period') || '';

                $('#staff-announcement-title').text(title);
                $('#staff-announcement-body').text(body);
                $('#staff-announcement-period').text(period);

                const modalEl = document.getElementById('staffAnnouncementModal');
                if (!modalEl) return;

                const modal = new bootstrap.Modal(modalEl);
                modal.show();
            });
        });
    </script>
@endsection
