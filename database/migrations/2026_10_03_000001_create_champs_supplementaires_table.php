<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Référentiel des "champs supplémentaires" — demande explicite du client :
 * pouvoir ajouter, depuis l'administration et SANS toucher au code, un
 * document propre à déposer sur les PV de délibération et/ou les Relevés de
 * notes (ex. un "Certificat de scolarité" ou une pièce propre à un cycle).
 * Voir App\Models\ChampSupplementaire pour le détail de chaque colonne, et
 * les deux migrations suivantes (ajout de `champ_supplementaire_id` sur
 * session_deliberation_fichiers / session_releve_fichiers) pour la façon
 * dont une case s'y rattache.
 *
 * `actif` + SoftDeletes, comme les autres référentiels (Filière, Niveau...,
 * voir App\Http\Controllers\ReferentielController) : `actif` masque le champ
 * des listes déroulantes/de la pré-création automatique sans rien supprimer,
 * la corbeille (SoftDeletes) reste bloquée tant qu'au moins un document est
 * déjà déposé pour ce champ (voir ChampSupplementaireController::destroy()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('champs_supplementaires', function (Blueprint $table) {
            $table->id();
            $table->string('libelle', 150);
            // S'applique au PV de délibération / au Relevé de notes — un même
            // champ peut cocher les deux, l'un, ou (temporairement, via
            // "actif" ci-dessous) aucun effet tant qu'il reste décoché.
            $table->boolean('pour_pv')->default(false);
            $table->boolean('pour_releve')->default(false);
            // Sous-ensemble de ['licence','master','doctorat'] — voir
            // App\Models\SessionDeliberation::CYCLES (mêmes valeurs que
            // App\Models\SessionReleve::CYCLES, les deux partagent ce
            // découpage).
            $table->json('cycles')->nullable();
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('champs_supplementaires');
    }
};
