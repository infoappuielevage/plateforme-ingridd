<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

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
class User extends Authenticatable implements FilamentUser, HasAvatar
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

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    /** Photo de profil réelle si elle existe, sinon Filament revient automatiquement aux initiales. */
    public function getFilamentAvatarUrl(): ?string
    {
        return $this->avatar ? Storage::disk('public')->url($this->avatar) : null;
    }

    public static function libellesRoles(): array
    {
        return [
            self::ROLE_ADMIN => 'Administrateur',
            self::ROLE_GESTIONNAIRE => 'Gestionnaire',
            self::ROLE_CONSULTATION => 'Consultation',
        ];
    }
}
