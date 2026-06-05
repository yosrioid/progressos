<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class BackupDatabase extends Command
{
    protected $signature = 'db:backup
        {--path=       : Direktori output (default: storage/backups)}
        {--no-compress : Tanpa kompresi gzip}
        {--keep=10     : Simpan hanya N backup terbaru (0 = simpan semua)}
        {--list        : Tampilkan daftar backup yang ada}';

    protected $description = 'Backup database ke file lokal (tidak di-push ke GitHub)';

    public function handle(): int
    {
        if ($this->option('list')) {
            return $this->listBackups();
        }

        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");

        return match ($driver) {
            'mysql', 'mariadb' => $this->backupMysql($connection),
            'sqlite' => $this->backupSqlite($connection),
            default => $this->unsupportedDriver($driver),
        };
    }

    // ── MySQL / MariaDB ───────────────────────────────────────────────────────

    private function backupMysql(string $connection): int
    {
        if (! $this->commandExists('mysqldump')) {
            $this->error('mysqldump tidak ditemukan di PATH. Install MySQL client tools terlebih dahulu.');

            return self::FAILURE;
        }

        $host = config("database.connections.{$connection}.host");
        $port = config("database.connections.{$connection}.port", 3306);
        $database = config("database.connections.{$connection}.database");
        $username = config("database.connections.{$connection}.username");
        $password = config("database.connections.{$connection}.password");

        $compress = ! $this->option('no-compress');
        $ext = $compress ? 'sql.gz' : 'sql';
        $filename = "{$database}_".now()->format('Y-m-d_H-i-s').".{$ext}";
        $filepath = $this->getBackupDir().DIRECTORY_SEPARATOR.$filename;

        $this->line('');
        $this->line("  Database : <fg=cyan>{$database}</>");
        $this->line('  Driver   : <fg=cyan>MySQL</>');
        $this->line("  Output   : <fg=yellow>{$filepath}</>");
        $this->line('  Compress : <fg=cyan>'.($compress ? 'gzip' : 'tidak').'</>');
        $this->line('');

        $dumpCmd = sprintf(
            'mysqldump --host=%s --port=%s --user=%s --password=%s'
            .' --single-transaction --routines --triggers %s',
            escapeshellarg((string) $host),
            escapeshellarg((string) $port),
            escapeshellarg((string) $username),
            escapeshellarg((string) $password),
            escapeshellarg((string) $database),
        );

        $outputPart = $compress
            ? '| gzip > '.escapeshellarg($filepath)
            : '> '.escapeshellarg($filepath);

        $exitCode = 0;
        exec("{$dumpCmd} {$outputPart} 2>&1", $output, $exitCode);

        if ($exitCode !== 0 || ! file_exists($filepath)) {
            $this->error('mysqldump gagal:');
            foreach ($output as $line) {
                $this->line("  {$line}");
            }

            return self::FAILURE;
        }

        $this->reportSuccess($filepath);
        $this->pruneOldBackups($this->getBackupDir());

        return self::SUCCESS;
    }

    // ── SQLite ────────────────────────────────────────────────────────────────

    private function backupSqlite(string $connection): int
    {
        $database = config("database.connections.{$connection}.database");

        if (! file_exists($database)) {
            $this->error("File SQLite tidak ditemukan: {$database}");

            return self::FAILURE;
        }

        $basename = pathinfo($database, PATHINFO_FILENAME);
        $filename = "{$basename}_".now()->format('Y-m-d_H-i-s').'.db';
        $filepath = $this->getBackupDir().DIRECTORY_SEPARATOR.$filename;

        $this->line('');
        $this->line("  Database : <fg=cyan>{$database}</>");
        $this->line('  Driver   : <fg=cyan>SQLite</>');
        $this->line("  Output   : <fg=yellow>{$filepath}</>");
        $this->line('');

        copy($database, $filepath);

        $this->reportSuccess($filepath);
        $this->pruneOldBackups($this->getBackupDir());

        return self::SUCCESS;
    }

    // ── List backups ──────────────────────────────────────────────────────────

    private function listBackups(): int
    {
        $dir = $this->getBackupDir();

        if (! is_dir($dir)) {
            $this->warn("Direktori backup belum ada: {$dir}");

            return self::SUCCESS;
        }

        $files = collect(File::files($dir))
            ->sortByDesc(fn ($f) => $f->getMTime())
            ->values();

        if ($files->isEmpty()) {
            $this->warn('Belum ada backup di: '.$dir);

            return self::SUCCESS;
        }

        $this->line('');
        $this->line("  <fg=cyan>Backup di {$dir}</>");
        $this->line('');

        foreach ($files as $i => $file) {
            $size = $this->humanSize($file->getSize());
            $date = date('Y-m-d H:i:s', $file->getMTime());
            $no = str_pad((string) ($i + 1), 3, ' ', STR_PAD_LEFT);
            $this->line("  <fg=gray>{$no}.</> <fg=yellow>{$file->getFilename()}</> <fg=gray>· {$size} · {$date}</>");
        }

        $this->line('');
        $this->line("  <fg=gray>Total: {$files->count()} file</>");
        $this->line('');

        return self::SUCCESS;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function getBackupDir(): string
    {
        $path = $this->option('path')
            ? rtrim((string) $this->option('path'), DIRECTORY_SEPARATOR)
            : storage_path('backups');

        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }

        return $path;
    }

    private function pruneOldBackups(string $dir): void
    {
        $keep = (int) $this->option('keep');
        if ($keep <= 0) {
            return;
        }

        $files = collect(File::files($dir))->sortByDesc(fn ($f) => $f->getMTime())->values();
        $toDelete = $files->slice($keep);

        if ($toDelete->isEmpty()) {
            return;
        }

        $this->line("  <fg=gray>Menghapus {$toDelete->count()} backup lama (sisakan {$keep})...</>");
        $toDelete->each(fn ($f) => File::delete($f->getPathname()));
    }

    private function reportSuccess(string $filepath): void
    {
        $size = $this->humanSize(filesize($filepath));
        $this->line('  <fg=green>✓ Backup berhasil!</>');
        $this->line("    Path : <fg=yellow>{$filepath}</>");
        $this->line("    Size : <fg=cyan>{$size}</>");
        $this->line('');
    }

    private function commandExists(string $command): bool
    {
        exec("which {$command} 2>/dev/null", $out, $code);

        return $code === 0;
    }

    private function unsupportedDriver(string $driver): int
    {
        $this->error("Driver database tidak didukung: {$driver}");
        $this->line('Didukung: mysql, mariadb, sqlite');

        return self::FAILURE;
    }

    private function humanSize(int $bytes): string
    {
        foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
            if ($bytes < 1024) {
                return "{$bytes} {$unit}";
            }
            $bytes = (int) ($bytes / 1024);
        }

        return "{$bytes} TB";
    }
}
