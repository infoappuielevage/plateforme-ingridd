<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nouvelle structure dédiée à la gestion des PV de délibération (demande
 * explicite du client, en remplacement du simple "un document = un fichier"
 * utilisé jusqu'ici pour ce type — voir App\Models\TypeDocument, code
 * PV_DELIBERATION). Les anciens documents PV_DELIBERATION déjà en base
 * restent inchangés (choix explicite du client, pas de migration de
 * données) : cette table ne concerne que les nouvelles sessions créées
 * depuis Gestion > PV de délibération.
 *
 * Une "session de délibération" = une combinaison année académique /
 * session (1ère ou 2ème) / cycle (Licence/Master/Doctorat) / filière — les
 * champs demandés par le client. Les fichiers eux-mêmes (notes d'information
 * étudiants/enseignants, PV par semestre ou par année de thèse) sont des
 * cases séparées, voir session_deliberation_fichiers ci-dessous : chaque
 * case pointe vers un App\Models\Document réel (upload progressif — le
 * client a explicitement choisi de ne pas exiger tous les fichiers d'un
 * coup à la création).
 *
 * `restrictOnDelete()` sur annee_academique_id/filiere_id (comme
 * type_document_id sur documents, voir 2026_09_20_000004) : ce sont des
 * champs obligatoires d'identification de la session, pas des rattachements
 * facultatifs — supprimer une année/filière encore utilisée par une session
 * de délibération doit être bloqué plutôt que de laisser une session
 * orpheline.
 *
 * SoftDeletes : même convention que les autres tables métier de premier
 * niveau (Document, Filiere, Niveau...) — "désactivé/corbeillé, jamais
 * supprimé" par défaut.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sessions_deliberation', function (Blueprint $table) {
            $table->id();
            $table->foreignId('annee_academique_id')->constrained('annees_academiques')->restrictOnDelete();
            // 'premiere' | 'deuxieme' — voir App\Models\SessionDeliberation::SESSION_*.
            $table->string('session', 20);
            // 'licence' | 'master' | 'doctorat' — voir App\Models\SessionDeliberation::CYCLE_*.
            $table->string('cycle', 20);
            $table->foreignId('filiere_id')->constrained('filieres')->restrictOnDelete();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Une seule session de délibération par combinaison — évite les
            // doublons accidentels (ex. deux fois "2025-2026 / 1ère session /
            // Licence / Informatique").
            $table->unique(
                ['annee_academique_id', 'session', 'cycle', 'filiere_id'],
                'sessions_deliberation_combinaison_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions_deliberation');
    }
};
