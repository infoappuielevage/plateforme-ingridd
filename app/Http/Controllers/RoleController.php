<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ListeTriable;
use App\Models\Role;
use App\Models\User;
use App\Support\ResourceAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Rôles & permissions — remplace App\Filament\Resources\RoleResource sur
 * demande du client (plus de Filament). Réservé en dur au rôle admin (pas de
 * clé ResourceAccess ici, volontairement — voir la note en tête de
 * App\Support\ResourceAccess) : un rôle mal configuré ne doit jamais pouvoir
 * s'accorder lui-même l'accès à cet écran ni les pleins pouvoirs.
 *
 * Pas de SoftDeletes sur App\Models\Role (suppression directe) : le seul
 * garde-fou repris de RoleResource::table() est d'interdire la suppression
 * du rôle "admin" lui-même — un utilisateur avec ce rôle doit toujours
 * pouvoir se connecter et tout gérer, quoi qu'il arrive aux autres rôles.
 */
class RoleController extends Controller
{
    use ListeTriable;

    public function index(Request $request): View
    {
        abort_unless(Auth::user()?->role === User::ROLE_ADMIN, 403);

        $colonnesTriables = $this->colonnesTriables();
        $triResolu = $this->resoudreTri($request, $colonnesTriables, 'name', 'asc');

        $requete = Role::query()->withCount('users');
        $this->appliquerTri($requete, $colonnesTriables, $triResolu);

        // paginate(8) plutôt que get() : demande explicite du client (voir
        // le commentaire équivalent dans UserController::index()).
        return view('gestion.roles.index', [
            'roles' => $requete->paginate(8)->withQueryString(),
            'triActuel' => $triResolu[0],
            'directionActuelle' => $triResolu[1],
        ]);
    }

    /**
     * "Utilisateurs" trie sur l'alias `users_count` ajouté par
     * ->withCount('users') ci-dessus — Eloquent l'expose comme une colonne
     * sélectionnée ordinaire, un orderBy() direct dessus fonctionne sans
     * sous-requête supplémentaire.
     *
     * @return array<string, string>
     */
    protected function colonnesTriables(): array
    {
        return [
            'slug' => 'slug',
            'name' => 'name',
            'users_count' => 'users_count',
        ];
    }

    public function create(): View
    {
        abort_unless(Auth::user()?->role === User::ROLE_ADMIN, 403);

        return view('gestion.roles.form', ['role' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(Auth::user()?->role === User::ROLE_ADMIN, 403);

        Role::create($this->valider($request, null));

        return redirect()->route('gestion.roles.index')->with('succes', 'Rôle ajouté.');
    }

    public function edit(Role $role): View
    {
        abort_unless(Auth::user()?->role === User::ROLE_ADMIN, 403);

        return view('gestion.roles.form', ['role' => $role]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        abort_unless(Auth::user()?->role === User::ROLE_ADMIN, 403);

        $role->update($this->valider($request, $role));

        return redirect()->route('gestion.roles.index')->with('succes', 'Rôle modifié.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        abort_unless(Auth::user()?->role === User::ROLE_ADMIN, 403);

        // Même garde-fou que RoleResource::table() : jamais le rôle "admin"
        // lui-même, quoi qu'il arrive aux autres rôles.
        abort_if($role->slug === User::ROLE_ADMIN, 403);

        $role->delete();

        return redirect()->route('gestion.roles.index')->with('succes', 'Rôle supprimé.');
    }

    protected function valider(Request $request, ?Role $role): array
    {
        $donnees = $request->validate([
            'slug' => [
                $role ? 'nullable' : 'required', 'string', 'max:50', 'alpha_dash',
                Rule::unique('roles', 'slug')->ignore($role?->id),
            ],
            'name' => ['required', 'string', 'max:100'],
        ]);

        if ($role) {
            unset($donnees['slug']);
        }

        // La matrice de permissions est toujours reconstruite en entier ici,
        // plutôt que de faire confiance à ce que le formulaire a envoyé : une
        // case décochée n'est jamais soumise par le navigateur, donc
        // ->boolean() (qui vaut false pour un champ absent) suffit seul, sans
        // avoir besoin d'un champ hidden jumeau par case à cocher (~vingt
        // cases au total, voir gestion/roles/form.blade.php).
        $permissions = [];

        foreach (ResourceAccess::RESSOURCES as $cle => $libelle) {
            foreach (ResourceAccess::ACTIONS as $action => $libelleAction) {
                // Historique : uniquement "Voir" a un sens (lecture seule partout) — même exception que RoleResource::form().
                if ($cle === 'audit_logs' && $action !== 'view') {
                    continue;
                }

                // Verrouillage : uniquement "Modifier" a un sens (un seul geste — verrouiller/déverrouiller un document), voir App\Support\ResourceAccess::RESSOURCES.
                if ($cle === 'verrouillage_documents' && $action !== 'edit') {
                    continue;
                }

                $permissions[$cle][$action] = $request->boolean("permissions.{$cle}.{$action}");
            }
        }

        $donnees['permissions'] = $permissions;

        return $donnees;
    }
}
