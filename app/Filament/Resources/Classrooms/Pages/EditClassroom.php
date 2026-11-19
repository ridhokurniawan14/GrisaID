<?php

namespace App\Filament\Resources\Classrooms\Pages;

use App\Filament\Resources\Classrooms\ClassroomResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditClassroom extends EditRecord
{
    protected static string $resource = ClassroomResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
    protected function getRedirectUrl(): string
    {
        return \App\Filament\Resources\Classrooms\ClassroomResource::getUrl('index');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return "Kelas {$this->getRecord()->name} berhasil diperbarui";
    }
}
