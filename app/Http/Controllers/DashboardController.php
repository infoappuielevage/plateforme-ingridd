<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Filiere;
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
 *
 * Les 4 graphiques (voir graphiques()) reprennent de la même façon les
 * anciens App\Filament\Widgets\*ChartWidget (retirés, plus de Filament) —
 * mêmes données, rendues côté client avec Chart.js (voir
 * resources/views/gestion/dashboard.blade.php) plutôt que le rendu Livewire
 * de Filament.
 */
class DashboardController extends Controller
{
    public function index(): View
    {
        $documentsCeMois = Document::query()
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();

        $espaceDisque = $this->espaceDisque();

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
                'description' => $espaceDisque['description'],
                'icone' => 'bi-server',
                'accent' => false,
                'alerte' => $espaceDisque['alerte'],
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
            'graphiques' => $this->graphiques($typeDiplomeAttestationIds),
        ]);
    }

    /**
     * @param  array<int, int>  $typeDiplomeAttestationIds
     * @return array<string, array{titre: string, type: string, labels: array<int, string>, donnees: array<int, int>, couleurs: array<int, string>|string}>
     */
    protected function graphiques(array $typeDiplomeAttestationIds): array
    {
        $graphiques = [
            'parMois' => [
                'titre' => 'Documents ajoutés par mois',
                'type' => 'line',
                'couleurs' => '#6366f1',
                ...$this->graphiqueDocumentsParMois(),
            ],
            'parType' => [
                'titre' => 'Documents par type',
                'type' => 'doughnut',
                'couleurs' => ['#6366f1', '#22c55e', '#f97316', '#ef4444', '#0ea5e9', '#a855f7'],
                ...$this->graphiqueDocumentsParType(),
            ],
            'parFiliere' => [
                'titre' => 'Documents par filière',
                'type' => 'bar',
                'couleurs' => '#22c55e',
                ...$this->graphiqueDocumentsParFiliere(),
            ],
        ];

        // Même dégradation silencieuse que StatsOverviewWidget/
        // DiplomesRetraitChartWidget : absent si le référentiel ne contient
        // ni "Diplôme" ni "Attestation", plutôt qu'un graphique à 0/0.
        if (count($typeDiplomeAttestationIds) > 0) {
            $graphiques['diplomesRetrait'] = [
                'titre' => 'Diplômes et attestations — retraits',
                'type' => 'doughnut',
                'couleurs' => ['#f97316', '#22c55e'],
                ...$this->graphiqueDiplomesRetrait($typeDiplomeAttestationIds),
            ];
        }

        return $graphiques;
    }

    /**
     * Tendance d'ajout de documents sur les 12 derniers mois. Comptages
     * faits en PHP (une seule requête qui ramène les dates, regroupées
     * ensuite avec Carbon) plutôt qu'avec une fonction SQL de date : pas la
     * même syntaxe d'un moteur à l'autre (DATE_FORMAT en MySQL, strftime en
     * SQLite), et les tests tournent sur SQLite en mémoire alors que
     * l'application tourne en production sur MySQL.
     *
     * @return array{labels: array<int, string>, donnees: array<int, int>}
     */
    protected function graphiqueDocumentsParMois(): array
    {
        $depuis = now()->subMonths(11)->startOfMonth();

        $dates = Document::query()
            ->withTrashed()
            ->where('created_at', '>=', $depuis)
            ->pluck('created_at');

        $mois = collect(range(0, 11))
            ->map(fn (int $i) => now()->subMonths(11 - $i)->startOfMonth());

        $comptes = $mois->map(
            fn (Carbon $mois) => $dates->filter(
                fn ($date) => $date->isSameMonth($mois) && $date->isSameYear($mois)
            )->count()
        );

        return [
            'labels' => $mois->map(fn (Carbon $mois) => ucfirst($mois->translatedFormat('M Y')))->all(),
            'donnees' => $comptes->all(),
        ];
    }

    /** @return array{labels: array<int, string>, donnees: array<int, int>} */
    protected function graphiqueDocumentsParType(): array
    {
        $types = TypeDocument::query()->withCount('documents')->orderBy('ordre')->get();

        return [
            'labels' => $types->pluck('libelle')->all(),
            'donnees' => $types->pluck('documents_count')->all(),
        ];
    }

    /** @return array{labels: array<int, string>, donnees: array<int, int>} */
    protected function graphiqueDocumentsParFiliere(): array
    {
        $filieres = Filiere::query()->withCount('documents')->orderBy('ordre')->get();

        return [
            'labels' => $filieres->pluck('libelle')->all(),
            'donnees' => $filieres->pluck('documents_count')->all(),
        ];
    }

    /**
     * @param  array<int, int>  $typeDiplomeAttestationIds
     * @return array{labels: array<int, string>, donnees: array<int, int>}
     */
    protected function graphiqueDiplomesRetrait(array $typeDiplomeAttestationIds): array
    {
        $parStatut = Document::query()
            ->whereIn('type_document_id', $typeDiplomeAttestationIds)
            ->selectRaw('statut_retrait, count(*) as total')
            ->groupBy('statut_retrait')
            ->pluck('total', 'statut_retrait');

        return [
            'labels' => ['Non retiré', 'Retiré'],
            'donnees' => [
                (int) ($parStatut[Document::STATUT_RETRAIT_NON_RETIRE] ?? 0),
                (int) ($parStatut[Document::STATUT_RETRAIT_RETIRE] ?? 0),
            ],
        ];
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

    /**
     * Espace RÉELLEMENT libre sur le disque du serveur, pas seulement la
     * somme des tailles enregistrées en base (voir la carte "Espace de
     * stockage" ci-dessus, qui affiche déjà ce total) — demande explicite du
     * client, qui voulait un vrai indicateur pour anticiper un disque plein,
     * pas seulement le volume déjà archivé.
     *
     * `disk_free_space()`/`disk_total_space()` sont natives PHP (aucune
     * dépendance), fonctionnent aussi bien sous Windows (XAMPP) que Linux, et
     * portent sur le disque QUI HÉBERGE le dossier `storage/app/documents`
     * (voir config/filesystems.php, disque `documents`) — pas un chiffre
     * générique, le bon disque même si le serveur en a plusieurs.
     *
     * `@` + vérification `=== false` : certains hébergeurs désactivent ces
     * fonctions (`disable_functions` dans php.ini) par mesure de sécurité —
     * dans ce cas on affiche simplement l'ancien texte plutôt que de planter
     * le tableau de bord.
     */
    protected function espaceDisque(): array
    {
        $chemin = Storage::disk('documents')->path('');
        $libre = @disk_free_space($chemin);
        $total = @disk_total_space($chemin);

        if ($libre === false || $total === false || $total <= 0) {
            return ['description' => 'Total des fichiers archivés', 'alerte' => false];
        }

        $pourcentageLibre = ($libre / $total) * 100;

        return [
            'description' => Formatage::octets((int) $libre).' libres sur le disque ('.round(100 - $pourcentageLibre).'% utilisé)',
            // Moins de 10% ou moins de 2 Go libres : signal d'alerte visuel
            // (même style que la carte "Dernière sauvegarde" en retard, voir
            // dashboard.blade.php) pour anticiper avant que le disque soit
            // réellement plein.
            'alerte' => $pourcentageLibre < 10 || $libre < 2 * 1024 ** 3,
        ];
    }
}
