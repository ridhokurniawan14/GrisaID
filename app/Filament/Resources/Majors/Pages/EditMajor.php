<?php

namespace App\Filament\Resources\Majors\Pages;

use App\Filament\Resources\Majors\MajorResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMajor extends EditRecord
{
    protected static string $resource = MajorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
    protected function getRedirectUrl(): string
    {
        return \App\Filament\Resources\Majors\MajorResource::getUrl('index');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return "Jurusan {$this->getRecord()->name} berhasil diperbarui";
    }
}
