<?php

namespace App\Providers;

use App\Listeners\JournaliserActiviteConnexion;
use App\Models\Parametre;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Pas d'EventServiceProvider dans ce projet (voir bootstrap/app.php) :
        // les écouteurs d'authentification sont enregistrés ici, à la main.
        Event::listen(Login::class, [JournaliserActiviteConnexion::class, 'gererConnexion']);
        Event::listen(Logout::class, [JournaliserActiviteConnexion::class, 'gererDeconnexion']);
        Event::listen(Failed::class, [JournaliserActiviteConnexion::class, 'gererEchec']);
        Event::listen(Lockout::class, [JournaliserActiviteConnexion::class, 'gererVerrouillage']);

        // Politique de mot de passe (production uniquement : en local/tests,
        // on garde des mots de passe simples type "password" pour les tests
        // automatisés et les données de démonstration).
        Password::defaults(function () {
            $regle = Password::min(10)->mixedCase()->numbers();

            return $this->app->isProduction() ? $regle->uncompromised() : $regle;
        });

        // Disponible dans toutes les vues Blade de l'espace de gestion
        // (layouts.gestion / layouts.invite) sous le nom $parametresGlobaux —
        // Parametre::actuel() se protège déjà lui-même si la table
        // `parametres` n'existe pas encore (voir son docblock), mais on evite
        // quand même la requête pour les commandes artisan (migrate, test,
        // queue:work...), qui ne rendent jamais de vue Blade.
        if (! $this->app->runningInConsole()) {
            View::share('parametresGlobaux', Parametre::actuel());
        }
    }
}
