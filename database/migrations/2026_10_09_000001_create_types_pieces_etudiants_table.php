<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Référentiel des pièces attendues dans un dossier étudiant — demande
 * explicite du client : remplacer la liste libre ("+ Ajouter" avec un
 * libellé tapé à la main, voir l'ancienne 2026_10_08_000003_create_etudiant_fichiers_table.php)
 * par une liste fixe gérée depuis l'administration, "comme pour les PV de
 * délibération avec la possibilité d'ajouter d'autre liste" — c'est-à-dire
 * le même principe que App\Models\ChampSupplementaire : chaque type ajouté
 * ici crée automatiquement une case (vide) sur TOUS les dossiers existants
 * (voir App\Models\TypePieceEtudiant::synchroniserCasesRetroactives()),
 * en plus des nouveaux dossiers créés ensuite — et aussi appelée par le
 * seeder de départ (database/seeders/TypePieceEtudiantSeeder.php) pour que
 * les dossiers RÉELS déjà créés avant cette refonte en profitent également.
 *
 * `code` : clé stable utilisée par le seeder de départ (TypePieceEtudiantSeeder)
 * et par la réconciliation des pièces déjà déposées avant cette refonte (voir
 * la migration suivante) — jamais modifiable après création (voir
 * TypePieceEtudiantController::valider()), contrairement à `libelle` qui
 * reste éditable à tout moment. Même principe que App\Models\TypeDocument.
 *
 * `recurrente` : distingue les DEUX familles de pièces demandées par le
 * client — une pièce d'IDENTITÉ (CNIB, acte de naissance...) n'existe qu'UNE
 * fois pour tout le parcours de l'étudiant (recurrente = false, une case
 * rattachée directement à l'étudiant), alors qu'une pièce liée à une ANNÉE
 * ACADÉMIQUE (fiche d'inscription, relevé de notes...) doit pouvoir exister
 * une fois PAR ANNÉE (recurrente = true, une case par inscription — voir
 * EtudiantInscription) : "il faudrait également que l'on puisse ranger les
 * fichiers en fonction du niveau d'étude ou de l'année académique".
 *
 * `actif` + SoftDeletes, comme ChampSupplementaire : `actif` arrête la
 * pré-création de nouvelles cases (nouveaux dossiers/inscriptions ET
 * synchronisation rétroactive) sans jamais toucher aux cases déjà créées.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('types_pieces_etudiants', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('libelle', 150);
            $table->boolean('recurrente')->default(false);
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('types_pieces_etudiants');
    }
};
