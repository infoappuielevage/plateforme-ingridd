<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PV de réunion — nouvelle structure dédiée (demande explicite du client,
 * "avançons sur le PV des réunions"), MÊME PRINCIPE que pv_soutenances/
 * sessions_deliberation/sessions_releves avant elle : un vrai enregistrement
 * dédié avec ses propres champs, plutôt que de continuer à surcharger
 * `documents`. Voir App\Support\TypesEcranDedie, où PV_REUNION rejoint les
 * trois autres codes.
 *
 * Contrairement aux PV de délibération/Relevés de notes (un enregistrement =
 * une FILIÈRE entière pour une session), une réunion ici n'est rattachée à
 * AUCUNE filière : conseils d'établissement, réunions hebdomadaires du
 * secrétariat, etc. concernent l'institution dans son ensemble, pas une
 * filière précise — demande explicite du client, qui n'a mentionné que
 * l'année académique, la date, le type et une description.
 *
 * `sous_type_document_id` porte le "type de réunion" (réunion hebdomadaire,
 * réunion extraordinaire, etc.) — réutilise App\Models\SousTypeDocument
 * plutôt qu'une colonne figée avec des constantes PHP : liste ouverte,
 * complétable depuis Administration > Référentiels > Types de documents >
 * PV réunions > Sous-types, sans toucher au code (même raisonnement que
 * "Bordereau", voir 2026_09_21_000017_create_sous_types_documents_table.php).
 *
 * `restrictOnDelete()` sur annee_academique_id (comme filiere_id sur
 * sessions_deliberation) : champ obligatoire d'identification de la réunion,
 * pas un rattachement facultatif. `sous_type_document_id` reste nullable +
 * `nullOnDelete()` : si un sous-type est supprimé définitivement depuis
 * l'administration, la réunion survit simplement sans type précis plutôt que
 * de disparaître avec lui (même raisonnement que champ_supplementaire_id
 * ailleurs dans ce projet).
 *
 * SoftDeletes : même convention que les autres écrans dédiés — "corbeillé,
 * jamais supprimé" par défaut.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reunions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('annee_academique_id')->constrained('annees_academiques')->restrictOnDelete();
            $table->date('date_reunion');
            // "Réunion hebdomadaire" | "Réunion extraordinaire" | ... — voir
            // App\Models\SousTypeDocument, rattaché au type PV_REUNION.
            $table->foreignId('sous_type_document_id')->nullable()->constrained('sous_types_documents')->nullOnDelete();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reunions');
    }
};
