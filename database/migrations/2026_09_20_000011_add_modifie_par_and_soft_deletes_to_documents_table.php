<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deux ajouts liés à la traçabilité et à la sécurité des suppressions :
 *
 * - `modifie_par` : qui a modifié le document en dernier (distinct de
 *   `uploaded_by`, qui reste "qui l'a ajouté" — voir App\Models\Document).
 *   L'historique complet (Administration > Historique) trace déjà chaque
 *   création/modification/suppression avec l'auteur et le détail des
 *   changements ; ces deux colonnes affichent la version courte
 *   directement sur le document, sans avoir à aller consulter l'historique.
 *
 * - `deleted_at` (SoftDeletes) : un document supprimé va d'abord à la
 *   corbeille (Administration > Documents > filtre "Corbeille") plutôt que
 *   d'être effacé immédiatement — voir App\Models\Document::booted(), qui
 *   ne supprime le fichier physique qu'au moment d'une suppression
 *   définitive (`isForceDeleting()`), jamais lors d'une simple mise à la
 *   corbeille.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('modifie_par')->nullable()->after('uploaded_by')->constrained('users')->nullOnDelete();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['modifie_par']);
            $table->dropColumn(['modifie_par', 'deleted_at']);
        });
    }
};
