<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * PV_REUNION reçoit désormais son propre écran dédié (voir
 * App\Http\Controllers\ReunionController et App\Support\TypesEcranDedie) —
 * comme PV_SOUTENANCE/PV_DELIBERATION/RELEVE_NOTES avant lui, il doit donc
 * redevenir `actif` pour apparaître dans le menu "Documents > Types de
 * documents" et le filtre "Type" de "Tous les documents" (voir
 * App\Support\MenuDocuments) — deux usages qui restent indépendants de
 * l'écran dédié, même raisonnement que les trois précédents.
 *
 * Une MIGRATION plutôt qu'un simple ajout dans TypeDocumentSeeder::
 * CODES_ACTIFS_PAR_DEFAUT (fait en même temps, voir son docblock) : ce
 * tableau ne s'applique qu'à une ligne qui n'existe pas encore — sur la base
 * déjà en service, PV_REUNION a été désactivé une fois pour toutes par la
 * migration 2026_10_06_000001_desactiver_types_document_non_prioritaires, il
 * faut donc l'inverse ici pour cette seule ligne. Réversible via `down()`.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('type_documents')->where('code', 'PV_REUNION')->update(['actif' => true]);
    }

    public function down(): void
    {
        DB::table('type_documents')->where('code', 'PV_REUNION')->update(['actif' => false]);
    }
};
