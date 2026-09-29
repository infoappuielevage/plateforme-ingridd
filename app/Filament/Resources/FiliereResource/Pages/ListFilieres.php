<?php

namespace App\Filament\Resources\FiliereResource\Pages;

use App\Filament\Resources\FiliereResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFilieres extends ListRecords
{
    protected static string $resource = FiliereResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
