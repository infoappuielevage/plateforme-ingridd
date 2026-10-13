<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Un contrat (Partenariat, Prestation de service, Bail/location...) — voir
 * le docblock de la migration 2026_10_23_000002_create_contrats_table.php
 * pour le détail complet des champs. Un enregistrement = une catégorie
 * (App\Models\TypeContrat) + les informations propres au contrat lui-même,
 * créé À LA DEMANDE (formulaire "Nouveau contrat", voir
 * App\Http\Controllers\ContratController::store()) — contrairement à
 * App\Models\Depense (une ligne par année académique × catégorie, créée
 * automatiquement).
 *
 * Tous les champs restent modifiables après création (voir
 * ContratController::update()) : rien ici ne détermine un nombre de cases à
 * figer, contrairement aux PV de délibération/Relevés de notes.
 */
class Contrat extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    public const STATUT_ACTIF = 'actif';

    public const STATUT_EXPIRE = 'expire';

    public const STATUT_RESILIE = 'resilie';

    public const STATUT_EN_NEGOCIATION = 'en_negociation';

    /** Libellé affiché par statut — voir statutLibelle() ci-dessous. Liste fermée (pas un référentiel ouvert) : ces 4 valeurs ne sont pas appelées à changer. */
    public const STATUTS = [
        self::STATUT_ACTIF => 'Actif',
        self::STATUT_EXPIRE => 'Expiré',
        self::STATUT_RESILIE => 'Résilié',
        self::STATUT_EN_NEGOCIATION => 'En négociation',
    ];

    protected $table = 'contrats';

    protected $fillable = [
        'type_contrat_id',
        'partenaire_prestataire',
        'objet',
        'montant',
        'date_signature',
        'date_debut',
        'date_fin',
        'statut',
        'responsable_id',
        'contact_nom',
        'contact_email',
        'contact_telephone',
        'observations',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'montant' => 'decimal:2',
            'date_signature' => 'date',
            'date_debut' => 'date',
            'date_fin' => 'date',
            'deleted_at' => 'datetime',
        ];
    }

    public function typeContrat(): BelongsTo
    {
        return $this->belongsTo(TypeContrat::class);
    }

    /** L'agent INGRIDD référent sur ce contrat — nullable, voir le docblock de la migration. */
    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    public function creePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function fichiers(): HasMany
    {
        return $this->hasMany(ContratFichier::class);
    }

    public function statutLibelle(): string
    {
        return self::STATUTS[$this->statut] ?? $this->statut;
    }

    /**
     * Classe de badge Bootstrap par statut — même convention de couleurs que
     * le reste de l'application (vert = actif/positif, rouge = expiré/
     * problème, gris = résilié/clos, orange = en cours/en attente).
     */
    public function statutBadgeClass(): string
    {
        return match ($this->statut) {
            self::STATUT_ACTIF => 'text-bg-success',
            self::STATUT_EXPIRE => 'text-bg-danger',
            self::STATUT_RESILIE => 'text-bg-secondary',
            self::STATUT_EN_NEGOCIATION => 'text-bg-warning',
            default => 'text-bg-secondary',
        };
    }

    /**
     * `statut` est saisi MANUELLEMENT (voir le docblock de la migration) :
     * ce champ calculé signale simplement une INCOHÉRENCE possible —
     * `date_fin` déjà dépassée alors que le statut affiche encore "Actif" —
     * sans jamais modifier `statut` lui-même. Sert uniquement à afficher un
     * petit avertissement dans la liste/la fiche (voir les vues), pour que
     * l'utilisateur pense à mettre le statut à jour lui-même.
     */
    public function echeanceDepassee(): bool
    {
        return $this->statut === self::STATUT_ACTIF
            && $this->date_fin !== null
            && $this->date_fin->isPast();
    }

    public function montantFormate(): ?string
    {
        if ($this->montant === null) {
            return null;
        }

        return number_format((float) $this->montant, 0, ',', ' ').' FCFA';
    }

    /** Dates lisibles "du .. au .." / "le .." / "Durée indéterminée" — pour la liste et la fiche. */
    public function periodeLibelle(): string
    {
        if ($this->date_debut && $this->date_fin) {
            return 'Du '.$this->date_debut->format('d/m/Y').' au '.$this->date_fin->format('d/m/Y');
        }

        if ($this->date_debut) {
            return 'Depuis le '.$this->date_debut->format('d/m/Y').' — durée indéterminée';
        }

        if ($this->date_fin) {
            return 'Jusqu\'au '.$this->date_fin->format('d/m/Y');
        }

        return 'Non précisée';
    }
}
