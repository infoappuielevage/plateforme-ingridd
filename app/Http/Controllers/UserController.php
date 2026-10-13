<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ListeTriable;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Notifications\CompteBloqueNotification;
use App\Notifications\MotDePasseReinitialiseParAdminNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password as BrokerMotDePasse;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

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
    use ListeTriable;

    public function index(Request $request): View
    {
        abort_unless(Auth::user()?->role === User::ROLE_ADMIN, 403);

        $colonnesTriables = $this->colonnesTriables();
        $triResolu = $this->resoudreTri($request, $colonnesTriables, 'name', 'asc');

        $requete = User::query();
        $this->appliquerTri($requete, $colonnesTriables, $triResolu);

        // paginate(8) plutôt que get() : demande explicite du client (tous
        // les tableaux de l'espace de gestion limités à 8 lignes, le reste
        // accessible par numéro de page — voir App\Providers\AppServiceProvider
        // ::boot(), qui configure le rendu Bootstrap 5 des liens de pagination).
        return view('gestion.utilisateurs.index', [
            'utilisateurs' => $requete->paginate(8)->withQueryString(),
            'triActuel' => $triResolu[0],
            'directionActuelle' => $triResolu[1],
        ]);
    }

    /**
     * Colonnes triables de la liste (voir App\Http\Controllers\Concerns\ListeTriable)
     * — demande explicite du client ("ajoute la fonctionnalité de trier sur
     * les tableaux"). "Statut" trie sur `bloque_at` (NULL = actif) : un
     * compte bloqué a une date, donc asc/desc le place naturellement d'un
     * côté ou de l'autre du tri.
     *
     * @return array<string, string>
     */
    protected function colonnesTriables(): array
    {
        return [
            'name' => 'name',
            'email' => 'email',
            'role' => 'role',
            'bloque_at' => 'bloque_at',
            'derniere_connexion_at' => 'derniere_connexion_at',
        ];
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

    /**
     * Bloque ou débloque un compte — demande explicite du client
     * ("l'administration doit pouvoir réinitialiser un compte ou le
     * bloquer"). Un compte bloqué ne peut plus se connecter
     * (AuthController::connexion()) et, s'il est déjà connecté, en est
     * immédiatement déconnecté (App\Http\Middleware\RejeterUtilisateurBloque).
     *
     * `bloque_at` n'est jamais dans User::$fillable (voir son docblock) :
     * uniquement modifiable ici, via forceFill() — comme destroy(), jamais
     * sur soi-même (un admin ne doit pas pouvoir se couper lui-même l'accès).
     */
    public function basculerBlocage(User $utilisateur): RedirectResponse
    {
        abort_unless(Auth::user()?->role === User::ROLE_ADMIN, 403);
        abort_if($utilisateur->id === Auth::id(), 403);

        if ($utilisateur->estBloque()) {
            $utilisateur->forceFill(['bloque_at' => null])->save();
            $action = 'utilisateur_debloque';
            $message = "Compte de {$utilisateur->name} débloqué.";
        } else {
            $utilisateur->forceFill(['bloque_at' => now()])->save();
            $action = 'utilisateur_bloque';
            $message = "Compte de {$utilisateur->name} bloqué.";
        }

        AuditLog::create([
            'user_id' => Auth::id(),
            'user_name' => Auth::user()->name,
            'action' => $action,
            'model_type' => User::class,
            'model_id' => $utilisateur->id,
            'resume' => "{$message} ({$utilisateur->email}) par ".Auth::user()->name,
        ]);

        // Cloche de notifications (voir layouts/gestion.blade.php) : la
        // personne concernée voit pourquoi son compte s'est mis à refuser la
        // connexion, ou qu'elle peut de nouveau s'y connecter — voir le
        // docblock de App\Notifications\CompteBloqueNotification.
        $utilisateur->notify(new CompteBloqueNotification($utilisateur->estBloque()));

        return redirect()->route('gestion.utilisateurs.index')->with('succes', $message);
    }

    /**
     * Envoie à l'utilisateur le même email "mot de passe oublié" que celui
     * qu'il pourrait demander lui-même (AuthController::envoyerLienReinitialisation(),
     * même broker Password standard de Laravel) — pratique pour un compte
     * qui ne se souvient plus de son mot de passe ET de son email
     * (l'administrateur connaît forcément les deux). Le mot de passe
     * lui-même n'est jamais vu ni choisi par l'administrateur : c'est
     * l'utilisateur qui le définit en suivant le lien reçu, exactement comme
     * un "mot de passe oublié" classique.
     */
    public function reinitialiserMotDePasse(User $utilisateur): RedirectResponse
    {
        abort_unless(Auth::user()?->role === User::ROLE_ADMIN, 403);

        BrokerMotDePasse::sendResetLink(['email' => $utilisateur->email]);

        AuditLog::create([
            'user_id' => Auth::id(),
            'user_name' => Auth::user()->name,
            'action' => 'reinitialisation_mot_de_passe_demandee',
            'model_type' => User::class,
            'model_id' => $utilisateur->id,
            'resume' => "Lien de réinitialisation du mot de passe envoyé à {$utilisateur->name} ({$utilisateur->email}) par ".Auth::user()->name,
        ]);

        // Cloche de notifications (voir layouts/gestion.blade.php) — simple
        // rappel dans l'application, le lien lui-même part par email (voir
        // le docblock de App\Notifications\MotDePasseReinitialiseParAdminNotification).
        $utilisateur->notify(new MotDePasseReinitialiseParAdminNotification);

        return redirect()->route('gestion.utilisateurs.index')->with('succes', "Lien de réinitialisation envoyé à {$utilisateur->email}.");
    }

    protected function valider(Request $request, ?User $utilisateur): array
    {
        $donnees = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($utilisateur?->id)],
            'role' => ['required', 'string', Rule::exists('roles', 'slug')],
            // Password::default() plutôt qu'un simple 'min:8' (revue de
            // sécurité demandée par le client) : reprend EXACTEMENT la même
            // politique que ProfilController::update() (voir
            // App\Providers\AppServiceProvider::boot()) — min 10, majuscule
            // + minuscule, au moins un chiffre, et vérification "déjà
            // compromis" (haveibeenpwned) en production uniquement — plutôt
            // qu'une seconde règle divergente ('min:8' seul) qui laissait un
            // admin créer un compte "aaaaaaaa" pour un autre utilisateur,
            // moins protégé que son propre changement de mot de passe.
            'password' => [$utilisateur ? 'nullable' : 'required', 'string', Password::default()],
        ]);

        return $donnees;
    }
}
