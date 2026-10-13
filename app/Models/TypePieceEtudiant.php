<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Un type de pièce attendue dans un dossier étudiant (ex. "CNIB", "Relevés
 * de notes"...) — voir le docblock de la migration
 * 2026_10_09_000001_create_types_pieces_etudiants_table.php pour le contexte
 * complet de cette refonte (liste fixe gérée en administration, demande
 * explicite du client).
 *
 * `recurrente` détermine OÙ la case correspondante est rattachée : voir
 * App\Http\Controllers\EtudiantController::creerCasesIdentite() (recurrente
 * = false, une case par étudiant) et ::creerCasesInscription() (recurrente =
 * true, une case par App\Models\EtudiantInscription).
 */
class TypePieceEtudiant extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $table = 'types_pieces_etudiants';

    protected $fillable = [
        'code',
        'libelle',
        'recurrente',
        'ordre',
        'actif',
    ];

    protected function casts(): array
    {
        return [
            'recurrente' => 'boolean',
            'ordre' => 'integer',
            'actif' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    /** Toutes les cases créées pour ce type, qu'elles soient remplies ou non. */
    public function fichiers(): HasMany
    {
        return $this->hasMany(EtudiantFichier::class);
    }

    /**
     * Ajoute une case vide pour ce type sur CHAQUE dossier étudiant déjà
     * existant (recurrente = false) ou sur CHAQUE inscription déjà existante
     * (recurrente = true) — demande explicite du client : un type ajouté (ou
     * réactivé) après coup doit aussi profiter aux dossiers déjà créés, pas
     * seulement aux nouveaux (voir aussi
     * App\Http\Controllers\EtudiantController::creerCasesIdentite()/
     * creerCasesInscription(), qui couvrent le cas des NOUVEAUX dossiers/
     * inscriptions). Ne retire JAMAIS une case existante, même si le type est
     * désactivé ensuite : une pièce déjà déposée ne doit jamais disparaître
     * automatiquement.
     *
     * Avant de créer une case vide, tente de RATTACHER une pièce déjà
     * déposée AVANT cette refonte (ancienne liste libre, voir le docblock de
     * App\Models\EtudiantFichier) dont le libellé saisi à l'époque
     * correspond exactement (insensible à la casse/aux espaces) à ce type —
     * pour ne jamais faire disparaître de la vue une pièce déjà fournie par
     * un étudiant, ni créer une case vide en double à côté.
     *
     * Méthode d'instance (plutôt que confinée à
     * App\Http\Controllers\TypePieceEtudiantController) pour pouvoir aussi
     * être appelée depuis database/seeders/TypePieceEtudiantSeeder.php : la
     * liste de départ est injectée par ce seeder plutôt que saisie à la main
     * depuis l'écran d'administration, mais doit profiter de la même
     * synchronisation rétroactive sur les dossiers réels déjà en place (ex.
     * la pièce CNIB déjà déposée par le client avant cette refonte).
     */
    public function synchroniserCasesRetroactives(): void
    {
        if (! $this->actif) {
            return;
        }

        if ($this->recurrente) {
            EtudiantInscription::query()->each(function (EtudiantInscription $inscription) {
                if (EtudiantFichier::query()
                    ->where('etudiant_inscription_id', $inscription->id)
                    ->where('type_piece_etudiant_id', $this->id)
                    ->exists()) {
                    return;
                }

                EtudiantFichier::create([
                    'etudiant_id' => $inscription->etudiant_id,
                    'numero' => $this->prochainNumero($inscription->etudiant_id),
                    'type_piece_etudiant_id' => $this->id,
                    'etudiant_inscription_id' => $inscription->id,
                    'document_id' => null,
                ]);
            });

            return;
        }

        Etudiant::query()->each(function (Etudiant $etudiant) {
            if (EtudiantFichier::query()
                ->where('etudiant_id', $etudiant->id)
                ->where('type_piece_etudiant_id', $this->id)
                ->whereNull('etudiant_inscription_id')
                ->exists()) {
                return;
            }

            $legacy = EtudiantFichier::query()
                ->where('etudiant_id', $etudiant->id)
                ->whereNull('type_piece_etudiant_id')
                ->whereNotNull('document_id')
                ->get()
                ->first(fn (EtudiantFichier $f) => mb_strtolower(trim((string) $f->getRawOriginal('libelle'))) === mb_strtolower(trim($this->libelle)));

            if ($legacy) {
                $legacy->update(['type_piece_etudiant_id' => $this->id]);

                return;
            }

            EtudiantFichier::create([
                'etudiant_id' => $etudiant->id,
                'numero' => $this->prochainNumero($etudiant->id),
                'type_piece_etudiant_id' => $this->id,
                'etudiant_inscription_id' => null,
                'document_id' => null,
            ]);
        });
    }

    /** Prochain numéro libre pour cet étudiant — simple compteur technique, voir le docblock de la migration qui a ajouté `type_piece_etudiant_id`. */
    protected function prochainNumero(int $etudiantId): int
    {
        return (int) EtudiantFichier::query()->where('etudiant_id', $etudiantId)->max('numero') + 1;
    }
}
