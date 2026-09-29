<?php

namespace App\Http\Controllers;

use App\Models\Parametre;
use App\Models\User;
use App\Support\TypesFichiers;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Réglages de la plateforme (une seule ligne en base, voir App\Models\Parametre)
 * — remplace App\Filament\Pages\Parametres. Réservé en dur au rôle admin, comme
 * la page Filament d'origine (pas de clé ResourceAccess ici : certains de ces
 * réglages, en particulier la rétention de l'historique, ne doivent jamais
 * pouvoir être accordés à un rôle par une simple mauvaise configuration des
 * permissions).
 */
class ParametresController extends Controller
{
    public function index(): View
    {
        abort_unless(Auth::user()?->role === User::ROLE_ADMIN, 403);

        return view('gestion.parametres.index', [
            'parametres' => Parametre::actuel(),
            'familles' => TypesFichiers::optionsPourFormulaire(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless(Auth::user()?->role === User::ROLE_ADMIN, 403);

        $donnees = $request->validate([
            'nom_application' => ['required', 'string', 'max:150'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp,svg', 'max:1024'],
            'couleur_principale' => ['required', 'string', 'max:20'],
            'types_fichiers_autorises' => ['required', 'array', 'min:1'],
            'types_fichiers_autorises.*' => ['string', 'in:'.implode(',', TypesFichiers::toutesLesCles())],
            'taille_max_document_mo' => ['required', 'integer', 'min:1', 'max:500'],
            'taille_max_avatar_mo' => ['required', 'integer', 'min:1', 'max:20'],
            'retention_audit_jours' => ['required', 'integer', 'min:30'],
            'frequence_sauvegarde_auto' => ['required', 'string', 'in:quotidienne,hebdomadaire,mensuelle'],
            'retention_sauvegardes_nombre' => ['required', 'integer', 'min:1'],
            'mysqldump_chemin' => ['nullable', 'string', 'max:255'],
        ]);

        $parametres = Parametre::actuel();
        $ancienLogo = null;

        if ($request->hasFile('logo')) {
            $ancienLogo = $parametres->logo;
            $donnees['logo'] = $request->file('logo')->store('logo', 'public');
        } else {
            // Pas de nouveau logo : on garde celui déjà enregistré, jamais
            // touché par ce formulaire (le champ `logo` n'est pas envoyé du
            // tout quand aucun fichier n'est choisi — voir gestion/
            // parametres/index.blade.php).
            unset($donnees['logo']);
        }

        $parametres->fill($donnees)->save();
        Parametre::oublierCache();

        if ($ancienLogo) {
            Storage::disk('public')->delete($ancienLogo);
        }

        return redirect()->route('gestion.parametres.index')->with('succes', 'Paramètres enregistrés.');
    }
}
