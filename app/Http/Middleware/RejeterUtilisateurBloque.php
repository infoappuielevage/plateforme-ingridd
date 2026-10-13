<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Déconnecte immédiatement tout utilisateur dont le compte a été bloqué par
 * un administrateur (voir UserController::basculerBlocage()).
 *
 * Sans ce middleware, un compte bloqué EN COURS DE SESSION continuerait de
 * fonctionner normalement jusqu'à sa prochaine tentative de connexion
 * volontaire (voir AuthController::connexion(), qui ne vérifie le blocage
 * qu'à ce moment-là) — un simple cookie de session déjà valide suffirait à
 * continuer d'utiliser la plateforme malgré le blocage.
 *
 * Posé sur le groupe `web` global (bootstrap/app.php), pas seulement sur les
 * routes `gestion.*` : couvre aussi le cas où le compte bloqué a encore un
 * onglet ouvert sur une autre route authentifiée (ex. téléchargement d'un
 * document, routes/web.php).
 */
class RejeterUtilisateurBloque
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $utilisateur */
        $utilisateur = Auth::user();

        if ($utilisateur && $utilisateur->estBloque()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('status', 'Ce compte a été bloqué par un administrateur.');
        }

        return $next($request);
    }
}
