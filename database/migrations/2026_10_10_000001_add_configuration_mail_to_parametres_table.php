<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configuration mail (SMTP) éditable depuis Administration > Paramètres —
 * demande explicite du client : jusqu'ici ces réglages (hôte, port,
 * identifiants, adresse d'expédition...) ne se changeaient qu'en modifiant
 * le fichier .env à la main sur le serveur, ce qui veut dire une
 * intervention technique à chaque renouvellement (ex. un nouveau mot de
 * passe d'application Gmail) — voir App\Models\Parametre::
 * appliquerConfigurationMail(), appelée depuis
 * App\Providers\AppServiceProvider::boot() à chaque requête, qui surcharge
 * la configuration mail de Laravel (normalement lue depuis .env) par ces
 * valeurs UNIQUEMENT quand `mail_host` est renseigné ici — tant que cet
 * écran n'a jamais été rempli, .env continue de faire foi exactement comme
 * avant cette migration.
 *
 * `mail_password` est en `text` (pas `string`) : App\Models\Parametre la
 * déclare avec le cast Eloquent `encrypted` (voir ce modèle), qui produit
 * une chaîne chiffrée sensiblement plus longue que le mot de passe
 * d'origine — jamais stocké en clair en base.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parametres', function (Blueprint $table) {
            $table->string('mail_host')->nullable()->after('nombre_annees_these_doctorat');
            $table->unsignedSmallInteger('mail_port')->nullable()->after('mail_host');
            $table->string('mail_username')->nullable()->after('mail_port');
            $table->text('mail_password')->nullable()->after('mail_username');
            $table->string('mail_encryption')->nullable()->after('mail_password');
            $table->string('mail_from_address')->nullable()->after('mail_encryption');
            $table->string('mail_from_name')->nullable()->after('mail_from_address');
        });
    }

    public function down(): void
    {
        Schema::table('parametres', function (Blueprint $table) {
            $table->dropColumn([
                'mail_host',
                'mail_port',
                'mail_username',
                'mail_password',
                'mail_encryption',
                'mail_from_address',
                'mail_from_name',
            ]);
        });
    }
};
