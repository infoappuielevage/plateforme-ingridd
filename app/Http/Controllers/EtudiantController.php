<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ListeTriable;
use App\Http\Controllers\Concerns\VerifieVerrouillage;
use App\Models\AnneeAcademique;
use App\Models\Document;
use App\Models\Etudiant;
use App\Models\EtudiantFichier;
use App\Models\EtudiantInscription;
use App\Models\Filiere;
use App\Models\Niveau;
use App\Models\Parametre;
use App\Models\TypeDocument;
use App\Models\TypePieceEtudiant;
use App\Support\FichiersExcel;
use App\Support\ResourceAccess;
use App\Support\TypesFichiers;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Gestion du dossier étudiant — structure dédiée (demande explicite du
 * client, "travaillons sur le dossier étudiant"), MÊME PRINCIPE que
 * ReunionController/PvSoutenanceController avant elle : un vrai
 * enregistrement dédié, et chaque fichier réellement déposé reste un vrai
 * App\Models\Document rattaché à une "case" (voir App\Models\EtudiantFichier).
 *
 * Particularité propre à cet écran (voir le docblock de la migration
 * 2026_10_08_000002_create_etudiant_inscriptions_table.php) : l'identité de
 * l'étudiant (matricule, nom, prénoms, voir valider()) est stable, mais son
 * niveau/sa filière changent chaque année académique — gérés séparément via
 * un historique d'inscriptions (ajouterInscription()/modifierInscription()/
 * supprimerInscription()), pas des champs figés sur l'étudiant lui-même.
 *
 * Les pièces du dossier (uploaderFichier()/supprimerFichier()) suivent
 * désormais le MÊME PRINCIPE que les cases d'une session de délibération
 * (voir le docblock de SessionDeliberationController) : une liste FIXE de
 * cases, pilotée par le référentiel App\Models\TypePieceEtudiant (voir
 * Référentiels > Types de pièces, App\Http\Controllers\TypePieceEtudiantController),
 * pré-créées vides (creerCasesIdentite()/creerCasesInscription() ci-dessous)
 * plutôt qu'une liste libre où chaque ajout tapait un libellé à la main —
 * demande explicite du client : "comme pour les PV de délibération avec la
 * possibilité d'ajouter d'autre liste".
 *
 * Deux familles de cases selon App\Models\TypePieceEtudiant::$recurrente :
 * une pièce d'IDENTITÉ (CNIB...) est rattachée directement à l'étudiant
 * (une case pour tout son parcours), une pièce liée à une ANNÉE ACADÉMIQUE
 * (relevé de notes...) est rattachée à une App\Models\EtudiantInscription
 * précise (une case par année) — "il faudrait que l'on puisse ranger les
 * fichiers en fonction du niveau d'étude ou de l'année académique".
 */
class EtudiantController extends Controller
{
    use ListeTriable, VerifieVerrouillage;

    public function __construct()
    {
        abort_unless(ResourceAccess::can('etudiants', 'view'), 403);
    }

    public function index(Request $request): View
    {
        $corbeille = $request->boolean('corbeille');

        $etudiants = $this->requeteListe($request, $corbeille)->paginate(8)->withQueryString();

        // [clé, direction] résolus SANS requête (voir ListeTriable) pour que
        // la vue marque la colonne active dans l'en-tête.
        [$triActuel, $directionActuelle] = $this->resoudreTri($request, $this->colonnesTriables(), 'nom', 'asc');

        return view('gestion.etudiants.index', [
            'etudiants' => $etudiants,
            'corbeille' => $corbeille,
            // Ignore les filtres actifs — "Vider la corbeille" agit toujours
            // sur TOUS les dossiers corbeillés, jamais seulement ceux affichés
            // à l'écran (voir viderCorbeille()).
            'corbeillePeutEtreVidee' => $corbeille && Etudiant::onlyTrashed()->exists(),
            'anneesAcademiques' => AnneeAcademique::query()->orderByDesc('libelle')->get(),
            'niveaux' => Niveau::query()->where('actif', true)->orderBy('ordre')->get(),
            'filieres' => Filiere::query()->where('actif', true)->orderBy('ordre')->get(),
            'triActuel' => $triActuel,
            'directionActuelle' => $directionActuelle,
        ]);
    }

    /**
     * Export Excel (.xlsx) de la liste — demandé par le client, respecte
     * EXACTEMENT les mêmes filtres que l'écran (voir requeteListe()
     * ci-dessous, partagée avec index()), simplement sans pagination : ce
     * qui est visible à l'écran est ce qui part dans le fichier.
     */
    public function export(Request $request): StreamedResponse
    {
        $corbeille = $request->boolean('corbeille');

        $etudiants = $this->requeteListe($request, $corbeille)->get();

        $lignes = $etudiants->map(function (Etudiant $etudiant) {
            $inscription = $etudiant->inscriptionActuelle();

            return [
                $etudiant->matricule,
                $etudiant->nom,
                $etudiant->prenoms,
                $inscription?->anneeAcademique?->libelle,
                $inscription?->niveau?->libelle,
                $inscription?->filiere?->libelle,
                $etudiant->fichiers->whereNotNull('document_id')->count(),
            ];
        });

        return FichiersExcel::exporter(
            'etudiants-'.now()->format('Y-m-d').'.xlsx',
            ['Matricule', 'Nom', 'Prénoms', 'Année académique (actuelle)', 'Niveau (actuel)', 'Filière (actuelle)', 'Pièces déposées'],
            $lignes
        );
    }

    /** Formulaire d'import — se contente d'afficher la page, voir import() pour le traitement. */
    public function formulaireImport(): View
    {
        abort_unless(ResourceAccess::can('etudiants', 'create'), 403);

        return view('gestion.etudiants.import');
    }

    /** Modèle .xlsx vide (une ligne d'exemple) à remplir avant import — MÊME ORDRE DE COLONNES que import() ci-dessous. */
    public function modeleImport(): StreamedResponse
    {
        abort_unless(ResourceAccess::can('etudiants', 'create'), 403);

        return FichiersExcel::exporter(
            'modele-import-etudiants.xlsx',
            ['Matricule', 'Nom', 'Prénoms', 'Année académique (ex. 2025-2026)', 'Niveau (code)', 'Filière (code)'],
            [['ETU-0001', 'Exemple', 'Prénom Exemple', '2025-2026', 'L1', 'INFO']]
        );
    }

    /**
     * Import groupé depuis un fichier Excel — demandé par le client ("Excel
     * import/export"), MÊME FORMAT que modeleImport() ci-dessus (6 colonnes
     * dans cet ordre précis : les lignes sont lues par position, pas par nom
     * d'en-tête, voir FichiersExcel::lireLignes()).
     *
     * Chaque ligne est traitée indépendamment (sa propre transaction) : une
     * ligne invalide est signalée et sautée, elle ne fait jamais échouer le
     * reste du fichier. Matricule déjà connu → identité mise à jour et
     * inscription de l'année ajoutée SI elle n'existe pas déjà (jamais une
     * deuxième inscription pour la même année, même règle que
     * ajouterInscription() ci-dessus) ; matricule inconnu → dossier ET
     * première inscription créés, exactement comme store().
     */
    public function import(Request $request): RedirectResponse
    {
        abort_unless(ResourceAccess::can('etudiants', 'create'), 403);

        $donnees = $request->validate([
            'fichier' => ['required', 'file', 'mimes:xlsx,xls'],
        ]);

        $lignes = FichiersExcel::lireLignes($donnees['fichier']->getRealPath());

        $crees = 0;
        $misAJour = 0;
        $erreurs = [];

        foreach ($lignes as $numeroLigne => $ligne) {
            [$matricule, $nom, $prenoms, $libelleAnnee, $codeNiveau, $codeFiliere] = array_pad($ligne, 6, null);

            $valide = Validator::make(
                compact('matricule', 'nom', 'prenoms', 'libelleAnnee', 'codeNiveau', 'codeFiliere'),
                [
                    'matricule' => ['required', 'string', 'max:50'],
                    'nom' => ['required', 'string', 'max:150'],
                    'prenoms' => ['required', 'string', 'max:150'],
                    'libelleAnnee' => ['required', 'string', Rule::exists('annees_academiques', 'libelle')],
                    'codeNiveau' => ['required', 'string', Rule::exists('niveaux', 'code')],
                    'codeFiliere' => ['required', 'string', Rule::exists('filieres', 'code')],
                ],
                [
                    'libelleAnnee.exists' => 'L\'année académique indiquée n\'existe pas dans le référentiel (Référentiels > Années académiques).',
                    'codeNiveau.exists' => 'Le code niveau indiqué n\'existe pas dans le référentiel (Référentiels > Niveaux).',
                    'codeFiliere.exists' => 'Le code filière indiqué n\'existe pas dans le référentiel (Référentiels > Filières).',
                ]
            );

            if ($valide->fails()) {
                $erreurs[] = "Ligne {$numeroLigne} : ".$valide->errors()->first();

                continue;
            }

            // Un matricule qui correspond à un dossier déjà dans la
            // corbeille n'est JAMAIS restauré silencieusement par un import
            // — la ligne est signalée pour que l'utilisateur restaure le
            // dossier lui-même en connaissance de cause si c'est bien ce
            // qu'il veut (voir aussi reglesIdentite() : le matricule reste
            // unique même pour un dossier corbeillé, un import qui en
            // recréerait un second échouerait sinon en base sans message
            // clair).
            if (Etudiant::onlyTrashed()->where('matricule', $matricule)->exists()) {
                $erreurs[] = "Ligne {$numeroLigne} : le matricule \"{$matricule}\" correspond à un dossier dans la corbeille — restaurez-le avant de l'importer.";

                continue;
            }

            $annee = AnneeAcademique::where('libelle', $libelleAnnee)->first();
            $niveau = Niveau::where('code', $codeNiveau)->first();
            $filiere = Filiere::where('code', $codeFiliere)->first();

            try {
                DB::transaction(function () use ($matricule, $nom, $prenoms, $annee, $niveau, $filiere, &$crees, &$misAJour) {
                    $etudiant = Etudiant::where('matricule', $matricule)->first();

                    if (! $etudiant) {
                        $etudiant = Etudiant::create([
                            'matricule' => $matricule,
                            'nom' => $nom,
                            'prenoms' => $prenoms,
                            'created_by' => Auth::id(),
                        ]);
                        $this->creerCasesIdentite($etudiant);
                        $crees++;
                    } else {
                        $etudiant->update(['nom' => $nom, 'prenoms' => $prenoms]);
                        $misAJour++;
                    }

                    $inscription = $etudiant->inscriptions()->where('annee_academique_id', $annee->id)->first();

                    if ($inscription) {
                        $inscription->update(['niveau_id' => $niveau->id, 'filiere_id' => $filiere->id]);
                    } else {
                        $inscription = $etudiant->inscriptions()->create([
                            'annee_academique_id' => $annee->id,
                            'niveau_id' => $niveau->id,
                            'filiere_id' => $filiere->id,
                        ]);
                        $this->creerCasesInscription($inscription);
                    }
                });
            } catch (\Throwable $exception) {
                report($exception);
                $erreurs[] = "Ligne {$numeroLigne} : erreur inattendue — voir les journaux du serveur.";
            }
        }

        return redirect()->route('gestion.etudiants.index')->with('resultatImport', [
            'crees' => $crees,
            'mis_a_jour' => $misAJour,
            'erreurs' => $erreurs,
        ]);
    }

    /** @return \Illuminate\Database\Eloquent\Builder<Etudiant> */
    protected function requeteListe(Request $request, bool $corbeille): \Illuminate\Database\Eloquent\Builder
    {
        return Etudiant::query()
            ->with(['inscriptions.anneeAcademique', 'inscriptions.niveau', 'inscriptions.filiere', 'fichiers'])
            ->when($corbeille, fn ($q) => $q->onlyTrashed())
            ->when($request->filled('annee_academique_id'), function ($q) use ($request) {
                $q->whereHas('inscriptions', fn ($q2) => $q2->where('annee_academique_id', $request->integer('annee_academique_id')));
            })
            // Niveau/filière restent scopés à l'année académique choisie
            // quand elle est renseignée (même inscription) ; sans année
            // précisée, le filtre porte sur N'IMPORTE QUELLE inscription de
            // l'étudiant (simplification volontaire : filtrer "niveau actuel
            // uniquement" demanderait de recalculer l'inscription la plus
            // récente en SQL pour chaque étudiant).
            ->when($request->filled('niveau_id'), function ($q) use ($request) {
                $q->whereHas('inscriptions', function ($q2) use ($request) {
                    $q2->where('niveau_id', $request->integer('niveau_id'))
                        ->when($request->filled('annee_academique_id'), fn ($q3) => $q3->where('annee_academique_id', $request->integer('annee_academique_id')));
                });
            })
            ->when($request->filled('filiere_id'), function ($q) use ($request) {
                $q->whereHas('inscriptions', function ($q2) use ($request) {
                    $q2->where('filiere_id', $request->integer('filiere_id'))
                        ->when($request->filled('annee_academique_id'), fn ($q3) => $q3->where('annee_academique_id', $request->integer('annee_academique_id')));
                });
            })
            ->when($request->filled('recherche'), function ($q) use ($request) {
                $terme = $request->string('recherche');
                $q->where(function ($q2) use ($terme) {
                    $q2->where('matricule', 'like', "%{$terme}%")
                        ->orWhere('nom', 'like', "%{$terme}%")
                        ->orWhere('prenoms', 'like', "%{$terme}%");
                });
            });

        $this->appliquerTri($query, $this->colonnesTriables(), $this->resoudreTri($request, $this->colonnesTriables(), 'nom', 'asc'));

        return $query;
    }

    /**
     * Colonnes triables de la liste (voir App\Http\Controllers\Concerns\ListeTriable)
     * — "Nom et prénoms" trie sur `nom` puis `prenoms` (même ordre que
     * l'ancien ->orderBy('nom')->orderBy('prenoms') qu'elle remplace).
     * "Pièces déposées" est un compteur calculé (withCount), pas une colonne
     * réelle. "Niveau actuel"/"Filière actuelle"/"Année académique" ne sont
     * PAS incluses : ce sont des valeurs dérivées de inscriptionActuelle()
     * (la dernière inscription de l'étudiant, par année académique la plus
     * récente), pas une colonne ni une relation simple — un tri SQL dessus
     * demanderait de recalculer "la plus récente inscription" pour chaque
     * étudiant via une sous-requête corrélée coûteuse, hors périmètre de
     * cette demande.
     *
     * @return array<string, string|\Closure>
     */
    protected function colonnesTriables(): array
    {
        return [
            'matricule' => 'matricule',
            'nom' => function ($query, string $direction) {
                $query->orderBy('nom', $direction)->orderBy('prenoms', $direction);
            },
            'pieces_deposees' => function ($query, string $direction) {
                $query->withCount(['fichiers as pieces_deposees_count' => fn ($q) => $q->whereNotNull('document_id')])
                    ->orderBy('pieces_deposees_count', $direction);
            },
        ];
    }

    public function create(): View
    {
        abort_unless(ResourceAccess::can('etudiants', 'create'), 403);

        return view('gestion.etudiants.form', [
            'etudiant' => null,
            'anneesAcademiques' => AnneeAcademique::query()->where('actif', true)->orderByDesc('libelle')->get(),
            'niveaux' => Niveau::query()->where('actif', true)->orderBy('ordre')->get(),
            'filieres' => Filiere::query()->where('actif', true)->orderBy('ordre')->get(),
        ]);
    }

    /**
     * Crée l'étudiant ET sa première inscription (année/niveau/filière) en
     * une seule fois — contrairement à App\Models\Reunion (où la case
     * "rapport" est créée VIDE), un étudiant sans aucune inscription
     * n'aurait ni niveau ni filière à afficher nulle part.
     */
    public function store(Request $request): RedirectResponse
    {
        abort_unless(ResourceAccess::can('etudiants', 'create'), 403);

        // Les deux groupes de champs (identité + première inscription) sont
        // validés EN UNE SEULE FOIS ici (contrairement à update()/
        // ajouterInscription(), qui n'ont besoin que d'un seul groupe) :
        // $request->validate() s'arrête à la première exception levée, donc
        // appeler validerIdentite() puis validerInscription() séparément
        // perdait les erreurs d'inscription dès que l'identité était elle
        // aussi invalide (ex. formulaire vide : seules les erreurs
        // matricule/nom/prénoms apparaissaient).
        $donnees = $request->validate(
            $this->reglesIdentite() + $this->reglesInscription(),
            $this->messagesInscription()
        );
        $donneesInscription = array_intersect_key($donnees, array_flip(['annee_academique_id', 'niveau_id', 'filiere_id']));
        $donnees = array_intersect_key($donnees, array_flip(['matricule', 'nom', 'prenoms']));
        $donnees['created_by'] = Auth::id();

        $etudiant = DB::transaction(function () use ($donnees, $donneesInscription) {
            $etudiant = Etudiant::create($donnees);
            $inscription = $etudiant->inscriptions()->create($donneesInscription);

            $this->creerCasesIdentite($etudiant);
            $this->creerCasesInscription($inscription);

            return $etudiant;
        });

        return redirect()
            ->route('gestion.etudiants.show', $etudiant)
            ->with('succes', 'Dossier étudiant créé. Vous pouvez maintenant y déposer des pièces et ajouter les inscriptions des années suivantes.');
    }

    public function show(Etudiant $etudiant): View
    {
        $etudiant->load([
            'inscriptions.anneeAcademique', 'inscriptions.niveau', 'inscriptions.filiere',
            'fichiers.document', 'fichiers.typePieceEtudiant',
        ]);

        $inscriptions = $etudiant->inscriptions->sortByDesc(fn (EtudiantInscription $i) => $i->anneeAcademique?->libelle);

        // Pièces d'identité (type non récurrent, rattachées directement à
        // l'étudiant) — triées par l'ordre du type (voir
        // App\Models\TypePieceEtudiant::$ordre), une pièce "historique" sans
        // type rattaché (ancienne liste libre, voir le docblock
        // d'App\Models\EtudiantFichier) passant en dernier.
        $piecesIdentite = $etudiant->fichiers
            ->whereNull('etudiant_inscription_id')
            ->sortBy(fn (EtudiantFichier $f) => $f->typePieceEtudiant?->ordre ?? PHP_INT_MAX);

        // Pièces récurrentes, regroupées par année académique (la plus
        // récente d'abord, même ordre que la liste des inscriptions
        // ci-dessus) puis triées par l'ordre du type au sein de chaque
        // année — demande explicite du client : "ranger les fichiers en
        // fonction du niveau d'étude ou de l'année académique".
        $piecesParAnnee = $inscriptions->mapWithKeys(fn (EtudiantInscription $i) => [
            $i->id => $etudiant->fichiers
                ->where('etudiant_inscription_id', $i->id)
                ->sortBy(fn (EtudiantFichier $f) => $f->typePieceEtudiant?->ordre ?? PHP_INT_MAX),
        ])->filter(fn ($pieces) => $pieces->isNotEmpty());

        $parametres = Parametre::actuel();

        return view('gestion.etudiants.show', [
            'etudiant' => $etudiant,
            'inscriptions' => $inscriptions,
            'piecesIdentite' => $piecesIdentite,
            'piecesParAnnee' => $piecesParAnnee,
            'anneesAcademiques' => AnneeAcademique::query()->where('actif', true)->orderByDesc('libelle')->get(),
            'niveaux' => Niveau::query()->where('actif', true)->orderBy('ordre')->get(),
            'filieres' => Filiere::query()->where('actif', true)->orderBy('ordre')->get(),
            'tailleMaxMo' => $parametres->taille_max_document_mo,
        ]);
    }

    public function edit(Etudiant $etudiant): View
    {
        abort_unless(ResourceAccess::can('etudiants', 'edit'), 403);

        return view('gestion.etudiants.form', [
            'etudiant' => $etudiant,
            'anneesAcademiques' => AnneeAcademique::query()->where('actif', true)->orderByDesc('libelle')->get(),
            'niveaux' => Niveau::query()->where('actif', true)->orderBy('ordre')->get(),
            'filieres' => Filiere::query()->where('actif', true)->orderBy('ordre')->get(),
        ]);
    }

    /** Ne modifie que l'identité (matricule/nom/prénoms) — les inscriptions se gèrent depuis show(). */
    public function update(Request $request, Etudiant $etudiant): RedirectResponse
    {
        abort_unless(ResourceAccess::can('etudiants', 'edit'), 403);

        $etudiant->update($this->validerIdentite($request, $etudiant));

        return redirect()
            ->route('gestion.etudiants.show', $etudiant)
            ->with('succes', 'Dossier étudiant modifié.');
    }

    public function destroy(Etudiant $etudiant): RedirectResponse
    {
        abort_unless(ResourceAccess::can('etudiants', 'delete'), 403);

        $etudiant->delete();
        $this->mettreFichiersALaCorbeille($etudiant);

        return redirect()->route('gestion.etudiants.index')->with('succes', 'Dossier étudiant mis à la corbeille.');
    }

    public function restore(int $etudiant): RedirectResponse
    {
        abort_unless(ResourceAccess::can('etudiants', 'delete'), 403);

        $etudiantTrouve = Etudiant::onlyTrashed()->findOrFail($etudiant);
        $etudiantTrouve->restore();
        $this->restaurerFichiers($etudiantTrouve);

        return back()->with('succes', 'Dossier étudiant restauré.');
    }

    /**
     * Cascade MISE À LA CORBEILLE — demande explicite du client ("cascade
     * incohérente" : voir le docblock équivalent de ReunionController pour
     * le détail du problème signalé). Même garde-fou (document déjà à la
     * corbeille ignoré).
     */
    protected function mettreFichiersALaCorbeille(Etudiant $etudiant): void
    {
        $etudiant->loadMissing('fichiers');

        foreach ($etudiant->fichiers as $fichier) {
            if (! $fichier->document_id) {
                continue;
            }

            $document = Document::query()->find($fichier->document_id);
            if ($document && ! $document->trashed()) {
                $document->delete();
            }
        }
    }

    /** Cascade RESTAURATION, symétrique à mettreFichiersALaCorbeille() ci-dessus. */
    protected function restaurerFichiers(Etudiant $etudiant): void
    {
        $etudiant->loadMissing('fichiers');

        foreach ($etudiant->fichiers as $fichier) {
            if ($fichier->document_id) {
                Document::onlyTrashed()->find($fichier->document_id)?->restore();
            }
        }
    }

    /**
     * Suppression définitive : supprime aussi (mise à la corbeille) chaque
     * Document encore attaché à une pièce de ce dossier — même raisonnement
     * que les autres écrans dédiés. Les lignes d'inscriptions partent avec
     * l'étudiant (cascadeOnDelete en base).
     */
    public function forceDestroy(int $etudiant): RedirectResponse
    {
        abort_unless(ResourceAccess::can('etudiants', 'delete_definitif'), 403);

        $etudiantTrouve = Etudiant::onlyTrashed()->with('fichiers')->findOrFail($etudiant);

        foreach ($etudiantTrouve->fichiers as $fichier) {
            if ($fichier->document_id) {
                Document::query()->find($fichier->document_id)?->delete();
            }
        }

        $etudiantTrouve->forceDelete();

        return redirect()->route('gestion.etudiants.index', ['corbeille' => 1])->with('succes', 'Dossier étudiant supprimé définitivement.');
    }

    /**
     * "Vider la corbeille" : supprime définitivement TOUS les dossiers
     * étudiant corbeillés en un clic — même principe que
     * DocumentController::viderCorbeille(). Porte sur TOUTE la corbeille,
     * jamais seulement la page affichée.
     */
    public function viderCorbeille(): RedirectResponse
    {
        abort_unless(ResourceAccess::can('etudiants', 'delete_definitif'), 403);

        $etudiants = Etudiant::onlyTrashed()->with('fichiers')->get();

        foreach ($etudiants as $etudiant) {
            foreach ($etudiant->fichiers as $fichier) {
                if ($fichier->document_id) {
                    Document::query()->find($fichier->document_id)?->delete();
                }
            }

            $etudiant->forceDelete();
        }

        return redirect()
            ->route('gestion.etudiants.index', ['corbeille' => 1])
            ->with('succes', $etudiants->count().' dossier(s) étudiant supprimé(s) définitivement.');
    }

    /**
     * Pendant de viderCorbeille() ci-dessus pour App\Console\Commands\
     * PurgerCorbeilles (purge automatique) : MÊME cascade, limitée aux
     * dossiers mis à la corbeille depuis plus de $jours jours. Appelée
     * depuis la console (aucun utilisateur authentifié) — sans
     * abort_unless ni redirection.
     *
     * @return array{supprimes: int, ignores: int}
     */
    public function purgerAnciennes(int $jours): array
    {
        $etudiants = Etudiant::onlyTrashed()->with('fichiers')->where('deleted_at', '<', now()->subDays($jours))->get();

        foreach ($etudiants as $etudiant) {
            foreach ($etudiant->fichiers as $fichier) {
                if ($fichier->document_id) {
                    Document::query()->find($fichier->document_id)?->delete();
                }
            }

            $etudiant->forceDelete();
        }

        return ['supprimes' => $etudiants->count(), 'ignores' => 0];
    }

    /**
     * Mise à la corbeille groupée : sélection multiple de dossiers étudiant
     * (cases à cocher, voir resources/views/gestion/etudiants/index.blade.php)
     * mis à la corbeille en une seule action — MÊME PRINCIPE que
     * DocumentController::mettreCorbeilleGroupee(), sur demande explicite du
     * client ("permettre de faire des sélections pour des actions sur les
     * tableaux différents").
     *
     * Chaque étudiant est traité individuellement (jamais une seule requête
     * SQL groupée), pour que les événements Eloquent (historique, voir
     * AuditLog) se déclenchent bien pour chacun.
     */
    public function mettreCorbeilleGroupee(Request $request): RedirectResponse
    {
        abort_unless(ResourceAccess::can('etudiants', 'delete'), 403);

        $donnees = $request->validate([
            'etudiants' => ['required', 'array', 'min:1'],
            'etudiants.*' => ['integer'],
        ]);

        $etudiants = Etudiant::query()->with('fichiers')->whereIn('id', $donnees['etudiants'])->get();

        foreach ($etudiants as $etudiant) {
            $etudiant->delete();
            $this->mettreFichiersALaCorbeille($etudiant);
        }

        return back()->with('succes', $etudiants->count().' dossier(s) étudiant mis à la corbeille.');
    }

    /** Restauration groupée — pendant de mettreCorbeilleGroupee() pour la corbeille. */
    public function restaurerGroupe(Request $request): RedirectResponse
    {
        abort_unless(ResourceAccess::can('etudiants', 'delete'), 403);

        $donnees = $request->validate([
            'etudiants' => ['required', 'array', 'min:1'],
            'etudiants.*' => ['integer'],
        ]);

        $etudiants = Etudiant::onlyTrashed()->with('fichiers')->whereIn('id', $donnees['etudiants'])->get();

        foreach ($etudiants as $etudiant) {
            $etudiant->restore();
            $this->restaurerFichiers($etudiant);
        }

        return back()->with('succes', $etudiants->count().' dossier(s) étudiant restauré(s).');
    }

    /**
     * Suppression définitive groupée — MÊME RAISONNEMENT que forceDestroy()
     * ci-dessus : chaque document encore attaché à une pièce du dossier est
     * mis à la corbeille AVANT de supprimer le dossier lui-même, pour ne
     * jamais laisser de Document orphelin.
     */
    public function forceDestroyGroupe(Request $request): RedirectResponse
    {
        abort_unless(ResourceAccess::can('etudiants', 'delete_definitif'), 403);

        $donnees = $request->validate([
            'etudiants' => ['required', 'array', 'min:1'],
            'etudiants.*' => ['integer'],
        ]);

        $etudiants = Etudiant::onlyTrashed()->with('fichiers')->whereIn('id', $donnees['etudiants'])->get();

        foreach ($etudiants as $etudiant) {
            foreach ($etudiant->fichiers as $fichier) {
                if ($fichier->document_id) {
                    Document::query()->find($fichier->document_id)?->delete();
                }
            }

            $etudiant->forceDelete();
        }

        return back()->with('succes', $etudiants->count().' dossier(s) étudiant supprimé(s) définitivement.');
    }

    /** Ajoute l'inscription d'une NOUVELLE année académique — une seule par année (voir le docblock de la migration). */
    public function ajouterInscription(Request $request, Etudiant $etudiant): RedirectResponse
    {
        abort_unless(ResourceAccess::can('etudiants', 'edit'), 403);

        $donnees = $this->validerInscription($request, $etudiant);

        $inscription = $etudiant->inscriptions()->create($donnees);

        $this->creerCasesInscription($inscription);

        return back()->with('succes', 'Inscription ajoutée.');
    }

    /** Corrige le niveau/la filière d'une inscription déjà existante — l'année académique ne se change pas ici (voir le docblock de la classe). */
    public function modifierInscription(Request $request, Etudiant $etudiant, EtudiantInscription $inscription): RedirectResponse
    {
        abort_unless(ResourceAccess::can('etudiants', 'edit'), 403);
        abort_if($inscription->etudiant_id !== $etudiant->id, 404);

        $donnees = $request->validate([
            'niveau_id' => ['required', Rule::exists('niveaux', 'id')],
            'filiere_id' => ['required', Rule::exists('filieres', 'id')],
        ]);

        $inscription->update($donnees);

        return back()->with('succes', 'Inscription modifiée.');
    }

    /**
     * Retire ENTIÈREMENT une inscription — ses cases récurrentes partent
     * avec elle (`cascadeOnDelete()` en base, voir la migration
     * 2026_10_09_000002_add_type_piece_et_inscription_to_etudiant_fichiers_table.php),
     * mais la suppression en cascade au niveau base ne déclenche AUCUN
     * événement Eloquent sur App\Models\EtudiantFichier : les documents
     * encore attachés à ces cases sont donc mis à la corbeille ICI, avant,
     * pour ne jamais laisser un Document orphelin (même raisonnement que
     * forceDestroy() ci-dessus).
     */
    public function supprimerInscription(Etudiant $etudiant, EtudiantInscription $inscription): RedirectResponse
    {
        abort_unless(ResourceAccess::can('etudiants', 'delete'), 403);
        abort_if($inscription->etudiant_id !== $etudiant->id, 404);

        foreach ($inscription->fichiers as $fichier) {
            if ($fichier->document_id) {
                Document::query()->find($fichier->document_id)?->delete();
            }
        }

        $inscription->delete();

        return back()->with('succes', 'Inscription retirée.');
    }

    /**
     * Dépose (ou remplace) le fichier d'une case déjà existante — MÊME
     * PRINCIPE que SessionDeliberationController::uploaderFichier() : la
     * case elle-même n'est jamais créée ici (voir creerCasesIdentite()/
     * creerCasesInscription() ci-dessous et
     * App\Models\TypePieceEtudiant::synchroniserCasesRetroactives()),
     * seul son `document_id` change.
     */
    public function uploaderFichier(Request $request, Etudiant $etudiant, EtudiantFichier $fichier): RedirectResponse
    {
        abort_unless(ResourceAccess::can('etudiants', 'edit'), 403);
        abort_if($fichier->etudiant_id !== $etudiant->id, 404);

        $reponse = $this->refuserSiVerrouille($fichier->document);
        if ($reponse) {
            return $reponse;
        }

        $document = $this->creerDocumentDepose($request, $etudiant, $fichier);

        $ancienDocumentId = $fichier->document_id;
        $fichier->update(['document_id' => $document->id]);

        if ($ancienDocumentId) {
            Document::query()->find($ancienDocumentId)?->delete();
        }

        return back()->with('succes', 'Fichier déposé.');
    }

    /** Vide une case (met son document à la corbeille) — la case elle-même reste disponible pour un nouveau dépôt, même principe que SessionDeliberationController::supprimerFichier(). */
    public function supprimerFichier(Etudiant $etudiant, EtudiantFichier $fichier): RedirectResponse
    {
        abort_unless(ResourceAccess::can('etudiants', 'delete'), 403);
        abort_if($fichier->etudiant_id !== $etudiant->id, 404);

        $reponse = $this->refuserSiVerrouille($fichier->document);
        if ($reponse) {
            return $reponse;
        }

        if ($fichier->document_id) {
            Document::query()->find($fichier->document_id)?->delete();
            $fichier->update(['document_id' => null]);
        }

        return back()->with('succes', 'Fichier retiré. La case reste disponible pour un nouveau dépôt.');
    }

    /**
     * Pré-crée, VIDES, les cases des types non récurrents (pièces
     * d'identité) à la création d'un étudiant — les types récurrents sont
     * gérés séparément par creerCasesInscription() ci-dessous, une par
     * inscription plutôt qu'une par étudiant.
     */
    protected function creerCasesIdentite(Etudiant $etudiant): void
    {
        $numero = (int) EtudiantFichier::query()->where('etudiant_id', $etudiant->id)->max('numero');

        TypePieceEtudiant::query()
            ->where('actif', true)
            ->where('recurrente', false)
            ->orderBy('ordre')
            ->get()
            ->each(function (TypePieceEtudiant $type) use ($etudiant, &$numero) {
                EtudiantFichier::create([
                    'etudiant_id' => $etudiant->id,
                    'numero' => ++$numero,
                    'type_piece_etudiant_id' => $type->id,
                    'etudiant_inscription_id' => null,
                    'document_id' => null,
                ]);
            });
    }

    /**
     * Pré-crée, VIDES, les cases des types récurrents pour CETTE inscription
     * précise (une par année académique, voir le docblock de la classe) — à
     * l'ajout d'une nouvelle inscription, qu'elle soit la première (voir
     * store()) ou une suivante (voir ajouterInscription()).
     */
    protected function creerCasesInscription(EtudiantInscription $inscription): void
    {
        $numero = (int) EtudiantFichier::query()->where('etudiant_id', $inscription->etudiant_id)->max('numero');

        TypePieceEtudiant::query()
            ->where('actif', true)
            ->where('recurrente', true)
            ->orderBy('ordre')
            ->get()
            ->each(function (TypePieceEtudiant $type) use ($inscription, &$numero) {
                EtudiantFichier::create([
                    'etudiant_id' => $inscription->etudiant_id,
                    'numero' => ++$numero,
                    'type_piece_etudiant_id' => $type->id,
                    'etudiant_inscription_id' => $inscription->id,
                    'document_id' => null,
                ]);
            });
    }

    /**
     * @return array<string, mixed>
     */
    protected function validerIdentite(Request $request, ?Etudiant $etudiant = null): array
    {
        return $request->validate($this->reglesIdentite($etudiant));
    }

    /**
     * Valide les champs d'une inscription (année/niveau/filière) — l'unicité
     * "une seule inscription par année" (voir le docblock de la migration)
     * n'est volontairement vérifiée QUE pour un étudiant déjà existant (lors
     * de l'ajout d'une inscription supplémentaire) : à la création, aucun id
     * d'étudiant n'existe encore pour interroger la table.
     *
     * @return array<string, mixed>
     */
    protected function validerInscription(Request $request, ?Etudiant $etudiant = null): array
    {
        return $request->validate($this->reglesInscription($etudiant), $this->messagesInscription());
    }

    /**
     * @return array<string, mixed>
     */
    protected function reglesIdentite(?Etudiant $etudiant = null): array
    {
        return [
            'matricule' => ['required', 'string', 'max:50', Rule::unique('etudiants', 'matricule')->ignore($etudiant?->id)],
            'nom' => ['required', 'string', 'max:150'],
            'prenoms' => ['required', 'string', 'max:150'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function reglesInscription(?Etudiant $etudiant = null): array
    {
        return [
            'annee_academique_id' => [
                'required',
                Rule::exists('annees_academiques', 'id'),
                Rule::when($etudiant, [Rule::unique('etudiant_inscriptions', 'annee_academique_id')->where('etudiant_id', $etudiant?->id)]),
            ],
            'niveau_id' => ['required', Rule::exists('niveaux', 'id')],
            'filiere_id' => ['required', Rule::exists('filieres', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messagesInscription(): array
    {
        return [
            'annee_academique_id.unique' => 'Cet étudiant a déjà une inscription pour cette année académique — modifiez-la plutôt que d\'en ajouter une seconde.',
        ];
    }

    /**
     * Dépose le fichier envoyé et crée le App\Models\Document derrière (voir
     * le docblock de la classe) — même principe que
     * SessionDeliberationController::uploaderFichier(). `filiere_id`/
     * `niveau_id`/`annee_academique_id` du document reprennent l'inscription
     * RATTACHÉE À CETTE CASE quand elle en a une (pièce récurrente, voir
     * EtudiantFichier::$etudiant_inscription_id), sinon l'inscription la plus
     * récente de l'étudiant (pièce d'identité) — purement informatif (simple
     * cohérence des métadonnées), sans aucune incidence sur le dossier
     * étudiant lui-même. Ne permet PLUS de retrouver le document depuis
     * "Tous les documents" (voir App\Support\TypesEcranDedie et le docblock
     * de DocumentController::index() : un document DOSSIER_ETUDIANT en est
     * désormais exclu, même pour un filtre filière/niveau — faille de
     * sécurité corrigée).
     */
    protected function creerDocumentDepose(Request $request, Etudiant $etudiant, EtudiantFichier $fichier): Document
    {
        // `type_document_id` est obligatoire (NOT NULL, restrictOnDelete) sur
        // App\Models\Document — un renommage/une suppression malencontreuse
        // du type DOSSIER_ETUDIANT (voir TypeDocumentSeeder) casserait sinon
        // ce dépôt avec une erreur SQL brute plutôt qu'un message clair.
        $type = $this->typeDocumentEtudiant();
        abort_if(! $type, 500, "Le type de document \"DOSSIER_ETUDIANT\" est introuvable — contactez l'administrateur.");

        $parametres = Parametre::actuel();
        $tailleMaxKo = $parametres->taille_max_document_mo * 1024;
        $mimesAutorises = TypesFichiers::mimesPour($parametres->types_fichiers_autorises);

        $donnees = $request->validate([
            'fichier' => [
                'required',
                'file',
                'max:'.$tailleMaxKo,
                Rule::when(! empty($mimesAutorises), ['mimetypes:'.implode(',', $mimesAutorises)]),
            ],
        ]);

        $fichierUploade = $donnees['fichier'];
        $chemin = $fichierUploade->store('uploads', 'documents');

        $inscription = $fichier->etudiant_inscription_id
            ? $fichier->etudiantInscription
            : $etudiant->inscriptionActuelle();

        return Document::create([
            'description' => $etudiant->nomComplet().' ('.$etudiant->matricule.') — '.$fichier->libelle(),
            'type_document_id' => $type->id,
            'filiere_id' => $inscription?->filiere_id,
            'niveau_id' => $inscription?->niveau_id,
            'annee_academique_id' => $inscription?->annee_academique_id,
            'chemin' => $chemin,
            'nom_original' => $fichierUploade->getClientOriginalName(),
            'extension' => pathinfo($fichierUploade->getClientOriginalName(), PATHINFO_EXTENSION),
            'taille' => $fichierUploade->getSize(),
            'uploaded_by' => Auth::id(),
        ]);
    }

    /** Type de document DOSSIER_ETUDIANT déjà existant (voir TypeDocumentSeeder) — jamais recréé ici. */
    protected function typeDocumentEtudiant(): ?TypeDocument
    {
        return TypeDocument::query()->where('code', 'DOSSIER_ETUDIANT')->first();
    }
}
