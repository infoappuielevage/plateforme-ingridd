<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\TypeDocument;
use App\Models\User;
use App\Support\Formatage;
use App\Support\ResourceAccess;
use App\Support\TypesDiplome;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Tableau de bord de l'espace de gestion — reprend exactement les mêmes
 * chiffres que l'ancien App\Filament\Widgets\StatsOverviewWidget (mêmes
 * calculs, mêmes conditions de visibilité selon les droits), juste affichés
 * avec les cartes façon "bibliothèque numérique" plutôt qu'un widget Filament.
 */
class DashboardController extends Controller
{
    public function index(): View
    {
        $documentsCeMois = Document::query()
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();

        $cartes = [
            [
                'label' => 'Documents',
                'valeur' => Document::query()->count(),
                'description' => "{$documentsCeMois} ajouté(s) ce mois-ci",
                'icone' => 'bi-file-earmark-text',
                'accent' => false,
            ],
            [
                'label' => 'Types actifs',
                'valeur' => TypeDocument::query()->where('actif', true)->count(),
                'description' => null,
                'icone' => 'bi-tags',
                'accent' => true,
            ],
            [
                'label' => 'Espace de stockage',
                'valeur' => Formatage::octets((int) Document::query()->sum('taille')),
                'description' => 'Total des fichiers archivés',
                'icone' => 'bi-server',
                'accent' => false,
            ],
        ];

        // Même dégradation silencieuse que StatsOverviewWidget : masquée si
        // le référentiel ne contient ni "Diplôme" ni "Attestation".
        $typeDiplomeAttestationIds = TypesDiplome::ids();

        if (count($typeDiplomeAttestationIds) > 0) {
            $nonRetires = Document::query()
                ->whereIn('type_document_id', $typeDiplomeAttestationIds)
                ->where('statut_retrait', Document::STATUT_RETRAIT_NON_RETIRE)
                ->count();

            $cartes[] = [
                'label' => 'Diplômes/attestations non retirés',
                'valeur' => $nonRetires,
                'description' => $nonRetires > 0 ? 'À faire retirer par les étudiants concernés' : 'Tous retirés',
                'icone' => 'bi-clipboard-check',
                'accent' => true,
            ];
        }

        if (ResourceAccess::can('documents', 'delete')) {
            $enCorbeille = Document::onlyTrashed()->count();

            $cartes[] = [
                'label' => 'En corbeille',
                'valeur' => $enCorbeille,
                'description' => $enCorbeille > 0 ? 'À restaurer ou supprimer définitivement' : 'Corbeille vide',
                'icone' => 'bi-trash3',
                'accent' => false,
            ];
        }

        $derniereSauvegarde = null;

        if (Auth::user()?->role === User::ROLE_ADMIN) {
            $derniereSauvegarde = $this->derniereSauvegarde();
        }

        // Documents les plus récents, à défaut d'un compteur de consultations
        // (qui n'existe pas sur ce projet, contrairement à ingridd-biblio) —
        // sert le même but : un aperçu concret dès l'arrivée sur le tableau
        // de bord, plutôt qu'un écran vide.
        $documentsRecents = Document::query()
            ->with(['typeDocument', 'sousTypeDocument', 'uploadePar'])
            ->latest('created_at')
            ->limit(8)
            ->get();

        return view('gestion.dashboard', [
            'cartes' => $cartes,
            'derniereSauvegarde' => $derniereSauvegarde,
            'documentsRecents' => $documentsRecents,
        ]);
    }

    protected function derniereSauvegarde(): array
    {
        $derniere = collect(Storage::disk('sauvegardes')->files())
            ->filter(fn (string $fichier) => str_ends_with($fichier, '.zip'))
            ->map(fn (string $fichier) => Storage::disk('sauvegardes')->lastModified($fichier))
            ->sort()
            ->last();

        if (! $derniere) {
            return [
                'valeur' => 'Aucune',
                'description' => 'Créez-en une depuis Administration > Sauvegardes',
                'alerte' => true,
            ];
        }

        $date = Carbon::createFromTimestamp($derniere);

        return [
            'valeur' => $date->diffForHumans(),
            'description' => $date->format('d/m/Y H:i'),
            'alerte' => $date->lt(now()->subWeek()),
        ];
    }
}
