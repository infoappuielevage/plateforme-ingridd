<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * Gestion des comptes — remplace App\Filament\Resources\UserResource sur
 * demande du client (plus de Filament). Réservée en dur au rôle admin, comme
 * la Resource Filament d'origine (voir son docblock et RoleController).
 *
 * `avatar`/`derniere_connexion_at`/`derniere_connexion_ip` restent
 * volontairement hors de ce contrôleur (jamais dans $donnees ci-dessous) :
 * voir le docblock de App\Models\User — ces champs ne doivent jamais être
 * modifiables via un ->update() de masse, seulement via forceFill() (page
 * "Mon profil" pour l'avatar, JournaliserActiviteConnexion pour la connexion).
 */
class UserController extends Controller
{
    public function index(): View
    {
        abort_unless(Auth::user()?->role === User::ROLE_ADMIN, 403);

        return view('gestion.utilisateurs.index', [
            'utilisateurs' => User::query()->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        abort_unless(Auth::user()?->role === User::ROLE_ADMIN, 403);

        return view('gestion.utilisateurs.form', [
            'utilisateur' => null,
            'roles' => Role::query()->orderBy('name')->pluck('name', 'slug'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(Auth::user()?->role === User::ROLE_ADMIN, 403);

        $donnees = $this->valider($request, null);
        $donnees['password'] = Hash::make($donnees['password']);

        User::create($donnees);

        return redirect()->route('gestion.utilisateurs.index')->with('succes', 'Utilisateur ajouté.');
    }

    public function edit(User $utilisateur): View
    {
        abort_unless(Auth::user()?->role === User::ROLE_ADMIN, 403);

        return view('gestion.utilisateurs.form', [
            'utilisateur' => $utilisateur,
            'roles' => Role::query()->orderBy('name')->pluck('name', 'slug'),
        ]);
    }

    public function update(Request $request, User $utilisateur): RedirectResponse
    {
        abort_unless(Auth::user()?->role === User::ROLE_ADMIN, 403);

        $donnees = $this->valider($request, $utilisateur);

        if (filled($donnees['password'] ?? null)) {
            $donnees['password'] = Hash::make($donnees['password']);
        } else {
            // Laissé vide : on ne touche pas au mot de passe existant, comme
            // TextInput::make('password')->dehydrated(fn (?string $state) =>
            // filled($state)) côté Filament.
            unset($donnees['password']);
        }

        $utilisateur->update($donnees);

        return redirect()->route('gestion.utilisateurs.index')->with('succes', 'Utilisateur modifié.');
    }

    public function destroy(User $utilisateur): RedirectResponse
    {
        abort_unless(Auth::user()?->role === User::ROLE_ADMIN, 403);

        // Même garde-fou que UserResource::canDelete() : un administrateur
        // ne peut pas se supprimer lui-même (risque de se retrouver sans
        // aucun compte admin restant).
        abort_if($utilisateur->id === Auth::id(), 403);

        $utilisateur->delete();

        return redirect()->route('gestion.utilisateurs.index')->with('succes', 'Utilisateur supprimé.');
    }

    protected function valider(Request $request, ?User $utilisateur): array
    {
        $donnees = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($utilisateur?->id)],
            'role' => ['required', 'string', Rule::exists('roles', 'slug')],
            'password' => [$utilisateur ? 'nullable' : 'required', 'string', 'min:8'],
        ]);

        return $donnees;
    }
}
