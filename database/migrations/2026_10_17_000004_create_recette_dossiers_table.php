<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gestion des Recettes > Retrait diplôme / Frais de soutenance — demande
 * explicite du client, DEUX volets distincts mais à la structure strictement
 * identique (un dossier par année académique, contenant une liste de
 * fichiers en nombre libre — excel, pdf...) : une seule table partagée,
 * distinguée par la colonne `type`, plutôt que deux tables quasi identiques
 * — même raisonnement que App\Models\ReunionFichier::role, qui distingue
 * déjà deux natures de pièces dans une seule table.
 *
 * IMPORTANT (demande explicite du client, "a ne pas confondre avec ce qui
 * etait deja en place, aucune relation") : ces deux volets n'ont AUCUN lien
 * avec les mécanismes déjà existants par ailleurs dans la plateforme —
 * `statut_retrait`/`date_retrait` sur App\Models\Document (suivi RÉEL,
 * document par document, du retrait physique d'un diplôme/d'une attestation)
 * et les champs propres à App\Models\PvSoutenance. Ici, il s'agit
 * uniquement d'y déposer un ou plusieurs fichiers (typiquement un export
 * Excel) donnant une VUE D'ENSEMBLE des étudiants ayant payé pour retirer
 * leur diplôme / pour soutenir, sans aucune relation en base avec ces
 * mécanismes existants.
 *
 * `restrictOnDelete()` sur annee_academique_id, SoftDeletes : même
 * raisonnement que recette_scolarites ci-dessus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recette_dossiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('annee_academique_id')->constrained('annees_academiques')->restrictOnDelete();
            // 'retrait_diplome' | 'frais_soutenance' — voir App\Models\RecetteDossier::TYPE_*.
            $table->string('type', 30);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Un seul dossier par combinaison année académique / type.
            $table->unique(['annee_academique_id', 'type'], 'recette_dossiers_annee_type_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recette_dossiers');
    }
};
