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
        'reunions' => 'PV de réunion',
        'etudiants' => 'Dossier étudiant',
        'etats_financiers' => 'États financiers',
        'recettes' => 'Gestion des recettes',
        'depenses' => 'Gestion des dépenses',
        'listes_finances_etudiants' => 'Liste des étudiants (finances)',
        'contrats' => 'Gestion des contrats',
        'types_documents' => 'Types de documents',
        'filieres' => 'Filières',
        'niveaux' => 'Niveaux',
        'annees_academiques' => 'Années académiques',
        'champs_supplementaires' => 'Champs supplémentaires',
        'types_pieces_etudiants' => 'Types de pièces (dossier étudiant)',
        'types_depenses' => 'Catégories de dépenses',
        'types_contrats' => 'Catégories de contrats',
        // Historique : toujours en lecture seule pour tout le monde, même
        // "admin" — seul le droit "Voir" a un effet ici (voir AuditLogResource).
        'audit_logs' => 'Historique (audit)',
        // Verrouillage : un seul geste possible (verrouiller/déverrouiller un
        // document), donc seul le droit "Modifier" a un effet ici — même
        // principe que "audit_logs" ci-dessus (voir RoleController::valider()
        // et gestion/roles/form.blade.php). Un rôle "admin" a de toute façon
        // toujours ce droit (voir can() ci-dessous), qu'il soit coché ou non
        // pour lui dans la matrice.
        'verrouillage_documents' => 'Verrouillage des documents',
    ];

    public const ACTIONS = [
        'view' => 'Voir',
        'create' => 'Créer',
        'edit' => 'Modifier',
        'delete' => 'Supprimer',
        // Distinct de "delete" (demande explicite du client) : "delete" ne
        // couvre plus que la mise à la corbeille/restauration (réversible),
        // ce droit-ci couvre la purge irréversible (vider la corbeille,
        // "Supprimer définitivement") — voir RoleController::valider() et
        // gestion/roles/form.blade.php pour l'exception "depenses" (pas de
        // corbeille propre, donc ce droit n'a pas de sens pour cette
        // ressource) et les 17 contrôleurs dont les méthodes
        // forceDestroy()/viderCorbeille() vérifient maintenant ce droit au
        // lieu de "delete". Comme tout nouveau droit, les rôles existants en
        // base n'ont pas cette clé dans leur JSON `permissions` : can() la
        // traite donc comme false (désactivée) pour tout le monde sauf
        // "admin" jusqu'à ce qu'un administrateur l'active explicitement.
        'delete_definitif' => 'Supprimer définitivement',
    ];

    /**
     * Une icône Bootstrap Icons par ressource, uniquement pour l'affichage
     * (écran Rôles & permissions, voir gestion/roles/form.blade.php). Reprend
     * EXACTEMENT l'icône déjà utilisée ailleurs dans l'application pour la
     * même ressource (menu de navigation dans resources/views/layouts/
     * gestion.blade.php, ou écran Paramètres pour "audit_logs"/
     * "verrouillage_documents"), pour que le même pictogramme désigne
     * toujours la même chose partout. Ancienne version de cette constante :
     * des classes "heroicon-o-*" héritées de la version Filament du projet,
     * jamais utilisées par les vues Blade actuelles (confirmé : aucune
     * référence à ICONES ailleurs dans le code avant ce changement).
     */
    public const ICONES = [
        'documents' => 'bi-file-earmark-text-fill',
        'sessions_deliberation' => 'bi-clipboard2-check-fill',
        'sessions_releves' => 'bi-journal-text',
        'pv_soutenances' => 'bi-mortarboard-fill',
        'reunions' => 'bi-people-fill',
        'etudiants' => 'bi-person-vcard-fill',
        'etats_financiers' => 'bi-bar-chart-line-fill',
        'recettes' => 'bi-cash-coin',
        'depenses' => 'bi-credit-card-2-front-fill',
        'listes_finances_etudiants' => 'bi-people-fill',
        'contrats' => 'bi-vector-pen',
        'types_documents' => 'bi-tags-fill',
        'filieres' => 'bi-diagram-3-fill',
        'niveaux' => 'bi-bar-chart-steps',
        'annees_academiques' => 'bi-calendar-range-fill',
        'champs_supplementaires' => 'bi-file-earmark-plus-fill',
        'types_pieces_etudiants' => 'bi-folder2-open',
        'types_depenses' => 'bi-credit-card-2-front-fill',
        'types_contrats' => 'bi-vector-pen',
        'audit_logs' => 'bi-clock-history',
        'verrouillage_documents' => 'bi-lock',
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
