<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Filiere;
use App\Models\Role;
use App\Models\TypeDocument;
use App\Models\User;
use App\Support\CorbeillesGlobales;
use App\Support\Formatage;
use App\Support\ResourceAccess;
use App\Support\TypesDiplome;
use App\Support\TypesEcranDedie;
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
        // Garde-fou pour TOUTE la section "Documents" du tableau de bord
        // (cartes "Documents"/"Diplômes non retirés", liste "Documents
        // récemment ajoutés", graphiques) — faille de sécurité corrigée sur
        // demande explicite du client ("revoir la sécurité pour éviter
        // l'accessibilité à des pages confidentielles sans autorisation") :
        // rien ici ne vérifiait auparavant le droit "documents" -> "Voir",
        // si bien qu'un rôle n'ayant AUCUN droit documentaire (ex. les rôles
        // "Direction"/"Secrétariat"/... créés sans permission par
        // database/seeders/RoleSeeder.php, en attendant d'être configurés)
        // voyait quand même des noms de fichiers réels dans "Documents
        // récemment ajoutés" rien qu'en arrivant sur son tableau de bord.
        // Les graphiques étaient déjà protégés côté vue (voir
        // gestion/dashboard.blade.php), mais pas les cartes ni cette liste.
        $peutVoirDocuments = ResourceAccess::can('documents', 'view');

        $espaceDisque = $this->espaceDisque();

        $cartes = [
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

        // Jamais les documents d'un écran dédié dans les chiffres ci-dessous
        // (voir App\Support\TypesEcranDedie) : le droit générique
        // "documents" -> "Voir" ne couvre plus ces types (même correction
        // que DocumentController::index()) — un chiffre cohérent avec ce que
        // "Tous les documents" affiche réellement.
        $typeDiplomeAttestationIds = TypesDiplome::ids();

        if ($peutVoirDocuments) {
            $documentsCeMois = Document::query()
                ->whereNotIn('type_document_id', TypesEcranDedie::ids())
                ->where('created_at', '>=', now()->startOfMonth())
                ->count();

            array_unshift($cartes, [
                'label' => 'Documents',
                'valeur' => Document::query()->whereNotIn('type_document_id', TypesEcranDedie::ids())->count(),
                'description' => "{$documentsCeMois} ajouté(s) ce mois-ci",
                'icone' => 'bi-file-earmark-text',
                'accent' => false,
            ]);

            // Même dégradation silencieuse que StatsOverviewWidget : masquée
            // si le référentiel ne contient ni "Diplôme" ni "Attestation".
            // (DIPLOME/ATTESTATION n'ont pas d'écran dédié, voir
            // App\Support\TypesEcranDedie : rien à exclure ici.)
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

            // Pour un non-admin, on garde EXACTEMENT l'ancien chiffre (documents
            // génériques seulement) : lui donner le total toutes corbeilles
            // confondues (CorbeillesGlobales::total(), voir ci-dessous)
            // reviendrait à lui montrer des comptages d'écrans qu'il n'a pas
            // forcément le droit de voir, alors que "documents" -> "Voir" ne
            // couvre que les documents génériques.
            if (ResourceAccess::can('documents', 'delete') && Auth::user()?->role !== User::ROLE_ADMIN) {
                $enCorbeille = Document::onlyTrashed()->whereNotIn('type_document_id', TypesEcranDedie::ids())->count();

                $cartes[] = [
                    'label' => 'En corbeille',
                    'valeur' => $enCorbeille,
                    'description' => $enCorbeille > 0 ? 'À restaurer ou supprimer définitivement' : 'Corbeille vide',
                    'icone' => 'bi-trash3',
                    'accent' => false,
                ];
            }
        }

        // Carte "En corbeille" de l'administrateur — demande explicite du
        // client ("le compteur du tableau de bord ne compte que les
        // documents génériques, exclut tout le reste") : remplace, pour
        // l'admin seulement, le chiffre ci-dessus par le VRAI total toutes
        // corbeilles confondues (voir App\Support\CorbeillesGlobales), avec
        // un lien direct vers le nouvel écran de détail plutôt que vers la
        // seule corbeille "Documents". Indépendant de $peutVoirDocuments :
        // un admin a de toute façon tous les droits, et ce chiffre couvre
        // bien plus que les seuls documents.
        if (Auth::user()?->role === User::ROLE_ADMIN) {
            $enCorbeilleGlobale = CorbeillesGlobales::total();

            $cartes[] = [
                'label' => 'En corbeille',
                'valeur' => $enCorbeilleGlobale,
                'description' => $enCorbeilleGlobale > 0 ? 'À restaurer ou supprimer définitivement' : 'Toutes les corbeilles sont vides',
                'icone' => 'bi-trash3',
                'accent' => false,
                'lien' => route('gestion.corbeilles.index'),
            ];
        }

        $derniereSauvegarde = null;

        if (Auth::user()?->role === User::ROLE_ADMIN) {
            $derniereSauvegarde = $this->derniereSauvegarde();
        }

        // Documents les plus récents, à défaut d'un compteur de consultations
        // (qui n'existe pas sur ce projet, contrairement à ingridd-biblio) —
        // sert le même but : un aperçu concret dès l'arrivée sur le tableau
        // de bord, plutôt qu'un écran vide. Collection vide (jamais de
        // requête) sans le droit "documents" -> "Voir".
        $documentsRecents = $peutVoirDocuments
            ? Document::query()
                ->with(['typeDocument', 'sousTypeDocument', 'uploadePar'])
                ->whereNotIn('type_document_id', TypesEcranDedie::ids())
                ->latest('created_at')
                ->limit(8)
                ->get()
            : collect();

        return view('gestion.dashboard', [
            'cartes' => $cartes,
            'derniereSauvegarde' => $derniereSauvegarde,
            'documentsRecents' => $documentsRecents,
            'graphiques' => $peutVoirDocuments ? $this->graphiques($typeDiplomeAttestationIds) : [],
            'monCompte' => $this->monCompte(),
            'calendrier' => $this->calendrier(),
        ]);
    }

    /**
     * "Mon compte" sur le tableau de bord — demande explicite du client : que
     * la personne connectée puisse voir directement son nom, son rôle et ses
     * droits d'accès réels, sans avoir à aller demander à l'administrateur
     * "qu'est-ce que je peux faire ?". Lecture seule ici (la modification du
     * nom/email/mot de passe reste sur Mon profil, voir ProfilController) —
     * ce bloc ne fait qu'afficher.
     *
     * Le libellé du rôle et la matrice de droits viennent directement de
     * App\Models\Role (plutôt que du tableau statique User::libellesRoles(),
     * utilisé seulement pour le badge de la barre de navigation) : ainsi un
     * rôle personnalisé créé depuis Rôles & permissions, avec un libellé que
     * le client a choisi lui-même, s'affiche correctement ici aussi.
     *
     * @return array{nom: string, email: string, roleLibelle: string, estAdmin: bool, permissions: array<string, array<string, bool>>, derniereConnexionAt: ?\Illuminate\Support\Carbon, derniereConnexionIp: ?string}
     */
    protected function monCompte(): array
    {
        $utilisateur = Auth::user();
        $role = Role::query()->where('slug', $utilisateur->role)->first();

        return [
            'nom' => $utilisateur->name,
            'email' => $utilisateur->email,
            'roleLibelle' => $role?->name ?? (User::libellesRoles()[$utilisateur->role] ?? $utilisateur->role),
            // Même garde-fou que App\Support\ResourceAccess::can() : "admin"
            // a toujours tous les droits, même sans ligne Role correspondante
            // (ou une ligne mal configurée) — pas de table de permissions à
            // afficher dans ce cas, juste la mention "accès complet".
            'estAdmin' => $utilisateur->role === User::ROLE_ADMIN,
            'permissions' => $role?->permissions ?? [],
            'derniereConnexionAt' => $utilisateur->derniere_connexion_at,
            'derniereConnexionIp' => $utilisateur->derniere_connexion_ip,
        ];
    }

    /**
     * Calendrier du mois en cours — demande explicite du client, volontairement
     * "simple, sans évènements" (pas de lien avec les réunions/soutenances/
     * documents de l'application, juste un repère de dates). Calculé ici en
     * PHP avec Carbon plutôt que côté client en JavaScript : aucune
     * dépendance supplémentaire à charger, et le mois affiché respecte déjà
     * le fuseau horaire/la locale (français, voir config/app.php) du serveur.
     *
     * Semaine démarrant le lundi (convention française) : `dayOfWeekIso`
     * vaut 1 pour lundi, 7 pour dimanche, d'où le "- 1" pour le nombre de
     * cases vides à insérer avant le 1er du mois.
     *
     * @return array{libelleMois: string, semaines: array<int, array<int, array{numero: int, estAujourdhui: bool}|null>>}
     */
    protected function calendrier(): array
    {
        $aujourdhui = now();
        $premierJour = $aujourdhui->copy()->startOfMonth();

        $jours = array_fill(0, $premierJour->dayOfWeekIso - 1, null);

        for ($jour = 1; $jour <= $aujourdhui->daysInMonth; $jour++) {
            $jours[] = [
                'numero' => $jour,
                'estAujourdhui' => $jour === $aujourdhui->day,
            ];
        }

        // Toujours un multiple de 7 cases, pour que la dernière semaine du
        // mois garde une grille complète (cases vides en fin de tableau)
        // plutôt qu'une ligne tronquée.
        while (count($jours) % 7 !== 0) {
            $jours[] = null;
        }

        return [
            'libelleMois' => ucfirst($aujourdhui->translatedFormat('F Y')),
            'semaines' => array_chunk($jours, 7),
        ];
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
            ->whereNotIn('type_document_id', TypesEcranDedie::ids())
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

    /**
     * Exclut purement et simplement les types à écran dédié (voir
     * App\Support\TypesEcranDedie) : une ligne de moins à expliquer (son
     * compte serait de toute façon invisible ailleurs sur ce tableau de
     * bord) plutôt qu'un filtre sur chaque document compté.
     *
     * @return array{labels: array<int, string>, donnees: array<int, int>}
     */
    protected function graphiqueDocumentsParType(): array
    {
        $types = TypeDocument::query()
            ->whereNotIn('code', TypesEcranDedie::CODES)
            ->withCount('documents')
            ->orderBy('ordre')
            ->get();

        return [
            'labels' => $types->pluck('libelle')->all(),
            'donnees' => $types->pluck('documents_count')->all(),
        ];
    }

    /** @return array{labels: array<int, string>, donnees: array<int, int>} */
    protected function graphiqueDocumentsParFiliere(): array
    {
        // `withCount` contraint (pas un simple ->withCount('documents')) :
        // une filière peut tout à fait avoir des documents des DEUX sortes
        // (généraux et à écran dédié), contrairement à un type de document
        // ci-dessus qui est entièrement l'un ou l'autre — voir
        // App\Support\TypesEcranDedie.
        $filieres = Filiere::query()
            ->withCount(['documents' => fn ($q) => $q->whereNotIn('type_document_id', TypesEcranDedie::ids())])
            ->orderBy('ordre')
            ->get();

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
