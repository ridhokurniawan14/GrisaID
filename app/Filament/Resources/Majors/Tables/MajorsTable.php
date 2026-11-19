<?php

namespace App\Filament\Resources\Majors\Tables;

use App\Models\Major;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class MajorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('Kode')->badge()->searchable()->sortable(),
                TextColumn::make('name')->label('Nama jurusan')->searchable(),
                ToggleColumn::make('is_active')
                    ->label('Aktif')
                    ->onColor('success')
                    ->offColor('gray'),
            ])
            ->defaultSort('code')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->disabled(fn(Major $record): bool => $record->deletionBlockedReason() !== null)
                    ->tooltip(fn(Major $record): ?string => $record->deletionBlockedReason()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
