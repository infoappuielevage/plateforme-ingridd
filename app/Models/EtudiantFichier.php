<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une case du dossier d'un App\Models\Etudiant — voir le docblock de la
 * migration 2026_10_09_000002_add_type_piece_et_inscription_to_etudiant_fichiers_table.php
 * pour le détail complet de la refonte (liste fixe pilotée par
 * App\Models\TypePieceEtudiant, demande explicite du client). Pas de
 * SoftDeletes, comme les autres tables de "cases" du projet.
 *
 * `type_piece_etudiant_id`/`etudiant_inscription_id` nullable : une pièce
 * créée AVANT cette refonte (ancienne liste libre) peut n'avoir ni l'un ni
 * l'autre, auquel cas elle retombe sur l'ancienne colonne `libelle` — voir
 * libelle() ci-dessous. Une pièce "d'identité" (type non récurrent) a
 * `etudiant_inscription_id` à null ; une pièce récurrente (ex. relevé de
 * notes) le renseigne avec l'inscription (l'année) concernée.
 */
class EtudiantFichier extends Model
{
    use HasFactory;

    protected $table = 'etudiant_fichiers';

    protected $fillable = [
        'etudiant_id',
        'numero',
        'libelle',
        'type_piece_etudiant_id',
        'etudiant_inscription_id',
        'document_id',
    ];

    protected function casts(): array
    {
        return [
            'numero' => 'integer',
        ];
    }

    public function etudiant(): BelongsTo
    {
        return $this->belongsTo(Etudiant::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /** Le type de pièce attendu (voir App\Models\TypePieceEtudiant) — absent uniquement pour une pièce créée avant cette refonte. */
    public function typePieceEtudiant(): BelongsTo
    {
        return $this->belongsTo(TypePieceEtudiant::class);
    }

    /** Uniquement renseigné pour une pièce récurrente (voir App\Models\TypePieceEtudiant::$recurrente). */
    public function etudiantInscription(): BelongsTo
    {
        return $this->belongsTo(EtudiantInscription::class);
    }

    /** Une case pré-créée (voir App\Http\Controllers\EtudiantController::creerCasesIdentite()/creerCasesInscription()) commence toujours vide — c'est ce champ qui détermine si elle affiche un lien de téléchargement ou un formulaire de dépôt (voir gestion/etudiants/_fichier.blade.php). */
    public function estRemplie(): bool
    {
        return $this->document_id !== null;
    }

    /**
     * Le libellé du type de pièce rattaché (ex. "CNIB", "Relevés de notes"),
     * géré depuis Référentiels > Types de pièces (dossier étudiant) — voir
     * App\Models\TypePieceEtudiant. À défaut (pièce créée avant cette
     * refonte, voir le docblock de la classe), retombe sur l'ancien libellé
     * saisi à l'ajout puis, en dernier recours, sur le nom original du
     * fichier déposé — jamais de texte vide dans la liste.
     */
    public function libelle(): string
    {
        if ($this->typePieceEtudiant) {
            return $this->typePieceEtudiant->libelle;
        }

        return $this->libelle ?: ($this->document?->nom_original ?? 'Pièce du dossier');
    }
}
