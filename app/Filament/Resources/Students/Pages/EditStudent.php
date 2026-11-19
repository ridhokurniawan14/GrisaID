<?php

namespace App\Filament\Resources\Students\Pages;

use App\Filament\Resources\Students\StudentResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditStudent extends EditRecord
{
    protected static string $resource = StudentResource::class;

    protected function getHeaderActions(): array
    {
        // Sengaja tanpa "hapus permanen": riwayat siswa harus tetap ada untuk GrisaPay.
        return [
            DeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return StudentResource::getUrl('index');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return "Data siswa {$this->getRecord()->full_name} berhasil diperbarui";
    }
}
