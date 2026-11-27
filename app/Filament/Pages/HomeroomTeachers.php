<?php

namespace App\Filament\Pages;

use App\Filament\Actions\ImportHomeroomsAction;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Teacher;
use App\Services\HomeroomService;
use BackedEnum;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Daftar kelas tahun ajaran terpilih beserta wali kelasnya. */
class HomeroomTeachers extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-identification';

    protected static string|UnitEnum|null $navigationGroup = 'Data Master';

    protected static ?string $navigationLabel = 'Wali Kelas';

    protected static ?string $title = 'Wali Kelas';

    protected static ?int $navigationSort = 21;

    protected string $view = 'filament.pages.homeroom-teachers';

    public ?int $yearId = null;

    public function mount(): void
    {
        $this->yearId = AcademicYear::query()->where('is_active', true)->value('id');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn(): Builder => Classroom::query()
                ->leftJoin('homeroom_assignments as h', 'h.classroom_id', '=', 'classrooms.id')
                ->leftJoin('teachers as t', 't.id', '=', 'h.teacher_id')
                ->where('classrooms.academic_year_id', $this->yearId)
                ->select('classrooms.*', 't.name as teacher_name', 't.front_title as teacher_front', 't.back_title as teacher_back'))
            ->defaultSort('classrooms.name')
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->searchPlaceholder('Cari kelas atau wali kelas…')
            ->columns([
                TextColumn::make('name')->label('Kelas')->weight('bold')
                    ->searchable(query: fn(Builder $query, string $search): Builder => $query->where('classrooms.name', 'ilike', "%{$search}%")),
                TextColumn::make('teacher_name')->label('Wali kelas')
                    ->state(fn($record): string => $record->teacher_name
                        ? trim(trim(($record->teacher_front ? $record->teacher_front . ' ' : '') . $record->teacher_name) . ($record->teacher_back ? ', ' . $record->teacher_back : ''))
                        : '— belum ada —')
                    ->color(fn($record): ?string => $record->teacher_name ? null : 'warning')
                    ->searchable(query: fn(Builder $query, string $search): Builder => $query->where('t.name', 'ilike', "%{$search}%")),
            ])
            ->filters([SelectFilter::make('kosong')->label('Tampilkan')->options(['kosong' => 'Belum ada wali kelas'])
                ->query(fn(Builder $query, array $data): Builder => ($data['value'] ?? null) === 'kosong' ? $query->whereNull('h.id') : $query)])
            ->recordActions([
                Action::make('set')->label('Atur wali')->icon('heroicon-o-pencil-square')
                    ->schema([Select::make('teacher')->label('Guru')->required()->searchable()
                        ->options(fn() => Teacher::query()->where('status', 'aktif')->orderBy('name')->get()->mapWithKeys(fn(Teacher $t) => [$t->id => $t->full_name])->all())])
                    ->action(function (array $data, $record): void {
                        try {
                            $res = app(HomeroomService::class)->assign(Classroom::query()->findOrFail($record->id), Teacher::query()->findOrFail($data['teacher']), auth()->user());
                            Notification::make()->success()->title($res === 'sama' ? 'Tidak ada perubahan' : 'Wali kelas disimpan')->send();
                        } catch (DomainException $e) {
                            Notification::make()->danger()->title('Gagal')->body($e->getMessage())->send();
                        }
                    }),
                Action::make('clear')->label('Kosongkan')->icon('heroicon-o-x-mark')->color('gray')->requiresConfirmation()
                    ->visible(fn($record): bool => filled($record->teacher_name))
                    ->action(fn($record) => app(HomeroomService::class)->clear(Classroom::query()->findOrFail($record->id), auth()->user())),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('year')->label(fn() => 'Tahun: ' . (AcademicYear::query()->find($this->yearId)?->name ?? '—'))->color('gray')->icon('heroicon-o-calendar')
                ->schema([Select::make('year')->label('Tahun ajaran')->required()->options(fn() => AcademicYear::query()->orderByDesc('start_date')->pluck('name', 'id')->all())->default(fn() => $this->yearId)])
                ->action(function (array $data): void {
                    $this->yearId = (int) $data['year'];
                    $this->resetTable();
                }),
            Action::make('carry')->label('Salin wali dari tahun lalu')->icon('heroicon-o-document-duplicate')->color('gray')
                ->modalDescription('Wali kelas dibawa naik bersama kelasnya (XI PH 1 ← X PH 1). Hanya kelas yang belum punya wali yang diisi. Bisa diganti manual sesudahnya.')
                ->requiresConfirmation()
                ->action(function (): void {
                    $to = AcademicYear::query()->find($this->yearId);
                    $from = $to ? AcademicYear::query()->where('start_date', '<', $to->start_date)->orderByDesc('start_date')->first() : null;

                    if (! $to || ! $from) {
                        Notification::make()->warning()->title('Tidak ada tahun ajaran sebelumnya')->send();

                        return;
                    }

                    $r = app(HomeroomService::class)->carryForward($from, $to, auth()->user());
                    Notification::make()->success()->title("Disalin {$r['disalin']} · dilewati {$r['dilewati']}")->body(implode("\n", array_slice($r['warnings'], 0, 3)))->send();
                    $this->resetTable();
                }),
            ImportHomeroomsAction::make(),
        ];
    }
}
