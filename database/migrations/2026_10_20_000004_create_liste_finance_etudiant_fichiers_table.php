<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fichiers d'une "Liste des étudiants (finances)" — liste EN NOMBRE LIBRE
 * (comme le rôle `piece_jointe` d'App\Models\ReunionFichier, ou
 * App\Models\RecetteDossierFichier), aucune case fixe : chaque fichier
 * ajouté crée directement sa ligne. Voir le docblock de la migration
 * précédente pour le contexte complet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('liste_finance_etudiant_fichiers', function (Blueprint $table) {
            $table->id();
            // Contrainte nommée explicitement (court) : le nom auto-généré
            // par ->constrained() ("liste_finance_etudiant_fichiers_liste_
            // finance_etudiant_id_foreign") dépasse la limite MySQL de 64
            // caractères pour un identifiant — SQLSTATE[42000] "Identifier
            // name... is too long", vu en pratique au premier `php artisan
            // migrate` du client.
            $table->foreignId('liste_finance_etudiant_id');
            $table->foreign('liste_finance_etudiant_id', 'lfe_fichiers_liste_id_foreign')
                ->references('id')->on('listes_finances_etudiants')
                ->cascadeOnDelete();
            $table->unsignedInteger('numero');
            $table->string('libelle', 150)->nullable();
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->timestamps();

            $table->unique(['liste_finance_etudiant_id', 'numero'], 'liste_finance_etudiant_fichiers_numero_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('liste_finance_etudiant_fichiers');
    }
};
