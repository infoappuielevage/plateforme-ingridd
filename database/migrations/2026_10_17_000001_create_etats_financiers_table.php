<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * États financiers — nouvel écran dédié (demande explicite du client,
 * "intègre la gestion des États financières"), MÊME PRINCIPE que
 * pv_soutenances/sessions_deliberation/reunions avant lui : un vrai
 * enregistrement dédié, avec un fichier réellement déposé qui reste un vrai
 * App\Models\Document (voir App\Http\Controllers\EtatFinancierController).
 *
 * Structure volontairement minimale (un enregistrement = une année + un
 * fichier + une observation, voir la demande du client) : contrairement aux
 * autres écrans dédiés, UN SEUL fichier par enregistrement — pas besoin
 * d'une table de "cases" séparée (comme reunion_fichiers...), `document_id`
 * vit directement sur cette table (voir
 * App\Http\Controllers\EtatFinancierController::uploaderFichier()/
 * supprimerFichier()).
 *
 * `annee` est une ANNÉE CIVILE SIMPLE (ex. 2012, 2023 — demande explicite du
 * client, confirmée en amont), un entier libre plutôt qu'une référence au
 * référentiel App\Models\AnneeAcademique (format "AAAA-AAAA", une notion
 * différente utilisée par les autres écrans dédiés) — d'où `unsignedSmallInteger`
 * et non un `foreignId`. Unique : un seul état financier par année.
 *
 * SoftDeletes : même convention que les autres tables métier de premier
 * niveau de ce projet — "corbeillé, jamais supprimé" par défaut.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('etats_financiers', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('annee')->unique();
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->text('observation')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('etats_financiers');
    }
};
