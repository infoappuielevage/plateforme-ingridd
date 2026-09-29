<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Table "singleton" : une seule ligne (id=1), voir App\Models\Parametre.
     * Regroupe tout ce qui était auparavant codé en dur (nom de l'application,
     * couleur, tailles maximales d'upload, durée de rétention de l'historique)
     * pour que ce soit modifiable depuis Administration -> Paramètres, sans
     * jamais avoir à retoucher au code après le déploiement.
     */
    public function up(): void
    {
        Schema::create('parametres', function (Blueprint $table) {
            $table->id();
            $table->string('nom_application')->default('INGRIDD — Archives & Documents');
            $table->string('logo')->nullable();
            $table->string('couleur_principale', 7)->default('#6366f1');
            $table->unsignedInteger('taille_max_document_mo')->default(20);
            $table->unsignedInteger('taille_max_avatar_mo')->default(2);
            $table->unsignedInteger('retention_audit_jours')->default(730);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parametres');
    }
};
