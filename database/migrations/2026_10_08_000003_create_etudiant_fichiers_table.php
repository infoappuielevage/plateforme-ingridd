<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les pièces du dossier d'un étudiant (voir
 * 2026_10_08_000001_create_etudiants_table.php) — demande explicite du
 * client : "plusieurs fichiers seront téléchargés donc elle doit être
 * extensible". MÊME PRINCIPE que les pièces jointes d'une réunion (voir le
 * docblock de 2026_10_07_000002_create_reunion_fichiers_table.php, rôle
 * `piece_jointe`) : AUCUNE case n'est pré-créée, une ligne n'existe que
 * lorsqu'un fichier est effectivement ajouté (ex. copie de CNIB, acte de
 * naissance, diplôme du BAC...), et la retirer supprime la ligne elle-même
 * plutôt que de la vider — pas de "case fixe" à garder disponible ici,
 * contrairement au rapport d'une réunion : un dossier étudiant n'a pas de
 * pièce obligatoire unique.
 *
 * `numero` : simple compteur d'ajout (1, 2, 3...), pour un affichage stable
 * dans l'ordre d'ajout plutôt que par id — même rôle que sur
 * reunion_fichiers.
 *
 * `libelle` (nullable) : le type de pièce ("CNIB", "Acte de naissance"...),
 * saisi librement à l'ajout. Laissé vide, une pièce retombe sur le nom
 * original du fichier déposé — voir App\Models\EtudiantFichier::libelle().
 *
 * `document_id` nullable + `nullOnDelete()`, `etudiant_id` en
 * `cascadeOnDelete()` : mêmes raisons que pour reunion_fichiers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('etudiant_fichiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etudiant_id')->constrained('etudiants')->cascadeOnDelete();
            $table->unsignedInteger('numero');
            $table->string('libelle', 150)->nullable();
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->timestamps();

            $table->unique(['etudiant_id', 'numero'], 'etudiant_fichiers_numero_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('etudiant_fichiers');
    }
};
