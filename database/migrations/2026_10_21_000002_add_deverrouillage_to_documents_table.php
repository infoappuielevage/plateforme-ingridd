<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Déverrouillage explicite d'un document — voir App\Support\Verrouillage et
 * la migration précédente (2026_10_21_000001) pour les seuils globaux.
 * Demande explicite du client : même un administrateur doit d'abord
 * déverrouiller EXPLICITEMENT un document verrouillé avant de pouvoir le
 * remplacer/supprimer (voir App\Http\Controllers\DocumentController::
 * deverrouiller()) — ces deux colonnes gardent une trace de CET acte précis
 * (qui, quand), en plus de la ligne "modification" déjà journalisée
 * automatiquement dans `audit_logs` (voir App\Models\Concerns\Auditable,
 * déjà posé sur Document — remplir ces colonnes déclenche son événement
 * `updated` comme n'importe quel autre changement).
 *
 * Une fois renseigné, `deverrouille_le` lève le verrou DÉFINITIVEMENT pour
 * CE document (pas de ré-verrouillage automatique prévu — un document
 * remplacé devient de toute façon un tout nouveau App\Models\Document, qui
 * repart donc de zéro face aux deux seuils de la migration précédente).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->timestamp('deverrouille_le')->nullable()->after('modifie_par');
            $table->foreignId('deverrouille_par_id')->nullable()->after('deverrouille_le')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deverrouille_par_id');
            $table->dropColumn('deverrouille_le');
        });
    }
};
