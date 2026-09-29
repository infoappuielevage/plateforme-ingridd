<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use App\Support\Encodage;
use Illuminate\Support\Facades\Auth;

/**
 * Repris tel quel du projet ingridd-platform : trace automatiquement les
 * créations/modifications/suppressions d'un modèle dans `audit_logs`, sans
 * rien à faire dans chaque contrôleur/Resource — il suffit d'ajouter
 * `use Auditable;` sur le modèle.
 *
 * `Encodage::verSafeUtf8()` est appliqué à `avant`/`apres` avant l'écriture :
 * un texte mal encodé (import CSV, copier-coller depuis Word...) ferait
 * échouer silencieusement le cast JSON sans cette précaution.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($modele) {
            self::journaliser($modele, 'creation', null, $modele->attributesToArray());
        });

        static::updated(function ($modele) {
            $avant = array_intersect_key($modele->getOriginal(), $modele->getChanges());
            $apres = $modele->getChanges();

            if (empty($apres)) {
                return;
            }

            self::journaliser($modele, 'modification', $avant, $apres);
        });

        static::deleted(function ($modele) {
            // Un modèle avec SoftDeletes (ex. Document) passe par ici deux fois :
            // une première pour la mise à la corbeille (isForceDeleting() === false),
            // une seconde si l'utilisateur la supprime ensuite définitivement — les
            // deux sont distinguées dans l'historique. Un modèle sans SoftDeletes
            // n'a pas cette méthode : on traite alors toujours comme une suppression
            // définitive (comportement inchangé).
            $viaCorbeille = method_exists($modele, 'isForceDeleting') && ! $modele->isForceDeleting();

            self::journaliser($modele, $viaCorbeille ? 'mise_en_corbeille' : 'suppression', $modele->attributesToArray(), null);
        });

        // Contrairement à created/updated/deleted (définis génériquement par
        // Illuminate\Database\Eloquent\Concerns\HasEvents et donc disponibles
        // sur n'importe quel modèle), l'événement "restored" — comme
        // "restoring" — n'est enregistré que par le trait SoftDeletes
        // lui-même : appeler static::restored(...) sur un modèle qui ne
        // l'utilise pas lève un BadMethodCallException dès le boot du
        // modèle (ex. Role, AuditLog, User, qui n'ont pas de corbeille). On
        // ne branche donc cet événement que pour les modèles qui utilisent
        // réellement SoftDeletes — Document, et désormais aussi les
        // référentiels (TypeDocument, Filiere, Niveau, AnneeAcademique).
        if (in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive(static::class), true)) {
            static::restored(function ($modele) {
                self::journaliser($modele, 'restauration', null, $modele->attributesToArray());
            });
        }
    }

    protected static function journaliser($modele, string $action, ?array $avant, ?array $apres): void
    {
        $utilisateur = Auth::user();

        AuditLog::create([
            'user_id' => $utilisateur?->id,
            'user_name' => $utilisateur?->name,
            'action' => $action,
            'model_type' => get_class($modele),
            'model_id' => $modele->getKey(),
            'resume' => self::resumePour($modele, $action),
            'avant' => $avant ? Encodage::verSafeUtf8(self::sansColonnesExclues($avant)) : null,
            'apres' => $apres ? Encodage::verSafeUtf8(self::sansColonnesExclues($apres)) : null,
        ]);
    }

    protected static function sansColonnesExclues(array $donnees): array
    {
        return collect($donnees)
            ->except(self::colonnesExclues())
            ->toArray();
    }

    protected static function colonnesExclues(): array
    {
        return ['password', 'remember_token', 'created_at', 'updated_at'];
    }

    protected static function resumePour($modele, string $action): string
    {
        $nomModele = class_basename($modele);
        $libelle = match ($action) {
            'creation' => 'Création',
            'modification' => 'Modification',
            'suppression' => 'Suppression définitive',
            'mise_en_corbeille' => 'Mise à la corbeille',
            'restauration' => 'Restauration',
            default => ucfirst($action),
        };

        return "{$libelle} de {$nomModele} #{$modele->getKey()}";
    }
}
