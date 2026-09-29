<?php

namespace App\Filament\Resources\NiveauResource\Pages;

use App\Filament\Resources\NiveauResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditNiveau extends EditRecord
{
    protected static string $resource = NiveauResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn () => $this->record->documents()->doesntExist()),
        ];
    }
}
