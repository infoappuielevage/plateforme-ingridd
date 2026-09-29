<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

/**
 * Historique complet (créations/modifications/suppressions + connexions) —
 * voir App\Models\Concerns\Auditable (posé sur les modèles métier) et
 * App\Listeners\JournaliserActiviteConnexion (connexions).
 *
 * `avant`/`apres` : uniquement les colonnes réellement modifiées (jamais le
 * mot de passe ni le remember_token — voir Auditable::colonnesExclues()).
 *
 * Lecture seule dans l'application : aucune Resource ne permet de créer,
 * modifier ou supprimer une ligne manuellement (voir AuditLogResource).
 *
 * `Prunable` : purge automatique (voir routes/console.php, `model:prune`
 * planifié chaque jour) des entrées plus anciennes que la durée définie dans
 * Administration -> Paramètres (voir App\Models\Parametre::retention_audit_jours,
 * 730 jours/2 ans par défaut) — modifiable après le déploiement sans toucher au code.
 */
class AuditLog extends Model
{
    use Prunable;

    public $timestamps = true;

    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'user_name',
        'action',
        'model_type',
        'model_id',
        'resume',
        'avant',
        'apres',
    ];

    protected function casts(): array
    {
        return [
            'avant' => 'array',
            'apres' => 'array',
        ];
    }

    public function prunable()
    {
        $jours = Parametre::actuel()->retention_audit_jours ?? 730;

        return static::query()->where('created_at', '<', now()->subDays($jours));
    }
}
