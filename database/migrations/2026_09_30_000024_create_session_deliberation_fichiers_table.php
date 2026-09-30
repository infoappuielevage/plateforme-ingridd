<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Une "case" à remplir au sein d'une session de délibération (voir
 * 2026_09_30_000023_create_sessions_deliberation_table.php) : note
 * d'information étudiants, note d'information enseignants, ou un fichier
 * par semestre (Licence/Master, numérotation continue 1-10, voir
 * App\Support\Semestres) / par année de thèse (Doctorat, nombre réglable
 * depuis Administration > Paramètres, voir App\Models\Parametre::
 * nombre_annees_these_doctorat).
 *
 * Ces cases sont pré-créées automatiquement (voir
 * App\Http\Controllers\SessionDeliberationController::store()) à la
 * création de la session, TOUTES vides (`document_id` null) : l'utilisateur
 * les remplit ensuite progressivement, une à une (demande explicite du
 * client) plutôt que d'exiger tous les fichiers en même temps.
 *
 * `document_id` nullable + `nullOnDelete()` plutôt qu'un lien direct
 * fichier/métadonnées dupliqué ici : chaque case pointe vers un vrai
 * App\Models\Document (même disque, mêmes droits de téléchargement, même
 * corbeille SoftDeletes, même historique via Auditable) — voir le docblock
 * du contrôleur. Retirer le fichier d'une case ne supprime PAS la case
 * elle-même (elle redevient vide, réuploadable), d'où nullOnDelete() et non
 * cascadeOnDelete() : si le Document lié est un jour supprimé autrement que
 * par ce contrôleur, la case survit simplement vide plutôt que de
 * disparaître avec lui.
 *
 * `cascadeOnDelete()` sur session_deliberation_id (comme sous_types_documents
 * -> type_documents, voir 2026_09_21_000017) : une case n'a aucun sens sans
 * sa session parente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_deliberation_fichiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_deliberation_id')->constrained('sessions_deliberation')->cascadeOnDelete();
            // 'note_etudiants' | 'note_enseignants' | 'semestre' | 'annee_these'
            // — voir App\Models\SessionDeliberationFichier::ROLE_*.
            $table->string('role', 30);
            // Numéro du semestre (1-10) ou de l'année de thèse (1-N) — null
            // pour les deux rôles "note_*" (une seule case chacun, pas de
            // numérotation).
            $table->unsignedTinyInteger('numero')->nullable();
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['session_deliberation_id', 'role', 'numero'],
                'session_deliberation_fichiers_case_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_deliberation_fichiers');
    }
};
