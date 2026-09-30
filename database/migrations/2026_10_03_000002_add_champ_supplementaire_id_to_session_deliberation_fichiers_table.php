<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rattache une case de PV de délibération à un éventuel "champ
 * supplémentaire" (voir 2026_10_03_000001_create_champs_supplementaires_table.php
 * et App\Models\ChampSupplementaire) : une case créée pour un champ
 * personnalisé porte role = SessionDeliberationFichier::ROLE_SUPPLEMENTAIRE
 * et ce `champ_supplementaire_id` ; les cases "historiques" (notes
 * d'information, semestre, année de thèse) gardent cette colonne à null.
 *
 * `nullOnDelete()` plutôt que `cascadeOnDelete()` : supprimer définitivement
 * un champ supplémentaire (bloqué tant qu'il reste utilisé par au moins une
 * case ayant un document déposé, voir ChampSupplementaireController::destroy())
 * ne doit jamais emporter les cases déjà créées — au pire, `libelle()`
 * retombe sur un texte générique (voir App\Models\SessionDeliberationFichier).
 *
 * L'ancienne contrainte unique (session, role, numero) est recréée en y
 * ajoutant `champ_supplementaire_id` : plusieurs champs supplémentaires
 * partagent le même rôle ("supplementaire") et le même numero (toujours
 * null pour eux), seule la combinaison avec `champ_supplementaire_id` les
 * distingue les uns des autres au sein d'une même session.
 *
 * Index intermédiaire `..._session_id_index` (voir up() ci-dessous) : sur
 * MySQL/InnoDB, l'ancien index unique (session_deliberation_id, role, numero)
 * sert aussi de support à la contrainte de clé étrangère sur
 * `session_deliberation_id` (aucun autre index ne couvrait cette colonne).
 * Le supprimer directement échoue avec l'erreur 1553 ("needed in a foreign
 * key constraint") tant qu'aucun autre index ne peut prendre le relais — d'où
 * ce petit index simple, ajouté AVANT le dropUnique(), et volontairement
 * jamais retiré ensuite (le risque d'un nouvel imprévu sur une suppression
 * d'index n'en vaut pas la peine pour quelques octets économisés).
 *
 * `Schema::hasColumn(...)` en garde sur l'ajout de la colonne : cette
 * migration a échoué une première fois en production APRÈS avoir déjà ajouté
 * la colonne/la clé étrangère (c'est justement le dropUnique() qui a échoué,
 * une fois la colonne déjà en place) ; la garde permet de relancer
 * `php artisan migrate` tel quel, sans "Duplicate column" au second essai.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('session_deliberation_fichiers', 'champ_supplementaire_id')) {
            Schema::table('session_deliberation_fichiers', function (Blueprint $table) {
                $table->foreignId('champ_supplementaire_id')->nullable()->after('numero')
                    ->constrained('champs_supplementaires')->nullOnDelete();
            });
        }

        Schema::table('session_deliberation_fichiers', function (Blueprint $table) {
            $table->index('session_deliberation_id', 'session_deliberation_fichiers_session_id_index');
        });

        Schema::table('session_deliberation_fichiers', function (Blueprint $table) {
            $table->dropUnique('session_deliberation_fichiers_case_unique');
            $table->unique(
                ['session_deliberation_id', 'role', 'numero', 'champ_supplementaire_id'],
                'session_deliberation_fichiers_case_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('session_deliberation_fichiers', function (Blueprint $table) {
            $table->dropUnique('session_deliberation_fichiers_case_unique');
            $table->unique(
                ['session_deliberation_id', 'role', 'numero'],
                'session_deliberation_fichiers_case_unique'
            );
        });

        Schema::table('session_deliberation_fichiers', function (Blueprint $table) {
            $table->dropIndex('session_deliberation_fichiers_session_id_index');
        });

        Schema::table('session_deliberation_fichiers', function (Blueprint $table) {
            $table->dropForeign(['champ_supplementaire_id']);
            $table->dropColumn('champ_supplementaire_id');
        });
    }
};
