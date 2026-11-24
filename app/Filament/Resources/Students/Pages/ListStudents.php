<?php

namespace App\Filament\Resources\Students\Pages;

use App\Enums\StudentStatus;
use App\Filament\Resources\Students\StudentResource;
use App\Models\AcademicYear;
use App\Services\Import\DapodikStudentImporter;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
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
            \App\Filament\Actions\ImportAlumniAction::make(),
            Action::make('importDapodik')
                ->label('Impor Dapodik')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->modalHeading('Impor data siswa dari Excel Dapodik')
                ->modalDescription('Unggah file "Daftar Peserta Didik" (.xlsx). Siswa dicocokkan lewat NISN, jadi aman diulang. Nomor WA orang tua yang sudah diisi manual tidak akan ditimpa.')
                ->modalSubmitActionLabel('Jalankan')
                ->schema([
                    Select::make('academic_year_id')
                        ->label('Tahun ajaran tujuan')
                        ->options(fn() => AcademicYear::query()->orderByDesc('start_date')->pluck('code', 'id')->all())
                        ->default(fn() => AcademicYear::current()?->id)
                        ->required()
                        ->helperText('Pilih tahun ajaran yang SESUAI dengan isi file. Contoh: export Dapodik terbaru (tahun ajaran baru) harus ke tahun ajaran baru, bukan ke tahun lalu, karena kelas siswa di tahun tujuan akan ditimpa.'),
                    FileUpload::make('file')
                        ->label('File Excel Dapodik (.xlsx)')
                        ->required()
                        ->disk('local')
                        ->directory('imports')
                        ->visibility('private')
                        ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                        ->maxSize(10240),
                    Select::make('graduate_action')
                        ->label('Siswa kelas XII tahun sebelumnya yang tidak ada di file')
                        ->options([
                            'keep' => 'Biarkan (hanya laporkan)',
                            'lulus' => 'Tandai Lulus',
                        ])
                        ->default('keep')
                        ->required()
                        ->helperText('Mereka dianggap lulusan: tidak punya kelas di tahun tujuan dan tahun sebelumnya duduk di kelas XII.'),
                    Select::make('missing_action')
                        ->label('Siswa lain yang tidak ada di file')
                        ->options([
                            'keep' => 'Biarkan (hanya laporkan)',
                            'mutasi' => 'Tandai Mutasi (pindah sekolah)',
                            'do' => 'Tandai DO (mengundurkan diri / putus sekolah)',
                        ])
                        ->default('keep')
                        ->required()
                        ->helperText('Dibatalkan otomatis bila terlalu banyak yang tidak ada di file (file kemungkinan tidak lengkap). Data siswa tidak dihapus, hanya statusnya.'),
                    Toggle::make('commit')
                        ->label('Simpan ke database')
                        ->helperText('Matikan untuk simulasi: hasil (termasuk penandaan status) dihitung tetapi tidak ada data yang tersimpan.')
                        ->default(false),
                ])
                ->action(function (array $data): void {
                    set_time_limit(300);

                    $file = is_array($data['file']) ? reset($data['file']) : $data['file'];
                    $commit = (bool) ($data['commit'] ?? false);
                    $year = AcademicYear::find($data['academic_year_id'] ?? null);

                    if ($year === null) {
                        Storage::disk('local')->delete($file);
                        Notification::make()->danger()->title('Tahun ajaran tujuan tidak ditemukan')->send();

                        return;
                    }

                    $statusForGraduates = ($data['graduate_action'] ?? 'keep') === 'lulus' ? StudentStatus::Graduated : null;
                    $statusForOthers = match ($data['missing_action'] ?? 'keep') {
                        'mutasi' => StudentStatus::Transferred,
                        'do' => StudentStatus::DroppedOut,
                        default => null,
                    };

                    try {
                        $report = app(DapodikStudentImporter::class)->import(
                            Storage::disk('local')->path($file),
                            $year,
                            dryRun: ! $commit,
                            statusForGraduates: $statusForGraduates,
                            statusForOthers: $statusForOthers,
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
                                'lulusan_tidak_ada_di_file' => $report['graduates_count'],
                                'lulusan_ditandai_lulus' => $report['graduates_marked'],
                                'lainnya_tidak_ada_di_file' => $report['missing_count'],
                                'lainnya_ditandai_nonaktif' => $report['missing_marked'],
                            ])
                            ->log('Impor data siswa dari Dapodik');
                    }

                    $summary = "Baru {$report['created']} · Diperbarui {$report['updated']} · Dilewati {$report['skipped']} · Gagal {$report['failed']} · Kelas baru {$report['classrooms_created']} · Peringatan " . count($report['warnings']);

                    if ($report['graduates_count'] > 0) {
                        $summary .= $report['graduates_marked'] > 0
                            ? " · {$report['graduates_marked']} siswa kelas XII tahun lalu ditandai Lulus"
                            : " · {$report['graduates_count']} siswa kelas XII tahun lalu tidak ada di file dan masih aktif";
                    }

                    if ($report['missing_count'] > 0) {
                        $names = Str::limit(implode(', ', array_slice($report['missing'], 0, 5)), 150);
                        $summary .= $report['missing_marked'] > 0
                            ? " · {$report['missing_marked']} siswa lain tidak ada di file dan ditandai {$statusForOthers->label()}: {$names}"
                            : " · Siswa lain yang masih aktif tapi tidak ada di file: {$report['missing_count']} ({$names})";
                    }

                    if ($report['errors'] !== []) {
                        $summary .= ' · Contoh masalah: ' . Str::limit($report['errors'][0], 200);
                    }

                    $hasProblem = $report['failed'] > 0 || $report['errors'] !== [];
                    $needsAttention = $hasProblem
                        || $report['graduates_count'] > $report['graduates_marked']
                        || $report['missing_count'] > $report['missing_marked'];

                    $notification = Notification::make()
                        ->title(($commit ? 'Impor selesai' : 'Simulasi selesai (tidak ada data tersimpan)') . " · TA {$year->code}")
                        ->body($summary)
                        ->color($hasProblem ? 'danger' : ($needsAttention ? 'warning' : 'success'))
                        ->icon($hasProblem ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-check-circle');

                    if ($needsAttention) {
                        $notification->persistent();
                    }

                    $notification->send();
                }),
            CreateAction::make(),
        ];
    }
}
