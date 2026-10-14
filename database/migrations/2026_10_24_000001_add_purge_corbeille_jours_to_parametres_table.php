<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Durée de rétention de la corbeille (demande explicite du client, "purge
 * automatique" — voir le docblock de App\Console\Commands\PurgerCorbeilles),
 * réglable depuis Administration > Paramètres > Corbeilles, MÊME PRINCIPE
 * que `retention_audit_jours` (voir la migration
 * 2026_09_20_000005_create_parametres_table.php et App\Models\AuditLog).
 *
 * Nullable, SANS valeur par défaut dans App\Models\Parametre::
 * VALEURS_PAR_DEFAUT (contrairement à `retention_audit_jours`, qui en a
 * une) : purger DÉFINITIVEMENT du contenu est irréversible, bien plus
 * lourd de conséquence qu'une ligne d'historique — ce réglage reste donc
 * désactivé (NULL = "ne jamais purger automatiquement") tant que
 * l'administrateur ne l'active pas lui-même en renseignant un nombre de
 * jours, plutôt que d'activer silencieusement une suppression automatique
 * dès l'installation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parametres', function (Blueprint $table) {
            $table->unsignedInteger('purge_corbeille_jours')->nullable()->after('retention_audit_jours');
        });
    }

    public function down(): void
    {
        Schema::table('parametres', function (Blueprint $table) {
            $table->dropColumn('purge_corbeille_jours');
        });
    }
};
