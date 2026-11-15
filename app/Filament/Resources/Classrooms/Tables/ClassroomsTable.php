<?php

namespace App\Filament\Resources\Classrooms\Tables;

use App\Models\AcademicYear;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ClassroomsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Kelas')->searchable()->sortable(),
                TextColumn::make('grade')->label('Tingkat')->sortable(),
                TextColumn::make('major.code')->label('Jurusan')->badge()->sortable(),
                TextColumn::make('academicYear.code')->label('Tahun ajaran')->sortable(),
                TextColumn::make('enrollments_count')->counts('enrollments')->label('Jumlah siswa')->sortable(),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('academic_year_id')
                    ->label('Tahun ajaran')
                    ->relationship('academicYear', 'code')
                    ->default(fn() => AcademicYear::current()?->id),
                SelectFilter::make('grade')
                    ->label('Tingkat')
                    ->options([10 => 'X', 11 => 'XI', 12 => 'XII']),
                SelectFilter::make('major_id')
                    ->label('Jurusan')
                    ->relationship('major', 'name'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
