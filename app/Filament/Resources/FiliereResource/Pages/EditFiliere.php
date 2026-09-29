<?php

namespace App\Filament\Resources\FiliereResource\Pages;

use App\Filament\Resources\FiliereResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFiliere extends EditRecord
{
    protected static string $resource = FiliereResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn () => $this->record->documents()->doesntExist()),
        ];
    }
}
