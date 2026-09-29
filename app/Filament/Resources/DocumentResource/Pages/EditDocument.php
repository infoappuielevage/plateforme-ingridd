<?php

namespace App\Filament\Resources\DocumentResource\Pages;

use App\Filament\Resources\DocumentResource;
use App\Models\Document;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class EditDocument extends EditRecord
{
    protected static string $resource = DocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Le champ `fichier` (FileUpload) doit être pré-rempli avec le chemin
        // déjà stocké pour afficher le fichier existant en édition.
        $data['fichier'] = $data['chemin'] ?? null;

        return $data;
    }

    /** Chemin de l'ancien fichier, mémorisé avant l'enregistrement pour pouvoir le supprimer ensuite — voir afterSave(). */
    protected ?string $ancienChemin = null;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Si l'utilisateur n'a pas remplacé le fichier, `fichier` contient
        // toujours l'ancien chemin (voir mutateFormDataBeforeFill) : rien à
        // recalculer. S'il l'a remplacé, on met à jour chemin/extension/taille
        // et on retient l'ancien chemin pour le supprimer après coup (voir
        // afterSave()) — sans ça, l'ancien fichier resterait orphelin sur le
        // disque indéfiniment.
        //
        // Cas particulier à ne pas casser : le champ `fichier` (FileUpload)
        // vérifie lui-même, à l'hydratation, que le fichier pré-rempli existe
        // bien sur le disque — s'il a disparu physiquement (fichier supprimé
        // manuellement, disque changé...), Filament vide silencieusement le
        // champ. `fichier` vaut alors null/vide sans que l'utilisateur ait
        // rien touché : on doit le traiter comme "pas de remplacement" et
        // conserver l'ancien chemin, plutôt que de le nullifier (la colonne
        // `chemin` est NOT NULL) ou d'appeler Storage::exists(null).
        $nouveauChemin = $data['fichier'] ?? null;

        if ($nouveauChemin && $nouveauChemin !== $this->record->chemin) {
            $this->ancienChemin = $this->record->chemin;
            $data['chemin'] = $nouveauChemin;
            $data['extension'] = pathinfo($data['nom_original'] ?? '', PATHINFO_EXTENSION);
            $data['taille'] = Storage::disk('documents')->exists($data['chemin'])
                ? Storage::disk('documents')->size($data['chemin'])
                : 0;
        } else {
            $data['chemin'] = $this->record->chemin;
        }

        unset($data['fichier']);

        // Qui a modifié ce document en dernier — affiché directement sur la
        // fiche (voir DocumentResource::table()) en complément de
        // l'historique complet (Administration > Historique).
        $data['modifie_par'] = Auth::id();

        // `statut_retrait` n'est présent dans $data que pour un diplôme/une
        // attestation (dehydrated(false) sinon — voir DocumentResource::form()).
        // On complète ici ce que le formulaire ne peut pas déduire seul :
        // - qui a enregistré le retrait, uniquement au moment où il bascule
        //   (pas à chaque modification d'un document déjà marqué "Retiré",
        //   pour ne pas écraser l'auteur d'origine) ;
        // - le nettoyage de la date/de l'auteur si on repasse à "Non retiré"
        //   (sinon ces deux colonnes resteraient à leur ancienne valeur,
        //   `date_retrait` n'étant pas dans $data quand le champ est masqué).
        if (array_key_exists('statut_retrait', $data)) {
            if ($data['statut_retrait'] === Document::STATUT_RETRAIT_RETIRE) {
                if ($this->record->statut_retrait !== Document::STATUT_RETRAIT_RETIRE) {
                    $data['retrait_enregistre_par_id'] = Auth::id();
                }
                $data['date_retrait'] = $data['date_retrait'] ?? now();
            } else {
                $data['date_retrait'] = null;
                $data['retrait_enregistre_par_id'] = null;
            }
        }

        return $data;
    }

    protected function afterSave(): void
    {
        if ($this->ancienChemin) {
            Storage::disk('documents')->delete($this->ancienChemin);
        }
    }
}
