<?php

namespace App\Filament\Resources\NiveauResource\Pages;

use App\Filament\Resources\NiveauResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListNiveaux extends ListRecords
{
    protected static string $resource = NiveauResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
