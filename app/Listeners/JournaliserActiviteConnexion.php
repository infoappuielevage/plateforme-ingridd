<?php

namespace App\Listeners;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Request;

/**
 * Journal des connexions — enregistre les connexions réussies, les
 * déconnexions, les tentatives échouées et les verrouillages temporaires
 * (trop de tentatives rapprochées) dans la même table `audit_logs` que le
 * reste de l'historique (voir App\Models\AuditLog et
 * App\Filament\Resources\AuditLogResource), plutôt que de créer une table et
 * un écran séparés pour un besoin très proche.
 *
 * Enregistré explicitement dans App\Providers\AppServiceProvider::boot()
 * (pas de découverte automatique des listeners dans ce projet — voir
 * bootstrap/app.php, qui n'utilise pas d'EventServiceProvider).
 *
 * Met aussi à jour `users.derniere_connexion_at`/`derniere_connexion_ip` à
 * chaque connexion réussie — affiché sur "Mon profil" (voir
 * App\Filament\Pages\ModifierProfil) via forceFill() : ces deux colonnes
 * sont volontairement absentes de User::$fillable, donc un simple
 * ->update() échouerait silencieusement à les modifier.
 */
class JournaliserActiviteConnexion
{
    public function gererConnexion(Login $event): void
    {
        /** @var User $utilisateur */
        $utilisateur = $event->user;

        $utilisateur->forceFill([
            'derniere_connexion_at' => now(),
            'derniere_connexion_ip' => Request::ip(),
        ])->save();

        AuditLog::create([
            'user_id' => $utilisateur->id,
            'user_name' => $utilisateur->name,
            'action' => 'connexion',
            'model_type' => User::class,
            'model_id' => $utilisateur->id,
            'resume' => "Connexion de {$utilisateur->name}",
        ]);
    }

    public function gererDeconnexion(Logout $event): void
    {
        $utilisateur = $event->user;

        if (! $utilisateur instanceof User) {
            return;
        }

        AuditLog::create([
            'user_id' => $utilisateur->id,
            'user_name' => $utilisateur->name,
            'action' => 'deconnexion',
            'model_type' => User::class,
            'model_id' => $utilisateur->id,
            'resume' => "Déconnexion de {$utilisateur->name}",
        ]);
    }

    /**
     * $event->credentials contient l'email ET le mot de passe saisis : on
     * ne reprend JAMAIS ce tableau tel quel dans le résumé/les colonnes
     * "avant"/"après" de l'historique, seulement l'adresse email — jamais
     * le mot de passe, même raté.
     */
    public function gererEchec(Failed $event): void
    {
        $email = $event->credentials['email'] ?? '?';

        AuditLog::create([
            'user_id' => $event->user?->id,
            'user_name' => $event->user?->name,
            'action' => 'connexion_echouee',
            'model_type' => User::class,
            'model_id' => $event->user?->id,
            'resume' => "Tentative de connexion échouée pour « {$email} »",
        ]);
    }

    public function gererVerrouillage(Lockout $event): void
    {
        $email = $event->request->input('email', '?');

        AuditLog::create([
            'user_id' => null,
            'user_name' => null,
            'action' => 'connexion_verrouillee',
            'model_type' => User::class,
            'model_id' => null,
            'resume' => "Trop de tentatives de connexion pour « {$email} » — accès temporairement bloqué",
        ]);
    }
}
