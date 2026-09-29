<?php

namespace App\Filament\Resources\CorbeilleResource\Pages;

use App\Filament\Resources\CorbeilleResource;
use App\Models\Document;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListCorbeille extends ListRecords
{
    protected static string $resource = CorbeilleResource::class;

    /**
     * "Vider la corbeille" : supprime définitivement TOUS les documents
     * corbeillés en un clic, sans avoir à les sélectionner un par un — sur
     * demande du client, en plus de la sélection multiple déjà possible
     * via les actions groupées (voir CorbeilleResource::table()).
     *
     * Passe par chaque document individuellement (plutôt qu'un
     * Document::onlyTrashed()->forceDelete() en une requête) : c'est
     * Document::booted() (static::deleting) qui efface le fichier physique
     * de chacun, et App\Models\Concerns\Auditable qui journalise chaque
     * suppression dans l'historique — les deux exigent que l'événement
     * Eloquent "deleting"/"deleted" se déclenche pour chaque modèle, ce
     * qu'une suppression de masse en une requête SQL ne ferait pas.
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('vider_la_corbeille')
                ->label('Vider la corbeille')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Vider la corbeille')
                ->modalDescription('Tous les documents actuellement dans la corbeille seront supprimés définitivement, avec leur fichier. Cette action est irréversible.')
                ->modalSubmitActionLabel('Vider définitivement')
                ->visible(fn () => Document::onlyTrashed()->exists())
                ->action(function () {
                    $documents = Document::onlyTrashed()->get();

                    foreach ($documents as $document) {
                        $document->forceDelete();
                    }

                    Notification::make()
                        ->title($documents->count().' document(s) supprimé(s) définitivement')
                        ->success()
                        ->send();
                }),
        ];
    }
}
