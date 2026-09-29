<?php

namespace App\Filament\Concerns;

use App\Support\ResourceAccess;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Illuminate\Database\Eloquent\Collection;

/**
 * Actions groupées (sélection multiple de lignes) pour les référentiels
 * simples qui ont tous la même forme : Catégorie, Type de document, Filière,
 * Niveau, Année académique — un champ `actif`, une relation `documents()`,
 * et désormais une corbeille (SoftDeletes — voir le docblock de chaque
 * modèle). Centralisé ici pour ne pas dupliquer cette logique dans les 5
 * Resources concernées (elles se ressemblent déjà beaucoup, voir leurs
 * docblocks).
 *
 * "Supprimer" (= mettre à la corbeille) reprend le même garde-fou que
 * l'action individuelle déjà en place (DeleteAction ->visible(fn ($record)
 * => ! $record->trashed() && $record->documents()->...->doesntExist())) :
 * une ligne encore utilisée par au moins un document n'est jamais
 * corbeillée, même via une action groupée — les autres sélectionnées le
 * sont, avec un résumé en notification plutôt qu'une erreur brute de
 * contrainte de clé étrangère. Une ligne déjà corbeillée est simplement
 * ignorée (rien à refaire).
 *
 * ->withTrashed() : un document mis à la corbeille (voir Document::class,
 * SoftDeletes) existe toujours en base et pointe toujours vers sa
 * catégorie/son type/etc. — l'ignorer ferait croire qu'une ligne n'est plus
 * utilisée alors qu'un document seulement corbeillé y fait encore référence.
 * Grâce à ce garde-fou, un référentiel ne peut jamais être corbeillé tant
 * qu'il est utilisé : "Restaurer" et "Supprimer définitivement" n'ont donc
 * pas besoin du même garde-fou (une ligne corbeillée est, par construction,
 * déjà inutilisée).
 */
trait HasReferentielBulkActions
{
    protected static function actionsGroupeesReferentiel(): array
    {
        $cle = static::resourceAccessKey();

        return [
            BulkActionGroup::make([
                BulkAction::make('activer')
                    ->label('Activer')
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn () => ResourceAccess::can($cle, 'edit'))
                    ->action(fn (Collection $records) => $records->each->update(['actif' => true]))
                    ->deselectRecordsAfterCompletion(),
                BulkAction::make('desactiver')
                    ->label('Désactiver')
                    ->icon('heroicon-o-x-circle')
                    ->visible(fn () => ResourceAccess::can($cle, 'edit'))
                    ->action(fn (Collection $records) => $records->each->update(['actif' => false]))
                    ->deselectRecordsAfterCompletion(),
                BulkAction::make('supprimer')
                    ->label('Mettre à la corbeille')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn () => ResourceAccess::can($cle, 'delete'))
                    ->action(function (Collection $records) {
                        $supprimes = 0;
                        $ignores = 0;

                        foreach ($records as $record) {
                            if ($record->trashed()) {
                                continue;
                            }

                            if ($record->documents()->withTrashed()->doesntExist()) {
                                $record->delete();
                                $supprimes++;
                            } else {
                                $ignores++;
                            }
                        }

                        Notification::make()
                            ->title($supprimes.' mis(e) à la corbeille'.($ignores > 0 ? ', '.$ignores.' ignoré(s) car encore utilisé(s) par des documents' : ''))
                            ->success()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),
                BulkAction::make('restaurer')
                    ->label('Restaurer')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->visible(fn () => ResourceAccess::can($cle, 'delete'))
                    ->action(fn (Collection $records) => $records->each->restore())
                    ->deselectRecordsAfterCompletion(),
                BulkAction::make('supprimer_definitivement')
                    ->label('Supprimer définitivement')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn () => ResourceAccess::can($cle, 'delete'))
                    ->action(fn (Collection $records) => $records->each->forceDelete())
                    ->deselectRecordsAfterCompletion(),
            ]),
        ];
    }
}
