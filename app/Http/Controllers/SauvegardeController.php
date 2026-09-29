<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Formatage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Sauvegardes (Administration > Sauvegardes) — remplace App\Filament\Pages\
 * Sauvegardes sur demande du client (plus de Filament). Réservé en dur au
 * rôle admin, comme la page Filament d'origine (voir son docblock).
 *
 * Pas de modèle Eloquent ici : les sauvegardes sont des fichiers sur le
 * disque `sauvegardes` (voir config/filesystems.php), pas des lignes en base
 * — ce contrôleur liste/crée/supprime directement sur ce disque.
 *
 * creer() appelle la commande `ingridd:sauvegarde` de façon SYNCHRONE, comme
 * la page Filament d'origine : acceptable pour un volume de documents
 * raisonnable, voir le docblock d'origine pour la limite (hors périmètre de
 * cette v1 : passer par une file d'attente si le volume grossit trop).
 */
class SauvegardeController extends Controller
{
    public function index(): View
    {
        abort_unless(Auth::user()?->role === User::ROLE_ADMIN, 403);

        return view('gestion.sauvegardes.index', [
            'sauvegardes' => $this->lister(),
        ]);
    }

    public function store(): RedirectResponse
    {
        abort_unless(Auth::user()?->role === User::ROLE_ADMIN, 403);

        $code = Artisan::call('ingridd:sauvegarde');

        return $code === 0
            ? back()->with('succes', 'Sauvegarde créée.')
            : back()->withErrors(['sauvegarde' => 'Échec de la création de la sauvegarde.']);
    }

    public function destroy(string $nom): RedirectResponse
    {
        abort_unless(Auth::user()?->role === User::ROLE_ADMIN, 403);

        // `$nom` vient de l'URL : jamais de confiance avant de le passer à
        // Storage — voir la même garde sur la route de téléchargement
        // (routes/web.php, `sauvegardes.telecharger`).
        abort_if(str_contains($nom, '/') || str_contains($nom, '\\'), 403);

        Storage::disk('sauvegardes')->delete($nom);

        return back()->with('succes', 'Sauvegarde supprimée.');
    }

    /** @return array<int, array{nom: string, taille: string, date: string, horodatage: int}> */
    protected function lister(): array
    {
        return collect(Storage::disk('sauvegardes')->files())
            ->filter(fn (string $fichier) => str_ends_with($fichier, '.zip'))
            ->map(fn (string $fichier) => [
                'nom' => $fichier,
                'taille' => Formatage::octets(Storage::disk('sauvegardes')->size($fichier)),
                'date' => Carbon::createFromTimestamp(Storage::disk('sauvegardes')->lastModified($fichier))->format('d/m/Y H:i'),
                'horodatage' => Storage::disk('sauvegardes')->lastModified($fichier),
            ])
            ->sortByDesc('horodatage')
            ->values()
            ->all();
    }
}
