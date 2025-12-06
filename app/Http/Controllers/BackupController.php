<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BackupController extends Controller
{
    public function index(Request $request)
    {
        $backups = $this->listBackupFiles();

        $lastBackupFile = $backups[0] ?? null;

        $summary = [
            'last_backup' => [
                'label' => $lastBackupFile
                    ? $lastBackupFile['created_at']->format('Y-m-d H:i:s')
                    : 'No backups yet',
                'status' => $lastBackupFile
                    ? 'Last full backup file: ' . $lastBackupFile['filename']
                    : 'No completed backups have been recorded.',
            ],
            'next_backup' => [
                'label' => 'Not scheduled',
                'status' => 'Configure your backup schedule on the server.',
            ],
            'storage' => $this->buildStorageSummary($backups),
        ];

        $backupHistoryTable = $this->buildHistoryTable($backups);

        return view('pages.backup', [
            'title' => 'Backup & Recovery',
            'pageClass' => 'backup',
            'summary' => $summary,
            'backupHistoryTable' => $backupHistoryTable,
        ]);
    }

    public function createBackup(Request $request)
    {
        $connection = config('database.default');
        $config = config("database.connections.$connection");

        if (($config['driver'] ?? null) !== 'mysql') {
            return redirect()->route('backup')
                ->with('backup_error', 'Database backups are currently only supported for MySQL connections.');
        }

        $database = $config['database'];
        $username = $config['username'];
        $password = $config['password'];
        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 3306;

        try {
            if (!Storage::disk('local')->exists('backups')) {
                Storage::disk('local')->makeDirectory('backups');
            }

            $fileName = 'backup_' . Carbon::now()->format('Ymd_His') . '.sql';
            $filePath = storage_path('app/backups/' . $fileName);

            $this->dumpDatabaseToFile($database, $host, $port, $username, $password, $filePath);

            if (!file_exists($filePath) || filesize($filePath) === 0) {
                return redirect()->route('backup')
                    ->with('backup_error', 'Backup file could not be created. Please check server permissions.');
            }

            return redirect()->route('backup')
                ->with('backup_success', 'Database backup created: ' . $fileName);
        } catch (\Throwable $e) {
            return redirect()->route('backup')
                ->with('backup_error', 'Backup failed: ' . $e->getMessage());
        }
    }

    public function downloadBackup(string $file)
    {
        $file = basename($file);
        $path = storage_path('app/backups/' . $file);

        if (!is_file($path)) {
            return redirect()->route('backup')
                ->with('backup_error', 'The requested backup file was not found.');
        }

        return response()->download($path);
    }

    public function restoreBackup(Request $request)
    {
        $connection = config('database.default');
        $config = config("database.connections.$connection");

        if (($config['driver'] ?? null) !== 'mysql') {
            return redirect()->route('backup')
                ->with('backup_error', 'Database restore is currently only supported for MySQL connections.');
        }

        $request->validate([
            'backup_file' => ['required', 'file'],
        ]);

        $uploadedFile = $request->file('backup_file');

        if (!$uploadedFile || !$uploadedFile->isValid()) {
            return redirect()->route('backup')
                ->with('backup_error', 'Please select a valid backup file.');
        }

        $sql = file_get_contents($uploadedFile->getRealPath());

        if ($sql === false || trim($sql) === '') {
            return redirect()->route('backup')
                ->with('backup_error', 'The selected backup file is empty or unreadable.');
        }

        try {
            DB::unprepared($sql);

            return redirect()->route('backup')
                ->with('backup_success', 'Database restore completed from selected backup file.');
        } catch (\Throwable $e) {
            return redirect()->route('backup')
                ->with('backup_error', 'Restore failed: ' . $e->getMessage());
        }
    }

    protected function listBackupFiles(): array
    {
        $directory = storage_path('app/backups');

        if (!is_dir($directory)) {
            return [];
        }

        $files = glob($directory . DIRECTORY_SEPARATOR . '*.sql') ?: [];

        $backups = [];

        foreach ($files as $path) {
            $size = filesize($path) ?: 0;
            $backups[] = [
                'filename' => basename($path),
                'path' => $path,
                'size_bytes' => $size,
                'size_human' => $this->formatBytes($size),
                'created_at' => Carbon::createFromTimestamp(filemtime($path)),
            ];
        }

        usort($backups, function ($a, $b) {
            return $b['created_at']->timestamp <=> $a['created_at']->timestamp;
        });

        return $backups;
    }

    protected function buildStorageSummary(array $backups): array
    {
        if (empty($backups)) {
            return [
                'used' => null,
                'limit' => null,
                'percentage' => null,
            ];
        }

        $totalBytes = array_sum(array_column($backups, 'size_bytes'));

        return [
            'used' => $this->formatBytes($totalBytes),
            'limit' => null,
            'percentage' => null,
        ];
    }

    protected function buildHistoryTable(array $backups): array
    {
        if (empty($backups)) {
            return [
                ['No data found', '—', '—', '—', '—', '—'],
            ];
        }

        $rows = [];

        foreach ($backups as $backup) {
            $statusHtml = '<span class="badge bg-success">Available</span>';
            $downloadUrl = route('backup.download', ['file' => $backup['filename']]);

            $actionsHtml = '<button type="button" class="btn btn-outline-primary btn-sm" onclick="window.location.href=\'' . $downloadUrl . '\'">Download</button>';

            $rows[] = [
                'Full database',
                $backup['created_at']->format('Y-m-d H:i:s'),
                $backup['size_human'],
                'Local storage',
                $statusHtml,
                $actionsHtml,
            ];
        }

        return $rows;
    }

    protected function dumpDatabaseToFile(string $database, string $host, $port, string $username, string $password, string $filePath): void
    {
        $pdo = DB::connection()->getPdo();

        $tablesResult = DB::select('SHOW TABLES');
        $columnKey = 'Tables_in_' . $database;

        $handle = fopen($filePath, 'w');

        if ($handle === false) {
            throw new \RuntimeException('Unable to open backup file for writing.');
        }

        fwrite($handle, '-- Database backup generated at ' . Carbon::now()->toDateTimeString() . PHP_EOL . PHP_EOL);
        fwrite($handle, 'SET FOREIGN_KEY_CHECKS=0;' . PHP_EOL . PHP_EOL);

        foreach ($tablesResult as $row) {
            if (!isset($row->$columnKey)) {
                continue;
            }

            $table = $row->$columnKey;

            $createRow = DB::selectOne("SHOW CREATE TABLE `$table`");

            if (!$createRow) {
                continue;
            }

            $createSql = $createRow->{'Create Table'} ?? null;

            if (!$createSql) {
                continue;
            }

            fwrite($handle, 'DROP TABLE IF EXISTS `' . $table . '`;' . PHP_EOL);
            fwrite($handle, $createSql . ';' . PHP_EOL . PHP_EOL);

            $rows = DB::table($table)->get();

            if ($rows->isEmpty()) {
                continue;
            }

            $columns = array_keys((array)$rows[0]);
            $columnList = '`' . implode('`,`', $columns) . '`';

            $rowsArray = $rows->toArray();

            foreach (array_chunk($rowsArray, 500) as $chunk) {
                $valuesSql = [];

                foreach ($chunk as $rowData) {
                    $rowData = (array)$rowData;

                    $valueParts = [];

                    foreach ($columns as $column) {
                        $value = $rowData[$column] ?? null;

                        if (is_null($value)) {
                            $valueParts[] = 'NULL';
                        } else {
                            $valueParts[] = $pdo->quote($value);
                        }
                    }

                    $valuesSql[] = '(' . implode(',', $valueParts) . ')';
                }

                $insertSql = 'INSERT INTO `' . $table . '` (' . $columnList . ') VALUES ' . PHP_EOL
                    . implode(',' . PHP_EOL, $valuesSql) . ';' . PHP_EOL . PHP_EOL;

                fwrite($handle, $insertSql);
            }
        }

        fwrite($handle, 'SET FOREIGN_KEY_CHECKS=1;' . PHP_EOL);
        fclose($handle);
    }

    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $power = (int) floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);

        $value = $bytes / (1024 ** $power);

        return round($value, $precision) . ' ' . $units[$power];
    }
}
