<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Repris du projet ingridd-platform, adapté à 3 rôles au lieu de 4 (plus de
 * distinction direction/scolarité/secrétariat — hors périmètre ici) :
 *
 * - admin        : tous les droits, toujours (voir App\Support\ResourceAccess::can()).
 * - gestionnaire : gère les documents/catégories/types au quotidien.
 * - consultation : lecture seule (droits exacts définis via Rôles & permissions).
 *
 * `role` est une simple chaîne (le "slug" de App\Models\Role) — pas de clé
 * étrangère, pour permettre à ResourceAccess de fonctionner même si la table
 * `roles` est momentanément vide ou mal configurée (voir ROLE_ADMIN, jamais
 * bloqué par les permissions stockées en base).
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_GESTIONNAIRE = 'gestionnaire';

    public const ROLE_CONSULTATION = 'consultation';

    /**
     * `avatar`, `derniere_connexion_at` et `derniere_connexion_ip` sont
     * volontairement absents : ils ne doivent jamais être modifiables via un
     * simple ->update() de masse (voir ModifierProfil et
     * JournaliserActiviteConnexion, qui utilisent forceFill()).
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'derniere_connexion_at' => 'datetime',
        ];
    }

    /**
     * Libellés affichés (ex. badge de rôle dans la topbar, voir
     * resources/views/layouts/gestion.blade.php) pour les rôles connus du
     * code. Les cinq rôles "organisation" (Direction, Secrétariat,
     * Comptabilité, Patrimoine, Chargées de cours — voir
     * database/seeders/RoleSeeder.php) n'ont pas de constante ROLE_* dédiée
     * ici : contrairement à ROLE_ADMIN (seul rôle avec un traitement à part
     * dans le code, voir App\Support\ResourceAccess/RoleController/
     * UserController), ce sont de simples lignes de App\Models\Role, comme
     * n'importe quel rôle qu'un administrateur créerait lui-même depuis
     * Rôles & permissions — ils restent listés ici uniquement pour un
     * affichage plus lisible que leur slug brut.
     */
    public static function libellesRoles(): array
    {
        return [
            self::ROLE_ADMIN => 'Administrateur',
            self::ROLE_GESTIONNAIRE => 'Gestionnaire',
            self::ROLE_CONSULTATION => 'Consultation',
            'direction' => 'Direction',
            'secretariat' => 'Secrétariat',
            'comptabilite' => 'Comptabilité',
            'patrimoine' => 'Patrimoine',
            'chargees_cours' => 'Chargées de cours',
        ];
    }
}
