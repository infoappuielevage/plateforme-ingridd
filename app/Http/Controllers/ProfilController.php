<?php

namespace App\Http\Controllers;

use App\Models\Parametre;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * "Mon profil" — remplace App\Filament\Pages\ModifierProfil sur demande du
 * client (plus de Filament). Repris à l'identique pour deux points de
 * sécurité :
 *
 * 1. Photo de profil restreinte à PNG/JPEG/WebP (jamais SVG, même si SVG est
 *    un format d'image) : un SVG est un format texte pouvant contenir du
 *    JavaScript (balise <script> ou attributs on*) — l'accepter ouvrirait une
 *    faille XSS stockée, le fichier étant servi tel quel par le navigateur
 *    qui l'affiche.
 * 2. Changer le mot de passe exige de resaisir le mot de passe ACTUEL
 *    (Rule Password + 'current_password') — jamais un simple nouveau mot de
 *    passe sans vérification, qui permettrait à quiconque prend la main sur
 *    une session déjà ouverte de verrouiller le compte derrière un nouveau
 *    mot de passe.
 *
 * `avatar` n'est PAS dans App\Models\User::$fillable (voir son docblock) :
 * cet écran utilise forceFill() pour ce champ précis plutôt qu'un simple
 * update() de masse, volontairement — jamais togglable depuis un autre
 * formulaire (UserController, par ex.) qui pourrait un jour accepter des
 * données non fiables.
 */
class ProfilController extends Controller
{
    protected const TYPES_AVATAR_ACCEPTES = ['image/png', 'image/jpeg', 'image/webp'];

    public function edit(): View
    {
        return view('gestion.profil.edit', [
            'utilisateur' => Auth::user(),
            'tailleMaxAvatarMo' => Parametre::actuel()->taille_max_avatar_mo,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        /** @var User $utilisateur */
        $utilisateur = Auth::user();
        $tailleMaxKo = Parametre::actuel()->taille_max_avatar_mo * 1024;

        $donnees = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($utilisateur->id)],
            'avatar' => ['nullable', 'image', 'mimetypes:'.implode(',', self::TYPES_AVATAR_ACCEPTES), 'max:'.$tailleMaxKo],
            'mot_de_passe_actuel' => ['required_with:nouveau_mot_de_passe', 'nullable', 'current_password'],
            'nouveau_mot_de_passe' => ['nullable', Password::default(), 'confirmed'],
        ]);

        $ancienAvatar = $utilisateur->avatar;
        $nouvelAvatar = $ancienAvatar;

        if ($request->hasFile('avatar')) {
            $nouvelAvatar = $request->file('avatar')->store('avatars', 'public');
        }

        $utilisateur->forceFill([
            'name' => $donnees['name'],
            'email' => $donnees['email'],
            'avatar' => $nouvelAvatar,
        ]);

        if (filled($donnees['nouveau_mot_de_passe'] ?? null)) {
            $utilisateur->password = Hash::make($donnees['nouveau_mot_de_passe']);
        }

        $utilisateur->save();

        // Supprime l'ancienne photo de profil si elle a été remplacée — sans
        // ça, storage/app/public/avatars accumule des fichiers orphelins.
        if ($ancienAvatar && $ancienAvatar !== $nouvelAvatar) {
            Storage::disk('public')->delete($ancienAvatar);
        }

        return redirect()->route('gestion.profil.edit')->with('succes', 'Profil mis à jour.');
    }
}
