<?php

namespace App\Filament\Resources\Students\Tables;

use App\Enums\EnrollmentStatus;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\StudentEnrollment;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class StudentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn(Builder $query) => $query->with(['currentEnrollment.classroom', 'guardians']))
            ->columns([
                // Klik NISN = salin (tidak membuka halaman edit). NISN = username login siswa.
                ViewColumn::make('nisn')->label('NISN (username)')->view('filament.columns.copy')->searchable()->sortable(),
                TextColumn::make('full_name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('gender')
                    ->label('JK')
                    ->formatStateUsing(fn($state): string => Gender::fromState($state)?->value ?? '-'),
                TextColumn::make('currentEnrollment.classroom.name')
                    ->label('Kelas')
                    ->placeholder('-'),
                TextColumn::make('entry_year')->label('Angkatan')->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn($state): string => StudentStatus::fromState($state)?->label() ?? (string) $state)
                    ->color(fn($state): string => StudentStatus::fromState($state)?->color() ?? 'gray'),
                IconColumn::make('has_guardian_whatsapp')
                    ->label('WA ortu')
                    ->boolean()
                    ->state(fn(Student $record): bool => $record->guardians->contains(fn($guardian) => filled($guardian->whatsapp))),
                TextColumn::make('nis')->label('NIS')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('phone')->label('WA siswa')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('full_name')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(StudentStatus::options())
                    ->default(StudentStatus::Active->value),
                SelectFilter::make('classroom')
                    ->label('Kelas (tahun ajaran aktif)')
                    ->searchable()
                    ->options(fn() => Classroom::query()->inActiveYear()->orderBy('name')->pluck('name', 'id')->all())
                    ->query(fn(Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('currentEnrollment', fn(Builder $enrollment) => $enrollment->where('classroom_id', $data['value']))
                        : $query),
                SelectFilter::make('grade')
                    ->label('Tingkat')
                    ->options([10 => 'X', 11 => 'XI', 12 => 'XII'])
                    ->query(fn(Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('currentEnrollment.classroom', fn(Builder $classroom) => $classroom->where('grade', (int) $data['value']))
                        : $query),
                SelectFilter::make('entry_year')
                    ->label('Angkatan')
                    ->options(fn() => Student::query()->distinct()->orderByDesc('entry_year')->pluck('entry_year', 'entry_year')->all()),
                Filter::make('no_guardian_whatsapp')
                    ->label('Belum ada WA orang tua')
                    ->toggle()
                    ->query(fn(Builder $query): Builder => $query->whereDoesntHave(
                        'guardians',
                        fn(Builder $guardian) => $guardian->whereNotNull('whatsapp')->where('whatsapp', '!=', '')
                    )),
                Filter::make('no_current_classroom')
                    ->label('Belum punya kelas (tahun ajaran aktif)')
                    ->toggle()
                    ->query(fn(Builder $query): Builder => $query->whereDoesntHave('currentEnrollment')),
                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('assignClassroom')
                    ->label('Atur kelas')
                    ->icon('heroicon-o-academic-cap')
                    ->color('info')
                    ->modalHeading(fn(Student $record): string => "Atur kelas: {$record->full_name}")
                    ->modalDescription('Kelas berlaku untuk tahun ajaran yang sedang aktif. Riwayat kelas tahun lain tidak berubah.')
                    ->fillForm(fn(Student $record): array => [
                        'classroom_id' => $record->currentEnrollment?->classroom_id,
                    ])
                    ->schema([
                        Select::make('classroom_id')
                            ->label('Kelas')
                            ->options(fn() => Classroom::query()->inActiveYear()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (Student $record, array $data): void {
                        $year = AcademicYear::current();

                        if ($year === null) {
                            Notification::make()->danger()->title('Belum ada tahun ajaran aktif')->send();

                            return;
                        }

                        StudentEnrollment::updateOrCreate(
                            ['student_id' => $record->id, 'academic_year_id' => $year->id],
                            ['classroom_id' => $data['classroom_id'], 'status' => EnrollmentStatus::Active->value],
                        );

                        Notification::make()->success()->title('Kelas diperbarui')->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('assignClassroomBulk')
                        ->label('Atur kelas')
                        ->icon('heroicon-o-academic-cap')
                        ->color('info')
                        ->modalHeading('Atur kelas siswa terpilih')
                        ->modalDescription('Semua siswa yang dicentang dipindahkan ke kelas pilihan untuk tahun ajaran aktif. Riwayat kelas tahun lain tidak berubah.')
                        ->schema([
                            Select::make('classroom_id')
                                ->label('Kelas tujuan')
                                ->options(fn() => Classroom::query()->inActiveYear()->orderBy('name')->pluck('name', 'id')->all())
                                ->searchable()
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $year = AcademicYear::current();
                            $classroom = Classroom::find($data['classroom_id']);

                            if ($year === null || $classroom === null) {
                                Notification::make()->danger()->title('Tahun ajaran aktif atau kelas tidak ditemukan')->send();

                                return;
                            }

                            DB::transaction(function () use ($records, $classroom, $year): void {
                                foreach ($records as $student) {
                                    StudentEnrollment::updateOrCreate(
                                        ['student_id' => $student->id, 'academic_year_id' => $year->id],
                                        ['classroom_id' => $classroom->id, 'status' => EnrollmentStatus::Active->value],
                                    );
                                }
                            });

                            Notification::make()
                                ->success()
                                ->title("{$records->count()} siswa dipindahkan ke {$classroom->name}")
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('changeStatusBulk')
                        ->label('Ubah status')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->modalHeading('Ubah status siswa terpilih')
                        ->modalDescription('Status Lulus, Mutasi, atau DO menandai siswa sudah tidak aktif. Datanya tetap tersimpan, jadi riwayat tagihan tidak hilang.')
                        ->schema([
                            Select::make('status')
                                ->label('Status baru')
                                ->options(StudentStatus::options())
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $status = StudentStatus::from($data['status']);
                            $year = AcademicYear::current();

                            DB::transaction(function () use ($records, $status, $year): void {
                                foreach ($records as $student) {
                                    $student->update(['status' => $status->value]);

                                    if ($year !== null) {
                                        StudentEnrollment::query()
                                            ->where('student_id', $student->id)
                                            ->where('academic_year_id', $year->id)
                                            ->update(['status' => EnrollmentStatus::from($status->value)->value]);
                                    }
                                }
                            });

                            Notification::make()
                                ->success()
                                ->title("{$records->count()} siswa diubah menjadi {$status->label()}")
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
