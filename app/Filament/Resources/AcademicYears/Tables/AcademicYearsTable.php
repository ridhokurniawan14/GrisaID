<?php

namespace App\Filament\Resources\AcademicYears\Tables;

use App\Models\AcademicYear;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class AcademicYearsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('Kode')->searchable()->sortable(),
                TextColumn::make('name')->label('Nama')->searchable(),
                TextColumn::make('start_date')->label('Mulai')->date('d M Y')->sortable(),
                TextColumn::make('end_date')->label('Selesai')->date('d M Y')->sortable(),
                ToggleColumn::make('is_active')
                    ->label('Aktif')
                    ->onColor('success')
                    ->offColor('gray')
                    // Yang sedang aktif tidak bisa dimatikan langsung: aktifkan tahun ajaran lain sebagai gantinya.
                    ->disabled(fn(AcademicYear $record): bool => $record->is_active)
                    ->tooltip(fn(AcademicYear $record): ?string => $record->is_active
                        ? 'Tahun ajaran aktif. Aktifkan tahun ajaran lain untuk menggantinya.'
                        : null)
                    // Menyalakan satu tahun ajaran otomatis mematikan yang lain (dijaga di model & database).
                    ->updateStateUsing(function (AcademicYear $record, $state): bool {
                        if ($state) {
                            $record->activate();
                        }

                        return (bool) $state;
                    })
                    ->afterStateUpdated(fn($livewire) => $livewire->js('window.location.reload()')),
            ])
            ->defaultSort('start_date', 'desc')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->disabled(fn(AcademicYear $record): bool => $record->deletionBlockedReason() !== null)
                    ->tooltip(fn(AcademicYear $record): ?string => $record->deletionBlockedReason()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
