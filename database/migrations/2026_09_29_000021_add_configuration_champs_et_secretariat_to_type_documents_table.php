<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rend "flexible via l'administrateur" ce qui était jusqu'ici câblé en dur
 * dans le code (App\Support\TypesDiplome::CODES,
 * App\Support\TypesPvSoutenance::CODES, et la recherche du type "RELEVE_NOTES"
 * par son code dans DocumentController) — sur demande explicite du client :
 * "tout doit pouvoir être flexible via l'administrateur, pour éviter de
 * toucher le code après un déploiement".
 *
 * Chaque type de document peut désormais, depuis Administration > Référentiels
 * > Types de documents (voir TypeDocumentResource et, une fois portée en
 * Blade, la future ressource équivalente), activer lui-même :
 *
 * - `champs_diplome_actifs` : affiche titulaire/numéro/date de délivrance/
 *   mention/statut de retrait sur le formulaire (App\Support\TypesDiplome,
 *   qui exposait ces types par une liste de codes figée — désormais un
 *   simple filtre sur cette colonne).
 * - `champs_soutenance_actifs` : affiche thème du mémoire/date de soutenance/
 *   membres du jury (App\Support\TypesPvSoutenance, même principe).
 * - `champ_semestre_actif` : affiche le champ "Semestre" (remplace la
 *   recherche en dur du type "RELEVE_NOTES" par son code).
 * - `epingle_secretariat` : fait apparaître ce type comme une entrée dédiée
 *   du menu "Secrétariat" (voir resources/views/layouts/gestion.blade.php)
 *   plutôt que de rester seulement accessible via le filtre "Type" de la
 *   liste générale des documents. Un type avec des sous-types actifs ET
 *   `champs_diplome_actifs` (aujourd'hui : Attestation) reçoit une entrée
 *   PAR sous-type actif plutôt qu'une seule entrée globale — même logique
 *   que l'ancien menu Filament, désormais pilotée par ces colonnes plutôt
 *   que par le code du type.
 *
 * Toutes par défaut à `false` : un type nouvellement créé par l'admin reste
 * un document "générique" (aucun champ complémentaire, pas d'entrée
 * Secrétariat dédiée) tant que l'admin ne coche pas explicitement l'option
 * voulue — voir TypeDocumentSeeder pour les valeurs initiales des types
 * existants.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('type_documents', function (Blueprint $table) {
            $table->boolean('champs_diplome_actifs')->default(false)->after('actif');
            $table->boolean('champs_soutenance_actifs')->default(false)->after('champs_diplome_actifs');
            $table->boolean('champ_semestre_actif')->default(false)->after('champs_soutenance_actifs');
            $table->boolean('epingle_secretariat')->default(false)->after('champ_semestre_actif');
        });
    }

    public function down(): void
    {
        Schema::table('type_documents', function (Blueprint $table) {
            $table->dropColumn([
                'champs_diplome_actifs',
                'champs_soutenance_actifs',
                'champ_semestre_actif',
                'epingle_secretariat',
            ]);
        });
    }
};
