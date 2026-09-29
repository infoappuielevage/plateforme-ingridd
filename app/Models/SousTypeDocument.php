<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sous-catégorie d'un type de document (ex. le type "Attestation" a pour
 * sous-types "Succès"/"Vacation"/"Inscription"...), gérée par type depuis
 * Administration > Référentiels > Types de documents (voir
 * TypeDocumentResource\RelationManagers\SousTypesRelationManager) — sur
 * demande du client, pour des listes ouvertes et extensibles (ex.
 * "Bordereau", dont la liste d'organismes s'allonge au fil du temps) plutôt
 * que figées dans le code. Voir aussi DocumentResource::form(), où le champ
 * "Sous-type" ne s'affiche que si le type sélectionné en a au moins un actif.
 *
 * Pas de corbeille ici (contrairement aux référentiels de premier niveau -
 * TypeDocument, Filiere, Niveau, AnneeAcademique) :
 * `documents.sous_type_document_id` est facultatif et ->nullOnDelete(), donc
 * une suppression ne peut jamais laisser un document dans un état invalide —
 * au pire son sous-type redevient vide. Si un vrai suivi
 * "restaurer/corbeille" devient nécessaire ici aussi, le même principe que
 * TypeDocument pourra être repris (voir son docblock).
 */
class SousTypeDocument extends Model
{
    use Auditable, HasFactory;

    /**
     * Convention Eloquent par défaut pour ce modèle : "sous_type_documents"
     * (seul le dernier mot est mis au pluriel). La migration crée
     * "sous_types_documents" (le mot "type" au pluriel, plus naturel à la
     * lecture en français) — d'où ce `$table` explicite, sans quoi Eloquent
     * chercherait une table qui n'existe pas ("no such table"). Même piège
     * que documenté dans la migration de `type_documents` pour TypeDocument.
     */
    protected $table = 'sous_types_documents';

    protected $fillable = [
        'type_document_id',
        'libelle',
        'ordre',
        'actif',
    ];

    protected function casts(): array
    {
        return [
            'ordre' => 'integer',
            'actif' => 'boolean',
        ];
    }

    public function typeDocument(): BelongsTo
    {
        return $this->belongsTo(TypeDocument::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }
}
