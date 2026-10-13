<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Formatage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
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
    public function index(Request $request): View
    {
        abort_unless(Auth::user()?->role === User::ROLE_ADMIN, 403);

        return view('gestion.sauvegardes.index', [
            'sauvegardes' => $this->paginerListe($request),
        ]);
    }

    public function store(): RedirectResponse
    {
        abort_unless(Auth::user()?->role === User::ROLE_ADMIN, 403);

        // La commande elle-même filtre déjà tout Throwable (voir
        // CreerSauvegarde::handle()) ; ce try/catch est une seconde
        // barrière, au cas où quelque chose échouerait avant même d'y
        // entrer (bootstrap d'Artisan::call()) — jamais laisser une
        // exception non gérée remonter jusqu'ici : elle produirait une
        // erreur 500 invisible pour l'utilisateur (voir le docblock de la
        // classe et celui de CreerSauvegarde).
        try {
            $code = Artisan::call('ingridd:sauvegarde');
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'sauvegarde' => 'Erreur inattendue : '.$exception->getMessage(),
            ]);
        }

        if ($code === 0) {
            return back()->with('succes', 'Sauvegarde créée.');
        }

        // La commande explique déjà précisément la cause de l'échec (extension
        // "zip" absente, mysqldump introuvable, base SQLite manquante...) via
        // ses propres appels $this->error()/$this->warn() — mais ceux-ci ne
        // partaient jusqu'ici que dans le buffer console d'Artisan::call(),
        // invisible depuis le navigateur : l'écran affichait un message
        // générique sans aucun indice pour diagnostiquer. On relaie donc ici
        // cette sortie telle quelle, plutôt que de la remplacer par un texte
        // fixe qui masquerait la vraie raison.
        $sortie = trim(Artisan::output());

        return back()->withErrors([
            'sauvegarde' => $sortie !== ''
                ? "Échec de la création de la sauvegarde : {$sortie}"
                : 'Échec de la création de la sauvegarde.',
        ]);
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

    /**
     * Pagination manuelle à 8 par page (demande explicite du client, voir le
     * commentaire équivalent dans UserController::index()) : lister() ne
     * renvoie pas une requête Eloquent (ce sont de simples fichiers sur
     * disque, voir le docblock de la classe), donc pas de ->paginate()
     * disponible nativement — LengthAwarePaginator reproduit exactement le
     * même comportement (et le même rendu Bootstrap 5, voir
     * AppServiceProvider::boot()) à partir d'une Collection déjà chargée en
     * mémoire.
     */
    protected function paginerListe(Request $request): LengthAwarePaginator
    {
        $toutes = collect($this->lister());
        $parPage = 8;
        $page = LengthAwarePaginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            $toutes->forPage($page, $parPage)->values(),
            $toutes->count(),
            $parPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );
    }
}
