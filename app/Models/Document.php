<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\Formatage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * Entité "plate" (pas de relation polymorphe — contrairement à l'ancien
 * Document d'ingridd-platform, inutile ici puisqu'il n'y a pas d'étudiants/
 * soutenances à rattacher) : un document = un fichier stocké + ses métadonnées.
 *
 * `chemin` est le chemin réel sur le disque `documents` (voir
 * config/filesystems.php et routes/web.php pour le téléchargement contrôlé),
 * `nom_original` le nom du fichier tel qu'envoyé par l'utilisateur (affiché
 * partout dans l'interface — jamais `chemin`, qui contient un nom généré).
 *
 * `SoftDeletes` : une suppression "normale" met le document à la corbeille
 * (voir DocumentResource::table(), filtre "Corbeille" + actions Restaurer/
 * Supprimer définitivement) plutôt que de l'effacer tout de suite.
 * `booted()` ne supprime le fichier physique du disque `documents` que lors
 * d'une suppression **définitive** (`isForceDeleting()`) — sans cette
 * distinction, mettre un document à la corbeille effacerait quand même son
 * fichier, rendant la corbeille inutile pour restaurer quoi que ce soit.
 *
 * `filiere_id`/`niveau_id`/`annee_academique_id`/`semestre` sont
 * volontairement nullable : utiles pour les documents liés à une promotion
 * (listes d'étudiants, relevés de notes, PV de délibération...), mais un
 * document administratif générique n'a pas forcément de filière/niveau/
 * année/semestre. `annee_academique_id` est une liste déroulante (référentiel
 * AnneeAcademique, format "AAAA-AAAA") plutôt qu'une saisie libre, pour
 * éviter les doublons proches ("2025-2026" vs "2025 - 2026"). Voir
 * App\Filament\Resources\FiliereResource, NiveauResource et
 * AnneeAcademiqueResource pour la gestion de ces référentiels depuis
 * l'administration.
 *
 * `uploaded_by`/`modifie_par` : qui a ajouté / qui a modifié en dernier,
 * affichés directement sur le document (l'historique complet — Administration
 * > Historique — trace en plus chaque changement individuel, avec le détail
 * avant/après).
 *
 * `nom_titulaire`/`numero_diplome`/`date_delivrance`/`mention`/
 * `statut_retrait`/`date_retrait`/`retrait_enregistre_par_id` ne concernent
 * que les documents de type "Diplôme"/"Attestation" (voir
 * App\Support\TypesDiplome et DocumentResource, où ces champs ne sont
 * affichés/enregistrés que pour ces deux types) — nullable pour tous les
 * autres. `statut_retrait` suit si l'étudiant est venu récupérer son
 * diplôme/attestation physique ; voir le bouton "Marquer comme retiré" sur
 * DocumentResource::table() et les widgets de tableau de bord dédiés.
 *
 * `theme_memoire`/`date_soutenance`/`membres_jury` ne concernent que les
 * documents de type "PV de soutenance" (voir App\Support\TypesPvSoutenance) —
 * `nom_titulaire` et `mention` sont réutilisés tels quels pour ce type
 * plutôt que dupliqués (voir DocumentResource). Pas de suivi de retrait pour
 * ce type, contrairement aux diplômes/attestations.
 *
 * `sous_type_document_id` : sous-catégorie facultative du type choisi (voir
 * App\Models\SousTypeDocument), gérée par type depuis l'administration
 * plutôt que figée dans le code (ex. le type "Bordereau" a pour sous-types
 * BNSP/CNSS/Coris Banque..., une liste que le client fait grandir librement).
 * Nullable et ->nullOnDelete() : la suppression d'un sous-type ne peut donc
 * jamais laisser un document dans un état invalide.
 */
class Document extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    public const STATUT_RETRAIT_NON_RETIRE = 'non_retire';

    public const STATUT_RETRAIT_RETIRE = 'retire';

    protected static function booted(): void
    {
        static::deleting(function (self $document) {
            if ($document->isForceDeleting() && $document->chemin) {
                Storage::disk('documents')->delete($document->chemin);
            }
        });
    }

    protected $fillable = [
        'description',
        'type_document_id',
        'sous_type_document_id',
        'filiere_id',
        'niveau_id',
        'annee_academique_id',
        'semestre',
        'nom_titulaire',
        'numero_diplome',
        'date_delivrance',
        'mention',
        'statut_retrait',
        'date_retrait',
        'retrait_enregistre_par_id',
        'theme_memoire',
        'date_soutenance',
        'membres_jury',
        'chemin',
        'nom_original',
        'extension',
        'taille',
        'uploaded_by',
        'modifie_par',
    ];

    protected function casts(): array
    {
        return [
            'taille' => 'integer',
            'semestre' => 'integer',
            'date_delivrance' => 'date',
            'date_retrait' => 'date',
            'date_soutenance' => 'date',
            'deleted_at' => 'datetime',
        ];
    }

    public function typeDocument(): BelongsTo
    {
        return $this->belongsTo(TypeDocument::class);
    }

    public function sousTypeDocument(): BelongsTo
    {
        return $this->belongsTo(SousTypeDocument::class);
    }

    public function filiere(): BelongsTo
    {
        return $this->belongsTo(Filiere::class);
    }

    public function niveau(): BelongsTo
    {
        return $this->belongsTo(Niveau::class);
    }

    public function anneeAcademique(): BelongsTo
    {
        return $this->belongsTo(AnneeAcademique::class);
    }

    public function uploadePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function modifiePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'modifie_par');
    }

    public function retraitEnregistrePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'retrait_enregistre_par_id');
    }

    /** Libellé lisible du semestre (ex. "Semestre 1"), ou null si non renseigné. */
    public function semestreLibelle(): ?string
    {
        return $this->semestre ? "Semestre {$this->semestre}" : null;
    }

    public function estRetire(): bool
    {
        return $this->statut_retrait === self::STATUT_RETRAIT_RETIRE;
    }

    /** Libellé lisible du statut de retrait, ou null si ce document n'est pas concerné (pas un diplôme/attestation). */
    public function statutRetraitLibelle(): ?string
    {
        return match ($this->statut_retrait) {
            self::STATUT_RETRAIT_RETIRE => 'Retiré',
            self::STATUT_RETRAIT_NON_RETIRE => 'Non retiré',
            default => null,
        };
    }

    public function tailleFormatee(): string
    {
        return Formatage::octets($this->taille ?? 0);
    }
}
