<?php

namespace App\Filament\Actions;

use App\Filament\Resources\Students\StudentResource;
use App\Models\AcademicYear;
use App\Services\StudentPromotion;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;

/** Tombol "Naik Kelas" untuk header halaman daftar Siswa. */
class PromoteStudentsAction
{
    public static function make(): Action
    {
        return Action::make('promote')
            ->label('Naik Kelas')
            ->icon('heroicon-o-arrow-trending-up')
            ->color('gray')
            ->visible(fn (): bool => StudentResource::canCreate())
            ->modalHeading('Naik kelas otomatis')
            ->modalDescription('Semua siswa aktif di tahun ajaran asal dipindahkan satu tingkat ke tahun ajaran tujuan (X → XI, XI → XII). Kelas tujuan dibuat otomatis. Siswa yang sudah punya kelas di tahun tujuan tidak diubah. Siswa tinggal kelas atur manual lewat "Atur kelas" setelahnya.')
            ->modalSubmitActionLabel('Jalankan')
            ->schema([
                Select::make('from')
                    ->label('Tahun ajaran asal')
                    ->options(fn () => AcademicYear::query()->orderByDesc('start_date')->pluck('code', 'id')->all())
                    ->default(function () {
                        $cur = AcademicYear::current();

                        return $cur ? AcademicYear::query()->where('start_date', '<', $cur->start_date)->orderByDesc('start_date')->value('id') : null;
                    })
                    ->required(),
                Select::make('to')
                    ->label('Tahun ajaran tujuan')
                    ->options(fn () => AcademicYear::query()->orderByDesc('start_date')->pluck('code', 'id')->all())
                    ->default(fn () => AcademicYear::current()?->id)
                    ->required(),
                Toggle::make('graduate')
                    ->label('Luluskan siswa kelas XII (jadi alumni)')
                    ->default(true),
                Toggle::make('commit')
                    ->label('Simpan ke database')
                    ->helperText('Matikan untuk simulasi: hasil dihitung lengkap tetapi tidak ada yang berubah. Disarankan simulasi dulu.')
                    ->default(false),
            ])
            ->action(function (array $data): void {
                abort_unless(StudentResource::canCreate(), 403);
                set_time_limit(300);

                $from = AcademicYear::findOrFail($data['from']);
                $to = AcademicYear::findOrFail($data['to']);
                $commit = (bool) ($data['commit'] ?? false);

                try {
                    $r = app(StudentPromotion::class)->run($from, $to, (bool) ($data['graduate'] ?? true), $commit);
                } catch (\Throwable $e) {
                    Notification::make()->danger()->title('Naik kelas gagal')->body(Str::limit($e->getMessage(), 250))->persistent()->send();

                    return;
                }

                $lines = [
                    "Kelas X → XI: {$r['naik_10']} siswa",
                    "Kelas XI → XII: {$r['naik_11']} siswa",
                    "Kelas XII → alumni: {$r['lulus']} siswa".($r['kelas12_dibiarkan'] ? " ({$r['kelas12_dibiarkan']} kelas XII dibiarkan)" : ''),
                    "Kelas baru dibuat: {$r['kelas_baru']}",
                    "Dilewati (sudah punya kelas di tahun tujuan): {$r['sudah_punya_kelas']}",
                ];

                if ($r['warnings'] !== []) {
                    $lines[] = 'Peringatan: '.count($r['warnings']).' — '.implode('; ', array_slice($r['warnings'], 0, 3));
                }

                if ($commit) {
                    activity('naik-kelas')->causedBy(auth()->user())->withProperties(['dari' => $from->code, 'ke' => $to->code] + array_diff_key($r, ['warnings' => 1]))->log("Naik kelas {$from->code} → {$to->code}");
                }

                Notification::make()
                    ->{$commit ? 'success' : 'warning'}()
                    ->title($commit ? 'Naik kelas selesai' : 'SIMULASI — belum ada yang disimpan')
                    ->body(implode("\n", $lines))
                    ->persistent()
                    ->send();
            });
    }
}
