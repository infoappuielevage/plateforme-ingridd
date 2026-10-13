<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dossier étudiant — nouvelle structure dédiée (demande explicite du client,
 * "travaillons sur le dossier étudiant"), MÊME PRINCIPE que reunions/
 * pv_soutenances/sessions_deliberation/sessions_releves avant elle : un vrai
 * enregistrement dédié plutôt que de continuer à surcharger `documents`.
 *
 * `matricule` : identifiant unique de l'étudiant pour TOUT son parcours à
 * INGRIDD (demande explicite du client) — `unique()` classique, qui
 * continue de bloquer sa réutilisation même si la ligne est un jour mise à
 * la corbeille (SoftDeletes) : un matricule déjà attribué ne doit jamais
 * pouvoir être réassigné à un autre étudiant par erreur.
 *
 * AUCUN champ niveau/filière/année académique ICI : contrairement à un PV de
 * soutenance (un étudiant = une soutenance, figée dans le temps), le même
 * étudiant progresse d'année en année ("inscrit cette année en Licence 1 et
 * l'année prochaine en Licence 2", demande explicite du client) — ces trois
 * champs vivent donc sur une table séparée, un historique d'inscriptions par
 * année académique. Voir le docblock de
 * 2026_10_08_000002_create_etudiant_inscriptions_table.php.
 *
 * `created_by` nullable + `nullOnDelete()`, SoftDeletes : mêmes conventions
 * que App\Models\Reunion.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('etudiants', function (Blueprint $table) {
            $table->id();
            $table->string('matricule', 50)->unique();
            $table->string('nom', 150);
            $table->string('prenoms', 150);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('etudiants');
    }
};
