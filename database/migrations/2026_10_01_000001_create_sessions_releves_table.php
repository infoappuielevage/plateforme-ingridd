<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gestion des Relevés de notes — structure dédiée, EXACTEMENT la même forme
 * que "sessions_deliberation" (voir 2026_09_30_000023_create_sessions_deliberation_table.php
 * pour le contexte complet) : demande explicite du client
 * ("même chose que pour le PV de délibération") une fois cette dernière
 * validée. Un "session de relevé" = année académique / session (1ère ou
 * 2ème) / cycle (Licence/Master/Doctorat) / filière, avec des cases pour les
 * fichiers (voir session_releve_fichiers ci-dessous). Table séparée de
 * "sessions_deliberation" plutôt que réutilisée : ce sont deux types de
 * document distincts (RELEVE_NOTES vs PV_DELIBERATION, voir TypeDocumentSeeder),
 * une session de relevé et une session de délibération pour la même
 * combinaison année/session/cycle/filière doivent pouvoir exister
 * indépendamment l'une de l'autre.
 *
 * Les anciens documents RELEVE_NOTES déjà en base (avant cet écran) restent
 * inchangés, même choix explicite du client que pour PV_DELIBERATION : pas
 * de migration de données.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sessions_releves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('annee_academique_id')->constrained('annees_academiques')->restrictOnDelete();
            // 'premiere' | 'deuxieme' — voir App\Models\SessionReleve::SESSION_*.
            $table->string('session', 20);
            // 'licence' | 'master' | 'doctorat' — voir App\Models\SessionReleve::CYCLE_*.
            $table->string('cycle', 20);
            $table->foreignId('filiere_id')->constrained('filieres')->restrictOnDelete();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['annee_academique_id', 'session', 'cycle', 'filiere_id'],
                'sessions_releves_combinaison_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions_releves');
    }
};
