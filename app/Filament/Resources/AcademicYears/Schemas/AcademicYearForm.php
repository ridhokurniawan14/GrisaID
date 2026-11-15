<?php

namespace App\Filament\Resources\AcademicYears\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class AcademicYearForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label('Kode')
                    ->placeholder('2025/2026')
                    ->required()
                    ->maxLength(9)
                    ->regex('/^\d{4}\/\d{4}$/')
                    ->unique(ignoreRecord: true),
                TextInput::make('name')
                    ->label('Nama')
                    ->placeholder('Tahun Ajaran 2025/2026')
                    ->required()
                    ->maxLength(255),
                DatePicker::make('start_date')
                    ->label('Tanggal mulai')
                    ->required(),
                DatePicker::make('end_date')
                    ->label('Tanggal selesai')
                    ->required()
                    ->after('start_date'),
                // Status aktif diubah lewat tombol "Jadikan aktif" di daftar (menjamin hanya satu yang aktif).
                Toggle::make('is_active')
                    ->label('Aktif')
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('Gunakan tombol "Jadikan aktif" di daftar tahun ajaran.'),
            ]);
    }
}
