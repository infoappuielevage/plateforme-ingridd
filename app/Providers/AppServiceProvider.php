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
        // (layouts.gestion / layouts.invite) sous le nom $parametresGlobaux.
        //
        // PAS de `if (! $this->app->runningInConsole())` ici (une version
        // précédente en avait un, en pensant "les commandes artisan ne
        // rendent jamais de vue Blade") : c'est faux pour `php artisan
        // test`, qui reste une commande console de bout en bout tout en
        // simulant de vraies requêtes HTTP à travers le noyau — donc en
        // rendant bel et bien des vues. Avec cette garde, TOUTE requête de
        // test HTTP touchant layouts.gestion échouait avec "Undefined
        // variable $parametresGlobaux" (5 tests en échec, régression
        // repérée par le client en exécutant `php artisan test`). Aucun
        // garde-fou n'est nécessaire ici de toute façon : Parametre::actuel()
        // se protège déjà lui-même si la table `parametres` n'existe pas
        // encore (voir son docblock et Parametre::estDisponible()), y
        // compris pendant `artisan migrate` elle-même.
        View::share('parametresGlobaux', Parametre::actuel());
    }
}
