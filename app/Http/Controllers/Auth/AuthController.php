<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
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

    protected function cleThrottle(Request $request): string
    {
        return Str::transliterate(Str::lower($request->input('email', ''))).'|'.$request->ip();
    }
}
