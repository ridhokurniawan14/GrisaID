<?php

namespace App\Filament\Actions;

use App\Services\Import\DapodikTeacherImporter;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class ImportTeachersAction
{
    public static function make(): Action
    {
        return Action::make('importTeachers')
            ->label('Impor Daftar Guru')
            ->icon('heroicon-o-arrow-up-tray')
            ->color('gray')
            ->modalHeading('Impor Daftar Guru dari Dapodik')
            ->modalDescription('Pakai file "Daftar Guru" hasil unduhan Dapodik apa adanya. Guru dicocokkan lewat NIK, email, lalu NUPTK. Nama dan gelar yang sudah ada tidak ditimpa. Aman diulang.')
            ->modalSubmitActionLabel('Jalankan')
            ->schema([
                FileUpload::make('file')->label('File Excel (.xlsx)')->required()->disk('local')->directory('imports')->visibility('private')
                    ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->maxSize(10240),
                Toggle::make('commit')->label('Simpan ke database')->helperText('Matikan untuk simulasi (tidak ada yang tersimpan). Disarankan simulasi dulu.')->default(false),
            ])
            ->action(function (array $data): void {
                $file = is_array($data['file']) ? reset($data['file']) : $data['file'];
                $commit = (bool) ($data['commit'] ?? false);

                try {
                    $r = app(DapodikTeacherImporter::class)->import(Storage::disk('local')->path($file), dryRun: ! $commit);
                } catch (\Throwable $e) {
                    Notification::make()->danger()->title('File tidak dapat diproses')->body(Str::limit($e->getMessage(), 250))->duration(10000)->send();

                    return;
                } finally {
                    Storage::disk('local')->delete($file);
                }

                if ($commit) {
                    activity('impor-guru')->causedBy(auth()->user())->withProperties(['baru' => $r['created'], 'diperbarui' => $r['updated'], 'gagal' => $r['failed']])->log('Impor Daftar Guru');
                }

                Notification::make()->color($r['failed'] > 0 ? 'warning' : 'success')
                    ->title($commit ? 'Impor guru selesai' : 'Simulasi selesai (tidak ada data tersimpan)')
                    ->body("Baru {$r['created']} · Diperbarui {$r['updated']} · Gagal {$r['failed']}" . ($r['errors'] ? "\n" . Str::limit(implode('; ', array_slice($r['errors'], 0, 3)), 300) : ''))
                    ->duration(10000)->send();
            });
    }
}
