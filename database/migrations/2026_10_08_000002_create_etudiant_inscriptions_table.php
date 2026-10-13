<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * L'historique des inscriptions d'un étudiant (voir
 * 2026_10_08_000001_create_etudiants_table.php) — UNE ligne par année
 * académique où l'étudiant est inscrit, avec son niveau et sa filière pour
 * CETTE année précise. Demande explicite du client : "un étudiant peut être
 * inscrit cette année en Licence 1 et l'année prochaine en Licence 2" — un
 * simple champ `niveau_id` directement sur `etudiants` aurait écrasé l'année
 * précédente à chaque changement, perdant tout l'historique.
 *
 * `niveau_id`/`filiere_id` réutilisent tels quels les référentiels existants
 * App\Models\Niveau (L1...M2, voir NiveauSeeder — correspond exactement à la
 * liste "Licence 1, Licence 2, Licence 3, Master 1, Master 2" demandée par
 * le client) et App\Models\Filiere, plutôt que d'inventer une nouvelle
 * liste : aucune des deux ne se prête à l'ambiguïté "niveau" rencontrée sur
 * App\Models\PvSoutenance (voir son docblock, un cycle BTS/Licence/Master/
 * Doctorat sans rapport avec L1...M2).
 *
 * Les trois FK sont en `restrictOnDelete()` (champs obligatoires
 * d'identification de l'inscription, jamais facultatifs) — `etudiant_id` en
 * `cascadeOnDelete()` en revanche : une ligne d'historique n'a aucun sens
 * indépendamment de son étudiant (même raisonnement que les tables de
 * "cases" existantes, ex. reunion_fichiers).
 *
 * `unique(['etudiant_id', 'annee_academique_id'])` : un étudiant n'a qu'UNE
 * seule inscription par année académique (niveau/filière déterminés une
 * fois par an) — corriger une inscription existante se fait en la modifiant,
 * pas en en ajoutant une seconde pour la même année.
 *
 * Pas de SoftDeletes : comme les autres tables de "cases"/historique du
 * projet (ex. reunion_fichiers), une ligne retirée l'est définitivement —
 * rien à restaurer indépendamment de l'étudiant lui-même.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('etudiant_inscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etudiant_id')->constrained('etudiants')->cascadeOnDelete();
            $table->foreignId('annee_academique_id')->constrained('annees_academiques')->restrictOnDelete();
            $table->foreignId('niveau_id')->constrained('niveaux')->restrictOnDelete();
            $table->foreignId('filiere_id')->constrained('filieres')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['etudiant_id', 'annee_academique_id'], 'etudiant_inscriptions_annee_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('etudiant_inscriptions');
    }
};
