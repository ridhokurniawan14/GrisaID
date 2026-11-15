<?php

namespace App\Filament\Resources\Classrooms\Schemas;

use App\Models\AcademicYear;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ClassroomForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('academic_year_id')
                    ->label('Tahun ajaran')
                    ->relationship('academicYear', 'code')
                    ->default(fn() => AcademicYear::current()?->id)
                    ->required(),
                Select::make('major_id')
                    ->label('Jurusan')
                    ->relationship('major', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('grade')
                    ->label('Tingkat')
                    ->options([10 => 'X (10)', 11 => 'XI (11)', 12 => 'XII (12)'])
                    ->required(),
                TextInput::make('name')
                    ->label('Nama kelas / rombel')
                    ->placeholder('X KULINER 1')
                    ->helperText('Samakan dengan nama rombel di Dapodik agar impor mengenali kelas ini.')
                    ->required()
                    ->maxLength(50)
                    ->dehydrateStateUsing(fn(?string $state): ?string => $state === null ? null : strtoupper(preg_replace('/\s+/', ' ', trim($state)))),
                TextInput::make('group_no')
                    ->label('Nomor rombel')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(20),
            ]);
    }
}
