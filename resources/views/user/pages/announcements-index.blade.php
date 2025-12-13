@extends('layouts.user')

@section('content')

    <div class="wrapper employee announcements-index">

        <div class="container employee header">

            <div class="content info">

                <div class="name">

                    <div class="profile-picture">
                        @if($user->profile_picture && file_exists(public_path('uploads/profiles/' . $user->profile_picture)))
                            <img src="{{ asset('uploads/profiles/' . $user->profile_picture) }}" alt="Profile Picture" width="120">
                        @else
                            <img src="{{ asset('assets/img/defaults/user_image.webp') }}" alt="Profile Picture" width="120">
                        @endif
                    </div>

                    <div class="name-info">

                        <div class="name-container">
                            {{ $user->full_name ?? $user->username }}
                        </div>

                        <div class="id-number">
                            <span class="icon">
                                <i class="fa-solid fa-id-badge"></i>
                            </span>
                            <div class="label">
                                RMCS-{{ str_pad($user->id, 4, '0', STR_PAD_LEFT) }}
                            </div>
                        </div>

                    </div>

                </div>

            </div>

        </div>

        <div class="container employee">

            <div class="content announcements-card">
                <div class="announcements-header d-flex justify-content-between align-items-center mb-2">
                    <div class="title">Announcements</div>
                </div>

                @php
                    $tableData = $announcementTableData ?? [];
                @endphp

                @include('user.components.table', [
                    'tableClass' => 'worker-announcements-table',
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

    </div>

    {{-- Announcement detail modal --}}
    <div class="modal fade" id="workerAnnouncementModal" tabindex="-1" aria-labelledby="workerAnnouncementModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="workerAnnouncementModalLabel">Announcement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-1 small text-muted" id="worker-announcement-period"></div>
                    <div class="fw-semibold mb-2" id="worker-announcement-title"></div>
                    <div id="worker-announcement-body"></div>
                </div>
            </div>
        </div>
    </div>

@endsection
