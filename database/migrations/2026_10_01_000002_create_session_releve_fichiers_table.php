<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Une "case" au sein d'une session de relevé (voir
 * 2026_10_01_000001_create_sessions_releves_table.php) — structure
 * identique à session_deliberation_fichiers (voir son docblock pour le
 * détail complet des choix : `numero` nullable pour les deux notes
 * d'information, `document_id` nullable + nullOnDelete() pour permettre un
 * réupload progressif sans perdre la case, `cascadeOnDelete()` sur la
 * session parente).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_releve_fichiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_releve_id')->constrained('sessions_releves')->cascadeOnDelete();
            // 'note_etudiants' | 'note_enseignants' | 'semestre' | 'annee_these'
            // — voir App\Models\SessionReleveFichier::ROLE_*.
            $table->string('role', 30);
            $table->unsignedTinyInteger('numero')->nullable();
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['session_releve_id', 'role', 'numero'],
                'session_releve_fichiers_case_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_releve_fichiers');
    }
};
