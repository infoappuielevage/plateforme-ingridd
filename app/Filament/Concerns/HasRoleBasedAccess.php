<?php

namespace App\Filament\Concerns;

use App\Support\ResourceAccess;

/**
 * Repris tel quel du projet ingridd-platform. Une Resource qui utilise ce
 * trait doit implémenter `resourceAccessKey()` (une des clés de
 * App\Support\ResourceAccess::RESSOURCES) — toutes les vérifications
 * d'affichage/création/modification/suppression passent alors par
 * ResourceAccess::can(), donc par la matrice de permissions modifiable
 * depuis Rôles & permissions.
 */
trait HasRoleBasedAccess
{
    abstract public static function resourceAccessKey(): string;

    public static function shouldRegisterNavigation(): bool
    {
        return ResourceAccess::can(static::resourceAccessKey(), 'view');
    }

    public static function canViewAny(): bool
    {
        return ResourceAccess::can(static::resourceAccessKey(), 'view');
    }

    public static function canCreate(): bool
    {
        return ResourceAccess::can(static::resourceAccessKey(), 'create');
    }

    public static function canEdit($record): bool
    {
        return ResourceAccess::can(static::resourceAccessKey(), 'edit');
    }

    public static function canDelete($record): bool
    {
        return ResourceAccess::can(static::resourceAccessKey(), 'delete');
    }
}
