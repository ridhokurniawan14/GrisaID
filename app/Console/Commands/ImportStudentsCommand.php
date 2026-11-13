<?php

namespace App\Console\Commands;

use App\Models\AcademicYear;
use App\Services\Import\DapodikStudentImporter;
use Illuminate\Console\Command;

class ImportStudentsCommand extends Command
{
    protected $signature = 'grisaid:import-students
        {file : Path ke file Excel Dapodik "Daftar Peserta Didik"}
        {--year= : Kode tahun ajaran, mis. 2025/2026 (default: tahun ajaran aktif)}
        {--commit : Simpan ke database. Tanpa opsi ini hanya simulasi (dry-run)}';

    protected $description = 'Impor data siswa dari Excel Dapodik (default: dry-run)';

    public function handle(DapodikStudentImporter $importer): int
    {
        $path = (string) $this->argument('file');

        if (! is_file($path)) {
            $this->error("File tidak ditemukan: {$path}");

            return self::FAILURE;
        }

        $code = $this->option('year');
        $year = $code ? AcademicYear::where('code', $code)->first() : AcademicYear::current();

        if ($year === null) {
            $this->error($code ? "Tahun ajaran {$code} tidak ada." : 'Belum ada tahun ajaran aktif. Jalankan: php artisan db:seed');

            return self::FAILURE;
        }

        $dryRun = ! $this->option('commit');
        $this->info(($dryRun ? '[DRY-RUN] Simulasi, tidak ada yang disimpan' : '[COMMIT] Menyimpan ke database') . " | Tahun ajaran {$year->code}");

        $report = $importer->import($path, $year, $dryRun);

        $this->table(['Hasil', 'Jumlah'], [
            ['Siswa baru', $report['created']],
            ['Siswa diperbarui', $report['updated']],
            ['Dilewati (duplikat antar-sheet)', $report['skipped']],
            ['Gagal', $report['failed']],
            ['Kelas baru dibuat', $report['classrooms_created']],
            ['Peringatan', count($report['warnings'])],
        ]);

        foreach (array_slice($report['errors'], 0, 30) as $error) {
            $this->error($error);
        }
        foreach (array_slice($report['warnings'], 0, 30) as $warning) {
            $this->warn($warning);
        }

        if ($dryRun) {
            $this->comment('Jika hasil sudah benar, ulangi dengan opsi --commit.');
        }

        return $report['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
