<?php

namespace App\Support;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Point d'entrée unique pour vérifier les droits d'un utilisateur sur une
 * ressource Filament. Les droits eux-mêmes ne sont pas codés en dur : ils
 * sont stockés sur chaque App\Models\Role (colonne `permissions`), modifiable
 * depuis Paramètres -> Rôles & permissions sans toucher au code.
 *
 * Repris du projet ingridd-platform (même principe éprouvé), adapté au
 * périmètre plus restreint de cette plateforme : documents, types de
 * documents, filières, niveaux, années académiques, historique.
 * RESSOURCES est la seule liste à modifier dans le code si un jour un
 * nouvel écran doit rejoindre le système de rôles.
 *
 * Les comptes utilisateurs et les rôles eux-mêmes (UserResource,
 * RoleResource) restent volontairement HORS de cette matrice, réservés en
 * dur au rôle "admin" : sinon, un rôle mal configuré pourrait s'accorder
 * lui-même les pleins pouvoirs (élévation de privilèges).
 */
class ResourceAccess
{
    public const RESSOURCES = [
        'documents' => 'Documents',
        'sessions_deliberation' => 'PV de délibération',
        'sessions_releves' => 'Relevés de notes',
        'pv_soutenances' => 'PV de soutenance',
        'types_documents' => 'Types de documents',
        'filieres' => 'Filières',
        'niveaux' => 'Niveaux',
        'annees_academiques' => 'Années académiques',
        'champs_supplementaires' => 'Champs supplémentaires',
        // Historique : toujours en lecture seule pour tout le monde, même
        // "admin" — seul le droit "Voir" a un effet ici (voir AuditLogResource).
        'audit_logs' => 'Historique (audit)',
    ];

    public const ACTIONS = [
        'view' => 'Voir',
        'create' => 'Créer',
        'edit' => 'Modifier',
        'delete' => 'Supprimer',
    ];

    /**
     * Une icône par ressource, uniquement pour l'affichage (écran Rôles &
     * permissions). Reprend l'icône déjà utilisée dans le menu de
     * navigation par la Resource correspondante, pour que le même
     * pictogramme désigne toujours la même chose partout dans l'application.
     */
    public const ICONES = [
        'documents' => 'heroicon-o-document-text',
        'sessions_deliberation' => 'heroicon-o-clipboard-document-check',
        'sessions_releves' => 'heroicon-o-document-chart-bar',
        'pv_soutenances' => 'heroicon-o-academic-cap',
        'types_documents' => 'heroicon-o-tag',
        'filieres' => 'heroicon-o-academic-cap',
        'niveaux' => 'heroicon-o-bars-arrow-up',
        'annees_academiques' => 'heroicon-o-calendar-days',
        'champs_supplementaires' => 'heroicon-o-document-plus',
        'audit_logs' => 'heroicon-o-clock',
    ];

    /** Cache mémoire (durée d'une requête) pour éviter une requête SQL répétée par ressource/action. */
    protected static array $cache = [];

    public static function can(string $resourceKey, string $action): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        // Garde-fou : le rôle "admin" a toujours tous les droits, même si sa
        // ligne en base a été mal modifiée ou effacée par erreur.
        if ($user->role === User::ROLE_ADMIN) {
            return true;
        }

        $permissions = self::permissionsPourRole($user->role);

        return (bool) ($permissions[$resourceKey][$action] ?? false);
    }

    /** @return array<string, array<string, bool>> */
    protected static function permissionsPourRole(string $slug): array
    {
        if (! array_key_exists($slug, self::$cache)) {
            self::$cache[$slug] = Role::query()->where('slug', $slug)->value('permissions') ?? [];
        }

        return self::$cache[$slug];
    }
}
