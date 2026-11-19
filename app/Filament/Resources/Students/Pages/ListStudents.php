<?php

namespace App\Filament\Resources\Students\Pages;

use App\Filament\Resources\Students\StudentResource;
use App\Models\AcademicYear;
use App\Services\Import\DapodikStudentImporter;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ListStudents extends ListRecords
{
    protected static string $resource = StudentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importDapodik')
                ->label('Impor Dapodik')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->modalHeading('Impor data siswa dari Excel Dapodik')
                ->modalDescription('Unggah file "Daftar Peserta Didik" (.xlsx). Siswa dicocokkan lewat NISN, jadi aman diulang. Nomor WA orang tua yang sudah diisi manual tidak akan ditimpa.')
                ->modalSubmitActionLabel('Jalankan')
                ->schema([
                    FileUpload::make('file')
                        ->label('File Excel Dapodik (.xlsx)')
                        ->required()
                        ->disk('local')
                        ->directory('imports')
                        ->visibility('private')
                        ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                        ->maxSize(10240),
                    Toggle::make('commit')
                        ->label('Simpan ke database')
                        ->helperText('Matikan untuk simulasi: hasil dihitung tetapi tidak ada data yang tersimpan.')
                        ->default(false),
                ])
                ->action(function (array $data): void {
                    set_time_limit(300);

                    $file = is_array($data['file']) ? reset($data['file']) : $data['file'];
                    $commit = (bool) ($data['commit'] ?? false);
                    $year = AcademicYear::current();

                    if ($year === null) {
                        Notification::make()->danger()->title('Belum ada tahun ajaran aktif')->send();

                        return;
                    }

                    try {
                        $report = app(DapodikStudentImporter::class)->import(
                            Storage::disk('local')->path($file),
                            $year,
                            dryRun: ! $commit,
                        );
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->danger()
                            ->title('File tidak dapat diproses')
                            ->body(Str::limit($e->getMessage(), 200))
                            ->persistent()
                            ->send();

                        return;
                    } finally {
                        Storage::disk('local')->delete($file);
                    }
                    if ($commit) {
                        activity('impor-dapodik')
                            ->causedBy(auth()->user())
                            ->withProperties([
                                'tahun_ajaran' => $year->code,
                                'baru' => $report['created'],
                                'diperbarui' => $report['updated'],
                                'dilewati' => $report['skipped'],
                                'gagal' => $report['failed'],
                                'kelas_baru' => $report['classrooms_created'],
                            ])
                            ->log('Impor data siswa dari Dapodik');
                    }
                    $summary = "Baru {$report['created']} · Diperbarui {$report['updated']} · Dilewati {$report['skipped']} · Gagal {$report['failed']} · Kelas baru {$report['classrooms_created']} · Peringatan " . count($report['warnings']);

                    if ($report['errors'] !== []) {
                        $summary .= ' · Contoh masalah: ' . Str::limit($report['errors'][0], 150);
                    }

                    Notification::make()
                        ->title(($commit ? 'Impor selesai' : 'Simulasi selesai (tidak ada data tersimpan)') . " · TA {$year->code}")
                        ->body($summary)
                        ->color($report['failed'] > 0 ? 'danger' : 'success')
                        ->icon($report['failed'] > 0 ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-check-circle')
                        ->persistent()
                        ->send();
                }),
            CreateAction::make(),
        ];
    }
}
