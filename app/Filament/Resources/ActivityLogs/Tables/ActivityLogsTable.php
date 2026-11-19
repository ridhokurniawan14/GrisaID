<?php

namespace App\Filament\Resources\ActivityLogs\Tables;

use App\Models\StudentEnrollment;
use App\Models\StudentGuardian;
use App\Support\ActivityChangeFormatter;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

class ActivityLogsTable
{
    private const LOG_NAMES = [
        'student' => 'Siswa',
        'student-guardian' => 'Orang tua / wali',
        'student-enrollment' => 'Kelas siswa',
        'classroom' => 'Kelas',
        'major' => 'Jurusan',
        'academic-year' => 'Tahun ajaran',
        'impor-dapodik' => 'Impor Dapodik',
    ];

    private const ACTIONS = [
        'created' => 'Dibuat',
        'updated' => 'Diubah',
        'deleted' => 'Dihapus',
        'restored' => 'Dipulihkan',
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn(Builder $query) => $query->with(['causer', 'subject']))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d M Y H:i:s')
                    ->sortable(),
                TextColumn::make('causer.name')
                    ->label('Oleh')
                    ->placeholder('Sistem'),
                TextColumn::make('log_name')
                    ->label('Data')
                    ->badge()
                    ->formatStateUsing(fn($state): string => self::LOG_NAMES[$state] ?? (string) $state),
                TextColumn::make('description')
                    ->label('Aksi')
                    ->badge()
                    ->formatStateUsing(fn($state): string => self::ACTIONS[$state] ?? (string) $state)
                    ->color(fn($state): string => match ($state) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'deleted' => 'danger',
                        'restored' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('subject_label')
                    ->label('Objek')
                    ->state(fn(Activity $record): string => self::subjectLabel($record)),
                TextColumn::make('changes')
                    ->label('Perubahan')
                    ->state(fn(Activity $record): array => self::changeLines($record))
                    ->listWithLineBreaks()
                    ->limitList(3)
                    ->expandableLimitedList(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('log_name')
                    ->label('Jenis data')
                    ->options(self::LOG_NAMES),
                SelectFilter::make('description')
                    ->label('Aksi')
                    ->options(self::ACTIONS),
                SelectFilter::make('period')
                    ->label('Periode')
                    ->options(['today' => 'Hari ini', '7d' => '7 hari terakhir', '30d' => '30 hari terakhir'])
                    ->query(fn(Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'today' => $query->where('created_at', '>=', now()->startOfDay()),
                        '7d' => $query->where('created_at', '>=', now()->subDays(7)),
                        '30d' => $query->where('created_at', '>=', now()->subDays(30)),
                        default => $query,
                    }),
            ]);
    }

    private static function subjectLabel(Activity $record): string
    {
        $subject = $record->subject;

        if ($subject instanceof StudentEnrollment) {
            return trim(($subject->student?->full_name ?? 'Siswa') . ' (' . ($subject->classroom?->name ?? '-') . ')');
        }

        if ($subject instanceof StudentGuardian) {
            return trim(($subject->student?->full_name ?? 'Siswa') . ' · ' . ($subject->relation?->label() ?? 'Wali'));
        }

        if ($subject !== null) {
            return (string) ($subject->full_name ?? $subject->name ?? $subject->code ?? ('#' . $record->subject_id));
        }

        if ($record->subject_type === null) {
            return '-';
        }

        // Data sudah terhapus permanen: ambil nama dari catatan perubahan jika ada.
        $changes = $record->attribute_changes;
        $changes = is_object($changes) && method_exists($changes, 'toArray') ? $changes->toArray() : (array) $changes;

        foreach (['full_name', 'name', 'code'] as $key) {
            $value = data_get($changes, "attributes.{$key}") ?? data_get($changes, "old.{$key}");

            if (filled($value)) {
                return (string) $value;
            }
        }

        return class_basename($record->subject_type) . ' #' . $record->subject_id;
    }

    /** @return list<string> */
    private static function changeLines(Activity $record): array
    {
        $lines = ActivityChangeFormatter::lines($record->attribute_changes);

        if ($lines !== []) {
            return $lines;
        }

        // Aktivitas manual (mis. impor Dapodik) menyimpan ringkasannya di properties.
        return collect($record->properties)
            ->map(fn($value, $key): string => $key . ': ' . (is_scalar($value) ? $value : json_encode($value)))
            ->values()
            ->all();
    }
}
