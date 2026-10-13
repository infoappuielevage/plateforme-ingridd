<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Notifications\AnnonceAdministrateurNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

/**
 * Administration > Annonces — permet à un administrateur de rédiger et
 * d'envoyer lui-même un message (titre + texte libre) dans la cloche de
 * notifications d'un utilisateur précis, de tout un rôle, ou de tout le
 * monde. Demande explicite du client : au-delà des alertes automatiques
 * (blocage de compte, réinitialisation de mot de passe, sauvegarde/espace
 * disque — voir app/Notifications/), il voulait pouvoir "gérer" lui-même
 * des notifications, pas seulement les recevoir.
 *
 * Réservé en dur au rôle admin (pas de clé ResourceAccess ici, volontairement
 * — même raisonnement que RoleController/UserController) : annoncer
 * quelque chose à TOUS les comptes de la plateforme est une action trop
 * sensible pour dépendre d'une matrice de permissions modifiable.
 *
 * Aucun historique des annonces déjà envoyées n'est conservé ici (pas de
 * table dédiée) : chaque envoi se contente de créer une notification par
 * destinataire (voir App\Notifications\AnnonceAdministrateurNotification),
 * exactement comme les autres notifications de l'application — à revoir si
 * le client demande un jour à pouvoir consulter/renvoyer une annonce passée.
 */
class AnnonceController extends Controller
{
    public function create(): View
    {
        abort_unless(Auth::user()?->role === User::ROLE_ADMIN, 403);

        return view('gestion.annonces.create', [
            'roles' => Role::query()->orderBy('name')->pluck('name', 'slug'),
            'utilisateurs' => User::query()->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(Auth::user()?->role === User::ROLE_ADMIN, 403);

        $donnees = $request->validate([
            'destinataire_type' => ['required', Rule::in(['tous', 'role', 'utilisateur'])],
            'role' => ['required_if:destinataire_type,role', 'nullable', Rule::exists('roles', 'slug')],
            'utilisateur_id' => ['required_if:destinataire_type,utilisateur', 'nullable', Rule::exists('users', 'id')],
            'titre' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $destinataires = match ($donnees['destinataire_type']) {
            'tous' => User::query()->get(),
            'role' => User::query()->where('role', $donnees['role'])->get(),
            'utilisateur' => User::query()->where('id', $donnees['utilisateur_id'])->get(),
        };

        if ($destinataires->isEmpty()) {
            return back()->withErrors(['destinataire_type' => "Aucun destinataire ne correspond à ce choix."])->withInput();
        }

        Notification::send($destinataires, new AnnonceAdministrateurNotification($donnees['titre'], $donnees['message']));

        $nombre = $destinataires->count();
        $libelleDestinataires = $nombre > 1 ? "{$nombre} utilisateurs" : '1 utilisateur';

        return redirect()->route('gestion.annonces.create')->with('succes', "Annonce envoyée à {$libelleDestinataires}.");
    }
}
