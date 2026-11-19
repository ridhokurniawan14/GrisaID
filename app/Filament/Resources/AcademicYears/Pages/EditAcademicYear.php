<?php

namespace App\Filament\Resources\AcademicYears\Pages;

use App\Filament\Resources\AcademicYears\AcademicYearResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAcademicYear extends EditRecord
{
    protected static string $resource = AcademicYearResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
    protected function getRedirectUrl(): string
    {
        return \App\Filament\Resources\AcademicYears\AcademicYearResource::getUrl('index');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return "Tahun ajaran {$this->getRecord()->code} berhasil diperbarui";
    }
}
