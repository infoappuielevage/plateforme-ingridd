<?php

namespace App\Filament\Resources\AnneeAcademiqueResource\Pages;

use App\Filament\Resources\AnneeAcademiqueResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAnneeAcademique extends EditRecord
{
    protected static string $resource = AnneeAcademiqueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn () => $this->record->documents()->doesntExist()),
        ];
    }
}
