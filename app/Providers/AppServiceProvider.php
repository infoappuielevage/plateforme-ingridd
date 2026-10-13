<?php

namespace App\Providers;

use App\Listeners\JournaliserActiviteConnexion;
use App\Models\Parametre;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Pagination\Paginator;
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
        // Tous les écrans de gestion utilisent Bootstrap 5 (voir layouts/
        // gestion.blade.php) — sans cette ligne, {{ $pagine->links() }}
        // générerait des liens de pagination stylés Tailwind par défaut
        // (voir Illuminate\Pagination\Paginator), qui ne s'intègrent pas du
        // tout avec le reste de l'interface. Demande explicite du client :
        // limiter chaque tableau à 8 lignes, le reste accessible par
        // numéro de page (voir les ->paginate(8) dans les contrôleurs).
        Paginator::useBootstrapFive();

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

        // "Mot de passe oublié" (App\Http\Controllers\Auth\AuthController) :
        // par défaut, la notification Laravel ResetPassword construit le
        // lien envoyé par email avec route('password.reset', ...) — un nom
        // de route qui n'existe pas ici (nos routes s'appellent
        // "mot-de-passe.*", dans le style français du reste du projet).
        // Sans cette ligne, Password::sendResetLink() lèverait une
        // RouteNotFoundException dès qu'un email de réinitialisation tente
        // de partir.
        ResetPassword::createUrlUsing(function ($notifiable, string $token) {
            return route('mot-de-passe.reinitialiser', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]);
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
        $parametresActuels = Parametre::actuel();
        View::share('parametresGlobaux', $parametresActuels);

        // Surcharge la configuration mail (.env) par celle enregistrée dans
        // Paramètres > Configuration mail, si elle a été renseignée — voir
        // le docblock de Parametre::appliquerConfigurationMail(). Appelée
        // ici (une fois par requête, juste après avoir chargé $parametresActuels
        // ci-dessus) pour que ce soit en place avant qu'un email puisse
        // partir plus loin dans la requête (ex. réinitialisation de mot de
        // passe) — sans effet tant que mail_host n'a jamais été renseigné.
        $parametresActuels->appliquerConfigurationMail();
    }
}
