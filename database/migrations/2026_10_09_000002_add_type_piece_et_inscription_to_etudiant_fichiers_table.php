<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fait passer les pièces du dossier étudiant d'une liste libre (un simple
 * `libelle` tapé à l'ajout) à des CASES rattachées au nouveau référentiel
 * App\Models\TypePieceEtudiant (voir 2026_10_09_000001_create_types_pieces_etudiants_table.php
 * pour le contexte complet de cette refonte) — même famille de changement
 * que 2026_10_03_000002_add_champ_supplementaire_id_to_session_deliberation_fichiers_table.php.
 *
 * `type_piece_etudiant_id` nullable + `nullOnDelete()` : une pièce déjà
 * déposée ne doit jamais disparaître si son type est supprimé définitivement
 * (bloqué de toute façon tant qu'une case l'utilise encore, voir
 * TypePieceEtudiantController::destroy()) — `libelle()` retombe alors sur
 * l'ancienne colonne `libelle`/le nom du fichier (voir App\Models\EtudiantFichier).
 *
 * `etudiant_inscription_id` nullable + `cascadeOnDelete()` : NULL pour une
 * pièce d'identité (recurrente = false, rattachée à l'étudiant pour tout son
 * parcours), renseigné pour une pièce récurrente (recurrente = true, une
 * case par année académique) — retirer une inscription entière (voir
 * EtudiantController::supprimerInscription()) retire alors avec elle ses
 * propres cases, pas celles des autres années.
 *
 * `libelle`/`numero` restent inchangées (pas de colonne retirée, pour ne
 * jamais perdre les pièces déjà déposées avant cette migration sur une base
 * existante) : `numero` continue de servir de simple compteur technique pour
 * satisfaire la contrainte NOT NULL/unique existante, l'affichage utilisant
 * désormais l'ordre du type (ou, pour une pièce "historique" sans type
 * rattaché, retombant sur l'ancien comportement) plutôt que ce numéro — voir
 * EtudiantController::creerCasesIdentite()/creerCasesInscription().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('etudiant_fichiers', function (Blueprint $table) {
            $table->foreignId('type_piece_etudiant_id')->nullable()->after('libelle')
                ->constrained('types_pieces_etudiants')->nullOnDelete();
            $table->foreignId('etudiant_inscription_id')->nullable()->after('type_piece_etudiant_id')
                ->constrained('etudiant_inscriptions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('etudiant_fichiers', function (Blueprint $table) {
            $table->dropForeign(['type_piece_etudiant_id']);
            $table->dropForeign(['etudiant_inscription_id']);
            $table->dropColumn(['type_piece_etudiant_id', 'etudiant_inscription_id']);
        });
    }
};
