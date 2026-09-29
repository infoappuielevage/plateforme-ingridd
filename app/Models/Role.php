<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un rôle = un `slug` (référencé par User::role) + une matrice de
 * permissions modifiable depuis l'écran Rôles & permissions, sans toucher au
 * code. Voir App\Support\ResourceAccess pour la liste des ressources/actions
 * possibles et la logique de vérification.
 *
 * Structure de `permissions` (JSON) :
 * ['documents' => ['view' => true, 'create' => false, ...], ...]
 */
class Role extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'permissions',
    ];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
        ];
    }

    /**
     * Pas de clé étrangère classique : `users.role` stocke directement le
     * `slug` du rôle (voir App\Models\User et App\Support\ResourceAccess),
     * donc la relation se fait sur `slug` <-> `role`, pas sur un `role_id`.
     * Utilisée par RoleResource pour compter les utilisateurs par rôle.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'role', 'slug');
    }
}
