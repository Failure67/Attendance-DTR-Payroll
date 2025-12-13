@extends('layouts.app')

@section('content')

    @include('partials.menu')

    <div class="wrapper announcements">

        <div class="page-header">
            <div class="page-title">
                <span class="page-icon"><i class="fa-solid fa-bullhorn"></i></span>
                <div class="page-title-text">
                    <h1>Announcements</h1>
                    <p>Post and manage company-wide announcements for employees</p>
                </div>
            </div>
        </div>

        <div class="container announcements mb-3">
            <div class="crew-card announcements-card">
                @php
                    $editing = $editingAnnouncement ?? null;
                @endphp

                <form method="POST" action="{{ $editing ? route('announcements.update', ['id' => $editing->id]) : route('announcements.store') }}" class="announcements-form">
                    @csrf
                    @if($editing)
                        @method('PUT')
                    @endif

                    <div class="announcements-form-group">
                        <label for="title" class="announcements-label">Title</label>
                        <input type="text" name="title" id="title" class="form-control" maxlength="255" value="{{ old('title', $editing?->title) }}" placeholder="Enter announcement title" required>
                    </div>

                    <div class="announcements-form-dates">
                        <div class="announcements-form-field">
                            <label for="starts_at" class="announcements-label">Starts at</label>
                            <input type="date" name="starts_at" id="starts_at" class="form-control" value="{{ old('starts_at', $editing?->starts_at?->format('Y-m-d')) }}" placeholder="Select date">
                            <div class="announcements-form-helper">Leave blank to start immediately.</div>
                        </div>

                        <div class="announcements-form-field">
                            <label for="ends_at" class="announcements-label">Ends at</label>
                            <input type="date" name="ends_at" id="ends_at" class="form-control" value="{{ old('ends_at', $editing?->ends_at?->format('Y-m-d')) }}" placeholder="Select date">
                            <div class="announcements-form-helper">Leave blank for no end date.</div>
                        </div>
                    </div>

                    <div class="announcements-form-group">
                        <label for="body" class="announcements-label">Message</label>
                        <textarea name="body" id="body" class="form-control" rows="4" maxlength="5000" placeholder="Enter your announcement message" required>{{ old('body', $editing?->body) }}</textarea>
                    </div>

                    <div class="announcements-form-actions">
                        @if($editing)
                            <a href="{{ route('announcements') }}" class="btn btn-outline-secondary me-2">Cancel</a>
                        @endif
                        <button type="submit" class="btn btn-primary">{{ $editing ? 'Update announcement' : 'Publish announcement' }}</button>
                    </div>

                    @if ($errors->any())
                        <div class="mt-2">
                            <div class="alert alert-danger mb-0">
                                <ul class="mb-0">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    @endif

                    @if (session('success'))
                        <div class="mt-2">
                            <div class="alert alert-success mb-0">
                                {{ session('success') }}
                            </div>
                        </div>
                    @endif
                </form>
            </div>
        </div>

        <div class="container announcements table-component">

            @php
                $tableData = $announcementTableData ?? [];
            @endphp

            @include('components.table', [
                'tableClass' => 'announcements-table',
                'tableCol' => [
                    'title',
                    'starts-at',
                    'ends-at',
                    'actions',
                ],
                'tableLabel' => [
                    'Title',
                    'Starts at',
                    'Ends at',
                    'Actions',
                ],
                'tableData' => $tableData,
                'rawColumns' => ['title', 'actions'],
            ])

            @if($announcements instanceof \Illuminate\Pagination\LengthAwarePaginator || $announcements instanceof \Illuminate\Pagination\Paginator)
                <div class="mt-3 d-flex justify-content-end">
                    {{ $announcements->onEachSide(1)->links('pagination::bootstrap-4') }}
                </div>
            @endif

        </div>

    </div>

@endsection

@section('scripts')
    <script>
        $(document).ready(function () {
            $(document).on('click', '.announcements-table tbody tr', function (e) {
                // Ignore clicks on buttons/links inside the row (e.g. Edit, Delete)
                if ($(e.target).closest('a, button, input, textarea, select, label, form').length) {
                    return;
                }

                const $preview = $(this).find('.announcement-admin-preview').first();
                if (!$preview.length) {
                    return;
                }

                const editUrl = $preview.data('edit-url');
                if (editUrl) {
                    window.location.href = editUrl;
                }
            });
        });
    </script>
@endsection
