@extends('layouts.app')

@section('content')

    @include('partials.menu')

    <div class="wrapper {{ $pageClass }}">

        <div class="page-header">
            <div class="page-title">
                <span class="page-icon"><i class="fa-solid fa-database"></i></span>
                <div class="page-title-text">
                    <h1>{{ $title }}</h1>
                    <p>Manage database backups and restore points</p>
                </div>
            </div>
        </div>

        @if (session('backup_success'))
            <div class="alert alert-success backup-alert">
                {{ session('backup_success') }}
            </div>
        @endif
        @if (session('backup_error'))
            <div class="alert alert-danger backup-alert">
                {{ session('backup_error') }}
            </div>
        @endif

        @php
            $summary = $summary ?? [];
            $lastBackup = $summary['last_backup'] ?? ['label' => 'No backups yet', 'status' => 'No completed backups have been recorded.'];
            $nextBackup = $summary['next_backup'] ?? ['label' => 'Not scheduled', 'status' => 'Configure your backup schedule on the server.'];
            $storage = $summary['storage'] ?? ['used' => null, 'limit' => null, 'percentage' => null];

            $storageLabel = $storage['used'] !== null && $storage['limit'] !== null
                ? ($storage['used'] . ' / ' . $storage['limit'])
                : 'Not available';
            $storageSubLabel = $storage['percentage'] !== null
                ? ($storage['percentage'] . '% of configured limit')
                : 'Storage usage information is not configured';
        @endphp

        <div class="container {{ $pageClass }} summary mb-3">

            @include('components.dashboard-count', [
                'countClass' => 'backup-last',
                'countLabel' => 'Last backup',
                'countSublabel' => $lastBackup['status'] ?? '',
                'countIcon' => '<i class="fa-solid fa-circle-check"></i>',
                'countValue' => $lastBackup['label'] ?? '—',
            ])

            @include('components.dashboard-count', [
                'countClass' => 'backup-next',
                'countLabel' => 'Next scheduled',
                'countSublabel' => $nextBackup['status'] ?? '',
                'countIcon' => '<i class="fa-solid fa-clock-rotate-left"></i>',
                'countValue' => $nextBackup['label'] ?? '—',
            ])

            @include('components.dashboard-count', [
                'countClass' => 'backup-storage',
                'countLabel' => 'Storage used',
                'countSublabel' => $storageSubLabel,
                'countIcon' => '<i class="fa-solid fa-hard-drive"></i>',
                'countValue' => $storageLabel,
            ])

        </div>

        <div class="container {{ $pageClass }} mt-3">
            <div class="backup-section">

                <div class="backup-section-header">
                    <div>
                        <div class="backup-section-title">Database backup</div>
                        <div class="backup-section-subtitle">Create and manage local and cloud backup files</div>
                    </div>
                </div>

                <div class="backup-section-grid">

                    <div class="dashboard-card backup-card backup-card-local">
                        <div class="dashboard-card-container">
                            <span class="dashboard-card-title">Local database backup</span>
                        </div>

                        <div class="backup-card-body">
                            <div class="backup-card-row">
                                <span class="label">Backup scope</span>
                                <span class="value">Full database</span>
                            </div>
                            <div class="backup-card-row">
                                <span class="label">Format</span>
                                <span class="value">SQL dump (compressed)</span>
                            </div>
                            <div class="backup-card-row">
                                <span class="label">Recommended</span>
                                <span class="value">Before major changes</span>
                            </div>
                        </div>

                        <div class="backup-card-actions">
                            <form method="POST" action="{{ route('backup.create') }}">
                                @csrf
                                @include('components.button', [
                                    'buttonType' => 'main',
                                    'buttonVar' => 'backup-create-full',
                                    'buttonSrc' => 'backup',
                                    'buttonIcon' => '<i class="fa-solid fa-download"></i>',
                                    'buttonLabel' => 'Create full backup',
                                    'isSubmit' => true,
                                ])
                            </form>
                        </div>
                    </div>

                    <div class="dashboard-card backup-card backup-card-cloud">
                        <div class="dashboard-card-container">
                            <span class="dashboard-card-title">Cloud backup</span>
                        </div>

                        <div class="backup-card-body">
                            <div class="backup-card-row">
                                <span class="label">Status</span>
                                <span class="value">Not connected</span>
                            </div>
                            <div class="backup-card-row">
                                <span class="label">Encryption</span>
                                <span class="value">To be configured</span>
                            </div>
                            <div class="backup-card-row">
                                <span class="label">Retention</span>
                                <span class="value">Not configured</span>
                            </div>
                        </div>

                        <div class="backup-card-actions">
                            @include('components.button', [
                                'buttonType' => 'secondary',
                                'buttonVar' => 'backup-connect-cloud',
                                'buttonSrc' => 'backup',
                                'buttonIcon' => '<i class="fa-solid fa-cloud-arrow-up"></i>',
                                'buttonLabel' => 'Configure cloud backup (UI only)',
                                'btnAttribute' => 'type="button" disabled title="Cloud backup integration not yet implemented"',
                            ])
                        </div>
                    </div>

                </div>

            </div>
        </div>

        <div class="container {{ $pageClass }} mt-3">
            <div class="backup-section">

                <div class="backup-section-header">
                    <div>
                        <div class="backup-section-title">Restore database</div>
                        <div class="backup-section-subtitle">Restore from a previous backup file</div>
                    </div>
                </div>

                <div class="backup-restore-body">
                    <div class="backup-restore-warning">
                        <strong>Caution:</strong> Restoring will overwrite current data. Make sure you have a recent backup before restoring from a previous point.
                    </div>

                    <div class="backup-restore-actions">
                        <form method="POST" action="{{ route('backup.restore') }}" enctype="multipart/form-data" class="backup-restore-form d-flex align-items-center gap-2">
                            @csrf
                            <input type="file" name="backup_file" accept=".sql" class="form-control form-control-sm" required>
                            @include('components.button', [
                                'buttonType' => 'secondary',
                                'buttonVar' => 'backup-select-file',
                                'buttonSrc' => 'backup',
                                'buttonIcon' => '<i class="fa-solid fa-file-arrow-up"></i>',
                                'buttonLabel' => 'Restore from file',
                                'isSubmit' => true,
                            ])
                        </form>
                    </div>
                </div>

            </div>
        </div>

        @php
            $backupHistoryTable = $backupHistoryTable ?? [];
        @endphp

        <div class="container {{ $pageClass }} mt-3 mb-3">
            <div class="backup-section">

                <div class="backup-section-header">
                    <div>
                        <div class="backup-section-title">Backup history</div>
                        <div class="backup-section-subtitle">Recent backup operations</div>
                    </div>
                </div>

                @include('components.table', [
                    'tableClass' => 'backup-history-table',
                    'tableCol' => [
                        'backup-type',
                        'backup-date-time',
                        'backup-size',
                        'backup-location',
                        'backup-status',
                        'backup-actions',
                    ],
                    'tableLabel' => [
                        'Type',
                        'Date & time',
                        'Size',
                        'Location',
                        'Status',
                        'Actions',
                    ],
                    'tableData' => $backupHistoryTable,
                    'rawColumns' => ['backup-status', 'backup-actions'],
                ])

            </div>
        </div>

    </div>

@endsection
