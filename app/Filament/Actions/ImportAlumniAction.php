<?php

namespace App\Filament\Actions;

use App\Services\Import\AlumniStudentImporter;
use App\Services\Import\AlumniTemplate;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Tombol "Impor Alumni" untuk halaman daftar Siswa di GrisaID. */
final class ImportAlumniAction
{
    /** Grup tombol "Alumni": Impor dan Unduh template. */
    public static function make(): ActionGroup
    {
        return ActionGroup::make([self::import(), self::template()])
            ->label('Alumni')
            ->icon('heroicon-o-academic-cap')
            ->color('gray')
            ->button();
    }

    public static function template(): Action
    {
        return Action::make('alumniTemplate')
            ->label('Unduh template Excel')
            ->icon('heroicon-o-document-arrow-down')
            ->action(fn () => AlumniTemplate::download());
    }

    public static function import(): Action
    {
        return Action::make('importAlumni')
            ->label('Impor dari Excel')
            ->icon('heroicon-o-arrow-up-tray')
            ->modalHeading('Impor data alumni dari Excel')
            ->modalDescription('Satu sheet per tahun lulus (nama sheet: 2020, 2021, ...). Kolom: Nama, L/P, NIK, NISN, Tanggal Lahir, Nama Ibu, Rombel Terakhir. Alumni dicocokkan lewat NISN dan dibuat berstatus Lulus tanpa kelas. Siswa yang sudah ada tidak diubah statusnya. Aman diulang.')
            ->modalSubmitActionLabel('Jalankan')
            ->schema([
                FileUpload::make('file')
                    ->label('File Excel alumni (.xlsx)')
                    ->required()
                    ->disk('local')
                    ->directory('imports')
                    ->visibility('private')
                    ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                    ->maxSize(10240),
                Toggle::make('commit')
                    ->label('Simpan ke database')
                    ->helperText('Matikan untuk simulasi: hasil dihitung lengkap tetapi tidak ada yang tersimpan. Disarankan simulasi dulu.')
                    ->default(false),
            ])
            ->action(function (array $data): void {
                set_time_limit(300);
                @ini_set('memory_limit', '512M');

                $file = is_array($data['file']) ? reset($data['file']) : $data['file'];
                $commit = (bool) ($data['commit'] ?? false);

                try {
                    $report = app(AlumniStudentImporter::class)->import(Storage::disk('local')->path($file), dryRun: ! $commit);
                } catch (\Throwable $e) {
                    Notification::make()->danger()->title('File tidak dapat diproses')
                        ->body(Str::limit($e->getMessage(), 250))->persistent()->send();

                    return;
                } finally {
                    Storage::disk('local')->delete($file);
                }

                if ($commit) {
                    activity('impor-alumni')
                        ->causedBy(auth()->user())
                        ->withProperties([
                            'baru' => $report['created'],
                            'diperbarui' => $report['updated'],
                            'dilewati' => $report['skipped'],
                            'masih_aktif_dilewati' => $report['active_skipped'],
                            'gagal' => $report['failed'],
                            'per_tahun' => $report['by_year'],
                        ])
                        ->log('Impor data alumni');
                }

                $years = collect($report['by_year'])->map(fn (array $y, string $year): string => "{$year}: {$y['created']} baru, ".($y['updated'] + $y['skipped'])." sudah ada")->implode(' · ');

                $body = "Baru {$report['created']} · Diperbarui {$report['updated']} · Dilewati {$report['skipped']} · Masih aktif (dilewati) {$report['active_skipped']} · Gagal {$report['failed']} · Peringatan ".count($report['warnings']);

                if ($years !== '') {
                    $body .= "\n".$years;
                }

                $problems = array_merge($report['errors'], array_slice($report['warnings'], 0, 3));

                if ($problems !== []) {
                    $body .= "\n".Str::limit(implode('; ', array_slice($problems, 0, 3)), 300);
                }

                Notification::make()
                    ->color($report['failed'] > 0 ? 'warning' : 'success')
                    ->title($commit ? 'Impor alumni selesai' : 'Simulasi selesai (tidak ada data tersimpan)')
                    ->body($body)
                    ->persistent()
                    ->send();
            });
    }
}
