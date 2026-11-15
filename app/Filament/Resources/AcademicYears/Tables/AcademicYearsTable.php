<?php

namespace App\Filament\Resources\AcademicYears\Tables;

use App\Models\AcademicYear;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
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
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->defaultSort('start_date', 'desc')
            ->recordActions([
                Action::make('activate')
                    ->label('Jadikan aktif')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Jadikan tahun ajaran ini aktif?')
                    ->modalDescription('Tahun ajaran lain otomatis dinonaktifkan. "Kelas saat ini" semua siswa akan mengikuti tahun ajaran yang aktif.')
                    ->hidden(fn(AcademicYear $record): bool => $record->is_active)
                    ->action(function (AcademicYear $record): void {
                        $record->activate();

                        Notification::make()
                            ->title("Tahun ajaran {$record->code} sekarang aktif")
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
            ]);
    }
}
