<?php

namespace App\Filament\Resources\Majors\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class MajorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label('Kode jurusan')
                    ->placeholder('TJKT')
                    ->required()
                    ->maxLength(10)
                    ->dehydrateStateUsing(fn(?string $state): ?string => $state === null ? null : strtoupper(trim($state)))
                    ->unique(ignoreRecord: true),
                TextInput::make('name')
                    ->label('Nama jurusan')
                    ->required()
                    ->maxLength(255),
                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
            ]);
    }
}
