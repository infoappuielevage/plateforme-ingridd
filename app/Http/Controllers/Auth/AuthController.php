<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as RegleMotDePasse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Connexion/déconnexion de l'espace de gestion — remplace le contrôleur de
 * connexion intégré à Filament (sur demande du client, qui ne veut plus de
 * Filament du tout, voir App\Providers\Filament\AdminPanelProvider).
 *
 * Même garde-fou anti-force-brute que l'ancien écran Filament : 5 tentatives
 * maximum par minute, la clé combinant l'email saisi et l'adresse IP (pas
 * l'email seul, pour ne pas bloquer tout le monde si quelqu'un enchaîne les
 * essais sur un compte qui n'existe pas — ni l'IP seule, pour ne pas bloquer
 * tout un réseau/proxy partagé à cause d'une seule personne).
 */
class AuthController extends Controller
{
    public function formulaire(): View
    {
        if (Auth::check()) {
            return redirect()->route('gestion.dashboard');
        }

        return view('auth.login');
    }

    public function connexion(Request $request): RedirectResponse
    {
        $identifiants = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $cleThrottle = $this->cleThrottle($request);

        if (RateLimiter::tooManyAttempts($cleThrottle, 5)) {
            // Même événement standard Laravel qu'avant (écouté par
            // App\Listeners\JournaliserActiviteConnexion::gererVerrouillage)
            // — l'historique des connexions garde exactement le même
            // comportement qu'avec l'ancien écran de connexion Filament.
            event(new Lockout($request));

            $secondes = RateLimiter::availableIn($cleThrottle);

            throw ValidationException::withMessages([
                'email' => "Trop de tentatives. Réessayez dans {$secondes} seconde(s).",
            ]);
        }

        if (! Auth::attempt($identifiants, $request->boolean('se_souvenir'))) {
            RateLimiter::hit($cleThrottle, 60);

            throw ValidationException::withMessages([
                'email' => 'Ces identifiants ne correspondent à aucun compte.',
            ]);
        }

        // Identifiants corrects mais compte bloqué par un administrateur
        // (voir UserController::basculerBlocage()) : on annule la connexion
        // qu'Auth::attempt() vient d'ouvrir, sans compter cette tentative
        // dans le compteur anti-force-brute (le mot de passe était le bon).
        // Un compte bloqué déjà connecté est, lui, rejeté à sa prochaine
        // requête par App\Http\Middleware\RejeterUtilisateurBloque — les
        // deux points de contrôle sont nécessaires (voir son docblock).
        if (Auth::user()->estBloque()) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'Ce compte a été bloqué par un administrateur.',
            ]);
        }

        RateLimiter::clear($cleThrottle);
        $request->session()->regenerate();

        return redirect()->intended(route('gestion.dashboard'));
    }

    public function deconnexion(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Formulaire "mot de passe oublié" — l'utilisateur y saisit son email
     * pour recevoir un lien de réinitialisation (voir config/auth.php, le
     * broker "users" standard de Laravel, déjà configuré de base : table
     * password_reset_tokens, lien valable 60 minutes).
     */
    public function formulaireOubli(): View
    {
        if (Auth::check()) {
            return redirect()->route('gestion.dashboard');
        }

        return view('auth.mot-de-passe-oublie');
    }

    /**
     * Envoie le lien de réinitialisation par email, via le broker standard
     * de Laravel (Password::sendResetLink()). Tant qu'aucun serveur SMTP
     * réel n'est configuré (voir MAIL_MAILER dans .env, "log" par défaut),
     * l'email n'est écrit que dans le fichier de log — à communiquer au
     * client une fois cet écran livré.
     *
     * Même garde-fou anti-abus que la connexion (5 tentatives/minute par
     * email+IP) : sans cela, un visiteur pourrait déclencher l'envoi en
     * boucle d'emails vers une adresse qui ne lui appartient pas.
     *
     * Le message de retour est VOLONTAIREMENT identique que l'email
     * corresponde ou non à un compte existant, pour ne pas permettre à un
     * visiteur de deviner quelles adresses sont inscrites sur la
     * plateforme (énumération de comptes) — Password::sendResetLink() ne
     * fait rien de visible si l'email est inconnu, mais ne lève pas
     * d'erreur non plus.
     */
    public function envoyerLienReinitialisation(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $cleThrottle = $this->cleThrottle($request);

        if (RateLimiter::tooManyAttempts($cleThrottle, 5)) {
            $secondes = RateLimiter::availableIn($cleThrottle);

            throw ValidationException::withMessages([
                'email' => "Trop de tentatives. Réessayez dans {$secondes} seconde(s).",
            ]);
        }

        RateLimiter::hit($cleThrottle, 60);

        Password::sendResetLink($request->only('email'));

        return back()->with(
            'status',
            'Si cette adresse correspond à un compte, un lien de réinitialisation vient de lui être envoyé.'
        );
    }

    /** Formulaire de saisie du nouveau mot de passe, à partir du lien reçu par email. */
    public function formulaireReinitialisation(Request $request, string $token): View
    {
        return view('auth.reinitialiser-mot-de-passe', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    /**
     * Valide le jeton reçu par email puis change le mot de passe —
     * Password::reset() vérifie lui-même que le jeton correspond bien à
     * l'email fourni et qu'il n'a pas expiré (60 minutes, voir
     * config/auth.php), sans quoi $statut ne vaut pas PASSWORD_RESET.
     *
     * Même politique de mot de passe que partout ailleurs sur la
     * plateforme (Password::default(), voir AppServiceProvider::boot()) —
     * pas de règle plus faible ici juste parce qu'il s'agit d'un
     * formulaire public avant connexion.
     */
    public function reinitialiserMotDePasse(Request $request): RedirectResponse
    {
        $donnees = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'confirmed', RegleMotDePasse::default()],
        ]);

        $statut = Password::reset($donnees, function (User $utilisateur, string $motDePasse) {
            $utilisateur->forceFill([
                'password' => $motDePasse, // casté 'hashed', voir App\Models\User::$casts
            ])->setRememberToken(Str::random(60));

            $utilisateur->save();

            event(new PasswordReset($utilisateur));
        });

        if ($statut !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($statut)],
            ]);
        }

        return redirect()->route('login')->with('status', 'Votre mot de passe a été réinitialisé. Vous pouvez désormais vous connecter.');
    }

    protected function cleThrottle(Request $request): string
    {
        return Str::transliterate(Str::lower($request->input('email', ''))).'|'.$request->ip();
    }
}
