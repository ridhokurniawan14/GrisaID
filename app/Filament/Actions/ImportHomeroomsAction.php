<?php

namespace App\Filament\Actions;

use App\Models\AcademicYear;
use App\Services\Import\HomeroomImporter;
use App\Services\Import\HomeroomTemplate;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class ImportHomeroomsAction
{
    /** Grup tombol "Rombel": Impor dan Unduh template. */
    public static function make(): ActionGroup
    {
        return ActionGroup::make([self::import(), self::template()])
            ->label('Rombel')
            ->icon('heroicon-o-academic-cap')
            ->color('gray')
            ->button();
    }

    public static function template(): Action
    {
        return Action::make('homeroomTemplate')
            ->label('Unduh template Excel')
            ->icon('heroicon-o-document-arrow-down')
            ->action(fn() => HomeroomTemplate::download());
    }

    public static function import(): Action
    {
        return Action::make('importHomerooms')
            ->label('Impor dari Excel')
            ->icon('heroicon-o-arrow-up-tray')
            ->color('gray')
            ->modalHeading('Impor wali kelas dari Rombongan Belajar')
            ->modalDescription('Pakai file Rombongan Belajar dari Dapodik, atau template dari "Unduh template Excel". Kolom yang dipakai: Nama Rombel dan Guru/Wali Kelas (kolom lain diabaikan). Kelas harus sudah ada di tahun ajaran itu (impor siswa dulu) dan guru sudah diimpor. Nama yang tidak cocok dilaporkan, tidak ditebak.')
            ->modalSubmitActionLabel('Jalankan')
            ->schema([
                Select::make('year')->label('Tahun ajaran')->required()
                    ->options(fn() => AcademicYear::query()->orderByDesc('start_date')->pluck('name', 'id')->all())
                    ->default(fn() => AcademicYear::query()->where('is_active', true)->value('id')),
                FileUpload::make('file')->label('File Excel Rombel (.xlsx)')->required()->disk('local')->directory('imports')->visibility('private')
                    ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->maxSize(10240),
                Toggle::make('commit')->label('Simpan ke database')->helperText('Matikan untuk simulasi.')->default(false),
            ])
            ->action(function (array $data): void {
                $file = is_array($data['file']) ? reset($data['file']) : $data['file'];
                $commit = (bool) ($data['commit'] ?? false);
                $year = AcademicYear::query()->findOrFail($data['year']);

                try {
                    $r = app(HomeroomImporter::class)->import(Storage::disk('local')->path($file), $year, dryRun: ! $commit, by: auth()->user());
                } catch (\Throwable $e) {
                    Notification::make()->danger()->title('File tidak dapat diproses')->body(Str::limit($e->getMessage(), 250))->duration(10000)->send();

                    return;
                } finally {
                    Storage::disk('local')->delete($file);
                }

                Notification::make()->color($r['gagal'] > 0 ? 'warning' : 'success')
                    ->title($commit ? 'Impor wali kelas selesai' : 'Simulasi selesai (tidak ada data tersimpan)')
                    ->body("Baru {$r['baru']} · Sama {$r['sama']} · Diganti {$r['diganti']} · Gagal {$r['gagal']}" . ($r['warnings'] ? "\n" . Str::limit(implode('; ', array_slice($r['warnings'], 0, 4)), 400) : ''))
                    ->duration(10000)->send();
            });
    }
}
