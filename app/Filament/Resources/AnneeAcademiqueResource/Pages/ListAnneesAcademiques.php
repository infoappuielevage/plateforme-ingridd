<?php

namespace App\Filament\Resources\AnneeAcademiqueResource\Pages;

use App\Filament\Resources\AnneeAcademiqueResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAnneesAcademiques extends ListRecords
{
    protected static string $resource = AnneeAcademiqueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
