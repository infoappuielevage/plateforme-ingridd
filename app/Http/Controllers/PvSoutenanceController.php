<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ListeTriable;
use App\Http\Controllers\Concerns\VerifieVerrouillage;
use App\Models\ChampSupplementaire;
use App\Models\Document;
use App\Models\Filiere;
use App\Models\Parametre;
use App\Models\PvSoutenance;
use App\Models\PvSoutenanceFichier;
use App\Models\TypeDocument;
use App\Support\FichiersExcel;
use App\Support\ResourceAccess;
use App\Support\TypesFichiers;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Gestion des PV de soutenance — structure dédiée (demande explicite du
 * client, à partir d'un exemple de registre papier), en remplacement du
 * simple "un document = un fichier" utilisé jusqu'ici pour le type
 * PV_SOUTENANCE. Voir le docblock de la migration
 * 2026_10_04_000001_create_pv_soutenances_table.php pour le contexte complet.
 *
 * Contrairement à SessionDeliberationController/SessionReleveController (un
 * enregistrement = une filière entière pour une session), un enregistrement
 * ici concerne UN SEUL étudiant : pas de pré-check de combinaison en amont
 * (comme pour sessions_deliberation), les deux contraintes d'unicité
 * (numero_ordre_registre par niveau, numero_pv globalement) sont validées
 * directement via Rule::unique() dans valider() ci-dessous.
 *
 * Chaque fichier réellement déposé (diplôme, image du registre, champ
 * supplémentaire) reste un vrai App\Models\Document, rattaché à une "case"
 * (App\Models\PvSoutenanceFichier) — même principe que pour le PV de
 * délibération/le Relevé de notes.
 *
 * Import Excel groupé (formulaireImport()/modeleImport()/import()) — demande
 * explicite du client pour saisir d'un coup toute la liste d'une session de
 * soutenances plutôt qu'un par un via create()/store(), les fichiers restant
 * ensuite à déposer case par case comme d'habitude. Voir le docblock
 * d'import() pour le détail (notamment "N° PV" comme clé de création/mise à
 * jour, MÊME PRINCIPE que EtudiantController::import() avec le matricule).
 */
class PvSoutenanceController extends Controller
{
    use ListeTriable, VerifieVerrouillage;

    /** Jamais SVG (format texte pouvant contenir du JavaScript) — même liste et même raisonnement que ProfilController::TYPES_AVATAR_ACCEPTES. */
    protected const TYPES_PHOTO_ACCEPTES = ['image/png', 'image/jpeg', 'image/webp'];

    public function __construct()
    {
        abort_unless(ResourceAccess::can('pv_soutenances', 'view'), 403);
    }

    public function index(Request $request): View
    {
        $corbeille = $request->boolean('corbeille');

        $pvSoutenances = $this->requeteListe($request, $corbeille)->paginate(8)->withQueryString();

        // [clé, direction] résolus SANS requête (voir ListeTriable) pour que
        // la vue marque la colonne active dans l'en-tête — même principe que
        // niveauActif ci-dessous.
        [$triActuel, $directionActuelle] = $this->resoudreTri($request, $this->colonnesTriables(), 'date_soutenance', 'desc');

        return view('gestion.pv-soutenances.index', [
            'pvSoutenances' => $pvSoutenances,
            'corbeille' => $corbeille,
            'filieres' => Filiere::query()->orderBy('ordre')->get(),
            'onglets' => ['tous' => 'Tous'] + PvSoutenance::NIVEAUX,
            'niveauActif' => $this->ongletNiveauValide($request),
            'triActuel' => $triActuel,
            'directionActuelle' => $directionActuelle,
        ]);
    }

    /**
     * Export Excel (.xlsx) de la liste — demandé par le client, respecte
     * EXACTEMENT les mêmes filtres que l'écran (voir requeteListe()
     * ci-dessous, partagée avec index()), simplement sans pagination.
     */
    public function export(Request $request): StreamedResponse
    {
        $corbeille = $request->boolean('corbeille');
        $pvSoutenances = $this->requeteListe($request, $corbeille)->get();

        $lignes = $pvSoutenances->map(fn (PvSoutenance $pv) => [
            $pv->numero_pv,
            $pv->nom,
            $pv->prenoms,
            $pv->niveauLibelle(),
            $pv->filiere?->libelle,
            $pv->date_soutenance?->format('d/m/Y'),
            $pv->decisionLibelle(),
            $pv->mention ? $pv->mentionLibelle() : null,
            $pv->estDiplomeRetire() ? 'Retiré le '.$pv->diplome_date_retrait->format('d/m/Y') : 'Non retiré',
        ]);

        return FichiersExcel::exporter(
            'pv-soutenances-'.now()->format('Y-m-d').'.xlsx',
            ['N° PV', 'Nom', 'Prénoms', 'Niveau', 'Filière', 'Date de soutenance', 'Décision', 'Mention', 'Diplôme'],
            $lignes
        );
    }

    /**
     * Formulaire d'import — se contente d'afficher la page, voir import()
     * pour le traitement. La liste des filières est transmise pour le
     * rappel de leurs codes affiché sur la page (demande explicite du
     * client, "importer la liste des étudiants avec toutes les
     * informations") : contrairement à "Niveau"/"Décision"/"Mention" (des
     * constantes PHP fixes, rappelées directement dans le modèle et sur
     * cette page), les codes filière viennent d'un référentiel que
     * l'utilisateur ne connaît pas forcément par cœur.
     */
    public function formulaireImport(): View
    {
        abort_unless(ResourceAccess::can('pv_soutenances', 'create'), 403);

        return view('gestion.pv-soutenances.import', [
            'filieres' => Filiere::query()->where('actif', true)->orderBy('ordre')->get(),
        ]);
    }

    /** Modèle .xlsx vide (une ligne d'exemple) à remplir avant import — MÊME ORDRE DE COLONNES que import() ci-dessous. */
    public function modeleImport(): StreamedResponse
    {
        abort_unless(ResourceAccess::can('pv_soutenances', 'create'), 403);

        return FichiersExcel::exporter(
            'modele-import-pv-soutenances.xlsx',
            [
                'Niveau (code : '.implode(', ', array_keys(PvSoutenance::NIVEAUX)).')',
                'Date de soutenance (jj/mm/aaaa)',
                "N° d'ordre au registre",
                'Nom',
                'Prénoms',
                'Date de naissance (jj/mm/aaaa)',
                'Lieu de naissance',
                'N° PV',
                'Filière (code)',
                'Thème',
                'Décision du jury (code : '.implode(', ', array_keys(PvSoutenance::DECISIONS)).')',
                'Moyenne (/20)',
                'Mention (code : '.implode(', ', array_keys(PvSoutenance::MENTIONS)).')',
                'Président du jury',
                'Directeur de mémoire',
                'Rapporteur',
                'Maître de stage',
            ],
            [[
                'licence', '15/06/2026', '42', 'Ouédraogo', 'Aïcha', '01/01/2000', 'Ouagadougou',
                'PV-2026-001', 'INFO', 'Étude comparative des systèmes de gestion des archives',
                'admis', '14.5', 'bien', 'Pr. Jean Kaboré', '', '', '',
            ]]
        );
    }

    /**
     * Import groupé depuis un fichier Excel — demande explicite du client
     * ("il faudrait également qu'on puisse importer la liste des étudiants
     * avec toutes les informations à travers un fichier excel, après on
     * pourrait importer les fichiers comme ce qui est déjà en place") :
     * remplace la saisie un par un via create()/store() pour renseigner
     * d'un coup tous les PV d'une session de soutenances, chaque fichier
     * (diplôme, image du registre, champs supplémentaires) restant ensuite
     * à déposer case par case EXACTEMENT comme avant, voir uploaderFichier()
     * ci-dessous (inchangé). La photo de l'étudiant n'est volontairement PAS
     * gérée ici (c'est un fichier image, pas une colonne Excel) : elle reste
     * à ajouter depuis la fiche après coup si besoin.
     *
     * MÊME FORMAT que modeleImport() ci-dessus (17 colonnes dans cet ordre
     * précis, lues par position — voir FichiersExcel::lireLignes()). Chaque
     * ligne est traitée indépendamment (sa propre transaction) : une ligne
     * invalide est signalée et sautée, elle ne fait jamais échouer le reste
     * du fichier.
     *
     * "N° PV" sert de clé naturelle pour décider création/mise à jour (un
     * même numéro de PV désigne toujours le même enregistrement) — réimporter
     * le même fichier après correction d'une erreur met donc simplement à
     * jour les PV déjà créés, sans jamais les dupliquer. Même précaution que
     * EtudiantController::import() pour un numéro qui correspondrait à un PV
     * dans la corbeille : jamais restauré silencieusement, la ligne est
     * signalée pour que l'utilisateur restaure lui-même en connaissance de
     * cause. Un PV mis à jour par cet import garde ses cases/fichiers déjà
     * déposés (aucune case n'est recréée ni supprimée), exactement comme un
     * passage par update() ci-dessus.
     */
    public function import(Request $request): RedirectResponse
    {
        abort_unless(ResourceAccess::can('pv_soutenances', 'create'), 403);

        $donneesRequete = $request->validate([
            'fichier' => ['required', 'file', 'mimes:xlsx,xls'],
        ]);

        $lignes = FichiersExcel::lireLignes($donneesRequete['fichier']->getRealPath());

        $crees = 0;
        $misAJour = 0;
        $erreurs = [];

        foreach ($lignes as $numeroLigne => $ligne) {
            [
                $codeNiveau, $dateSoutenanceBrute, $numeroOrdreRegistre, $nom, $prenoms,
                $dateNaissanceBrute, $lieuNaissance, $numeroPv, $codeFiliere, $theme,
                $codeDecision, $moyenneBrute, $codeMention, $presidentJury, $directeurMemoire,
                $rapporteur, $maitreStage,
            ] = array_pad($ligne, 17, null);

            // Une cellule à l'apparence purement numérique (ex. un numéro
            // d'ordre au registre "42") revient d'Excel comme un entier/
            // flottant PHP plutôt qu'une chaîne (voir PhpOffice\PhpSpreadsheet\
            // Cell::setValue()) — normalisée ici pour chaque champ texte,
            // sans quoi la règle de validation "string" ci-dessous
            // échouerait à tort sur une valeur par ailleurs parfaitement
            // valide. Ramène aussi une cellule vide à null (plutôt que ''),
            // pour que les règles "nullable" des champs optionnels
            // fonctionnent comme attendu.
            $codeNiveau = $this->normaliserTexteImport($codeNiveau);
            $numeroOrdreRegistre = $this->normaliserTexteImport($numeroOrdreRegistre);
            $nom = $this->normaliserTexteImport($nom);
            $prenoms = $this->normaliserTexteImport($prenoms);
            $lieuNaissance = $this->normaliserTexteImport($lieuNaissance);
            $numeroPv = $this->normaliserTexteImport($numeroPv);
            $codeFiliere = $this->normaliserTexteImport($codeFiliere);
            $theme = $this->normaliserTexteImport($theme);
            $codeDecision = $this->normaliserTexteImport($codeDecision);
            $codeMention = $this->normaliserTexteImport($codeMention);
            $presidentJury = $this->normaliserTexteImport($presidentJury);
            $directeurMemoire = $this->normaliserTexteImport($directeurMemoire);
            $rapporteur = $this->normaliserTexteImport($rapporteur);
            $maitreStage = $this->normaliserTexteImport($maitreStage);

            $dateSoutenance = $this->convertirDateImport($dateSoutenanceBrute);
            $dateNaissance = $this->convertirDateImport($dateNaissanceBrute);
            // Un classeur Excel réglé en français utilise la virgule comme
            // séparateur décimal (ex. "14,5") — convertie ici avant
            // validation, sans quoi une moyenne par ailleurs correcte serait
            // signalée à tort comme invalide.
            $moyenne = is_string($moyenneBrute) ? str_replace(',', '.', trim($moyenneBrute)) : $moyenneBrute;

            // "N° PV" dans la corbeille : jamais restauré silencieusement
            // (même précaution que EtudiantController::import() pour le
            // matricule).
            if ($numeroPv && PvSoutenance::onlyTrashed()->where('numero_pv', $numeroPv)->exists()) {
                $erreurs[] = "Ligne {$numeroLigne} : le N° PV \"{$numeroPv}\" correspond à un PV dans la corbeille — restaurez-le avant de l'importer.";

                continue;
            }

            $pvExistant = $numeroPv ? PvSoutenance::query()->where('numero_pv', $numeroPv)->first() : null;

            $valide = Validator::make(
                compact(
                    'codeNiveau', 'dateSoutenance', 'numeroOrdreRegistre', 'nom', 'prenoms',
                    'dateNaissance', 'lieuNaissance', 'numeroPv', 'codeFiliere', 'theme',
                    'codeDecision', 'moyenne', 'codeMention', 'presidentJury', 'directeurMemoire',
                    'rapporteur', 'maitreStage'
                ),
                [
                    'codeNiveau' => ['required', Rule::in(array_keys(PvSoutenance::NIVEAUX))],
                    'dateSoutenance' => ['required', 'date'],
                    'numeroOrdreRegistre' => [
                        'required', 'string', 'max:50',
                        Rule::unique('pv_soutenances', 'numero_ordre_registre')
                            ->where(fn ($q) => $q->where('niveau', $codeNiveau))
                            ->ignore($pvExistant?->id),
                    ],
                    'nom' => ['required', 'string', 'max:150'],
                    'prenoms' => ['required', 'string', 'max:150'],
                    'dateNaissance' => ['required', 'date'],
                    'lieuNaissance' => ['required', 'string', 'max:150'],
                    'numeroPv' => ['required', 'string', 'max:50'],
                    'codeFiliere' => ['required', 'string', Rule::exists('filieres', 'code')],
                    'theme' => ['required', 'string', 'max:2000'],
                    'codeDecision' => ['required', Rule::in(array_keys(PvSoutenance::DECISIONS))],
                    'moyenne' => ['required', 'numeric', 'between:0,20'],
                    'codeMention' => ['required', Rule::in(array_keys(PvSoutenance::MENTIONS))],
                    'presidentJury' => ['required', 'string', 'max:150'],
                    'directeurMemoire' => ['nullable', 'string', 'max:150'],
                    'rapporteur' => ['nullable', 'string', 'max:150'],
                    'maitreStage' => ['nullable', 'string', 'max:150'],
                ],
                [
                    'codeNiveau.in' => 'Niveau inconnu — valeurs acceptées : '.implode(', ', array_keys(PvSoutenance::NIVEAUX)).'.',
                    'dateSoutenance.date' => 'Date de soutenance invalide — utilisez le format jj/mm/aaaa.',
                    'dateNaissance.date' => 'Date de naissance invalide — utilisez le format jj/mm/aaaa.',
                    'numeroOrdreRegistre.unique' => 'Ce numéro d\'ordre au registre est déjà utilisé pour ce niveau.',
                    'codeFiliere.exists' => 'Le code filière indiqué n\'existe pas dans le référentiel (Référentiels > Filières).',
                    'codeDecision.in' => 'Décision du jury inconnue — valeurs acceptées : '.implode(', ', array_keys(PvSoutenance::DECISIONS)).'.',
                    'codeMention.in' => 'Mention inconnue — valeurs acceptées : '.implode(', ', array_keys(PvSoutenance::MENTIONS)).'.',
                ]
            );

            if ($valide->fails()) {
                $erreurs[] = "Ligne {$numeroLigne} : ".$valide->errors()->first();

                continue;
            }

            $filiere = Filiere::where('code', $codeFiliere)->first();

            $donnees = [
                'niveau' => $codeNiveau,
                'date_soutenance' => $dateSoutenance,
                'numero_ordre_registre' => $numeroOrdreRegistre,
                'nom' => $nom,
                'prenoms' => $prenoms,
                'date_naissance' => $dateNaissance,
                'lieu_naissance' => $lieuNaissance,
                'numero_pv' => $numeroPv,
                'filiere_id' => $filiere->id,
                'theme' => $theme,
                'decision_jury' => $codeDecision,
                'moyenne' => $moyenne,
                'mention' => $codeMention,
                'president_jury' => $presidentJury,
                'directeur_memoire' => $directeurMemoire ?: null,
                'rapporteur' => $rapporteur ?: null,
                'maitre_stage' => $maitreStage ?: null,
            ];

            try {
                DB::transaction(function () use ($donnees, $pvExistant, &$crees, &$misAJour) {
                    if ($pvExistant) {
                        $pvExistant->update($donnees);
                        $misAJour++;
                    } else {
                        $donnees['created_by'] = Auth::id();
                        $nouveauPv = PvSoutenance::create($donnees);
                        $this->creerCasesVides($nouveauPv);
                        $crees++;
                    }
                });
            } catch (\Throwable $exception) {
                report($exception);
                $erreurs[] = "Ligne {$numeroLigne} : erreur inattendue — voir les journaux du serveur.";
            }
        }

        return redirect()->route('gestion.pv-soutenances.index')->with('resultatImport', [
            'crees' => $crees,
            'mis_a_jour' => $misAJour,
            'erreurs' => $erreurs,
        ]);
    }

    /**
     * Normalise une valeur de cellule texte lue depuis Excel pour import() —
     * voir le commentaire à son point d'appel pour le détail (conversion
     * entier/flottant → chaîne, cellule vide → null).
     */
    protected function normaliserTexteImport(mixed $valeur): ?string
    {
        if ($valeur === null) {
            return null;
        }

        $valeur = trim((string) $valeur);

        return $valeur === '' ? null : $valeur;
    }

    /**
     * Convertit une date saisie en texte (format jj/mm/aaaa attendu par le
     * modèle d'import, voir modeleImport() ci-dessus) en chaîne Y-m-d
     * exploitable par la règle de validation "date" — quelques formats de
     * repli (Y-m-d, d-m-Y, d.m.Y) acceptés au cas où Excel aurait reformaté
     * la cellule différemment. La vérification par aller-retour
     * (`$date->format($format) === $valeur`) évite l'interprétation
     * "souple" de DateTime (ex. "32/13/2026" ne doit jamais être
     * silencieusement transformé en une autre date valide). Retourne null si
     * la valeur est vide ou ne correspond à aucun format connu — la règle de
     * validation "date" se charge ensuite de signaler l'erreur à l'utilisateur.
     */
    protected function convertirDateImport(mixed $valeur): ?string
    {
        if ($valeur === null || $valeur === '') {
            return null;
        }

        $valeur = trim((string) $valeur);

        foreach (['d/m/Y', 'Y-m-d', 'd-m-Y', 'd.m.Y'] as $format) {
            $date = \DateTime::createFromFormat('!'.$format, $valeur);

            if ($date !== false && $date->format($format) === $valeur) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    /** @return \Illuminate\Database\Eloquent\Builder<PvSoutenance> */
    protected function requeteListe(Request $request, bool $corbeille): \Illuminate\Database\Eloquent\Builder
    {
        $niveau = $this->ongletNiveauValide($request);

        $query = PvSoutenance::query()
            ->with(['filiere', 'fichiers'])
            ->when($corbeille, fn ($q) => $q->onlyTrashed())
            // Onglet "niveau" (voir index()/resources/views/gestion/pv-soutenances/index.blade.php)
            // — remplace l'ancien filtre "Niveau" à liste déroulante, demande
            // explicite du client ("enlever le filtre Niveau et faire plutôt
            // trois tableau, BTS, LICENCE, MASTER"). 'tous' = aucun filtre.
            ->when($niveau !== 'tous', fn ($q) => $q->where('niveau', $niveau))
            ->when($request->filled('filiere_id'), fn ($q) => $q->where('filiere_id', $request->integer('filiere_id')))
            // Remplace l'ancien écran Documents filtré par type (ex.
            // /gestion/documents?type_document_id=...), destiné à disparaître
            // au profit de cette liste — demande explicite du client : ce
            // filtre permet de retrouver en un clic les diplômes encore à
            // retirer, ou au contraire déjà remis.
            ->when($request->filled('diplome_retrait'), fn ($q) => $request->string('diplome_retrait')->toString() === 'retire'
                ? $q->whereNotNull('diplome_date_retrait')
                : $q->whereNull('diplome_date_retrait'))
            // "N° PV" : champ de recherche DÉDIÉ, distinct de "Recherche"
            // (nom/prénoms) ci-dessous — demande explicite du client
            // ("qu'on puisse chercher en fonction du N° PV").
            ->when($request->filled('numero_pv'), fn ($q) => $q->where('numero_pv', 'like', '%'.$request->string('numero_pv').'%'))
            ->when($request->filled('recherche'), function ($q) use ($request) {
                $terme = $request->string('recherche');
                $q->where(function ($q) use ($terme) {
                    $q->where('nom', 'like', "%{$terme}%")
                        ->orWhere('prenoms', 'like', "%{$terme}%");
                });
            });

        $this->appliquerTri($query, $this->colonnesTriables(), $this->resoudreTri($request, $this->colonnesTriables(), 'date_soutenance', 'desc'));

        return $query;
    }

    /**
     * Colonnes triables de la liste (voir App\Http\Controllers\Concerns\ListeTriable)
     * — demande explicite du client ("ajoute la fonctionnalité de trier sur
     * les tableaux"). "Étudiant" trie sur le nom de famille (colonne `nom`) ;
     * "Filière" n'est pas une colonne de pv_soutenances mais une relation,
     * donc une sous-requête corrélée plutôt qu'un orderBy direct —
     * App\Models\Filiere::select('libelle')->whereColumn(...) évite une
     * jointure qui dupliquerait des lignes ou entrerait en conflit avec les
     * filtres déjà appliqués ci-dessus. "Documents déposés" est un compteur
     * calculé (withCount conditionnel), pas une colonne réelle, d'où la
     * Closure qui l'ajoute à la volée avant de trier dessus.
     *
     * @return array<string, string|\Closure>
     */
    protected function colonnesTriables(): array
    {
        return [
            'numero_pv' => 'numero_pv',
            'nom' => 'nom',
            'niveau' => 'niveau',
            'filiere' => fn ($query, string $direction) => $query->orderBy(
                Filiere::select('libelle')->whereColumn('filieres.id', 'pv_soutenances.filiere_id'),
                $direction
            ),
            'date_soutenance' => 'date_soutenance',
            'decision_jury' => 'decision_jury',
            'diplome_date_retrait' => 'diplome_date_retrait',
            'documents_deposes' => function ($query, string $direction) {
                $query->withCount(['fichiers as documents_deposes_count' => fn ($q) => $q->whereNotNull('document_id')])
                    ->orderBy('documents_deposes_count', $direction);
            },
        ];
    }

    /**
     * Onglet "niveau" valide pour la liste — 'tous' (aucun filtre) ou l'une
     * des valeurs de PvSoutenance::NIVEAUX. Un lien externe/favori obsolète
     * pointant vers une valeur inconnue retombe silencieusement sur 'tous'
     * plutôt qu'une erreur SQL — même principe que
     * App\Support\FamillesDocument::ongletValide() pour la liste Documents.
     */
    protected function ongletNiveauValide(Request $request): string
    {
        $valeur = $request->string('niveau')->toString();

        return array_key_exists($valeur, PvSoutenance::NIVEAUX) ? $valeur : 'tous';
    }

    public function create(): View
    {
        abort_unless(ResourceAccess::can('pv_soutenances', 'create'), 403);

        return view('gestion.pv-soutenances.form', [
            'pvSoutenance' => null,
            'filieres' => Filiere::query()->where('actif', true)->orderBy('ordre')->get(),
            'niveaux' => PvSoutenance::NIVEAUX,
            'decisions' => PvSoutenance::DECISIONS,
            'mentions' => PvSoutenance::MENTIONS,
            'tailleMaxPhotoMo' => Parametre::actuel()->taille_max_avatar_mo,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(ResourceAccess::can('pv_soutenances', 'create'), 403);

        $donnees = $this->valider($request);
        $donnees['created_by'] = Auth::id();

        // `photo` n'est pas dans PvSoutenance::$fillable (voir son docblock) :
        // extraite ici plutôt que laissée dans $donnees, où create() en
        // mass-assignment l'ignorerait silencieusement de toute façon — mais
        // autant ne pas y laisser traîner l'objet UploadedFile.
        $fichierPhoto = $request->file('photo');
        unset($donnees['photo']);

        $pvSoutenance = DB::transaction(function () use ($donnees, $fichierPhoto) {
            $pvSoutenance = PvSoutenance::create($donnees);
            $this->creerCasesVides($pvSoutenance);

            if ($fichierPhoto) {
                $pvSoutenance->forceFill(['photo' => $fichierPhoto->store('pv-soutenances-photos', 'public')])->save();
            }

            return $pvSoutenance;
        });

        return redirect()
            ->route('gestion.pv-soutenances.show', $pvSoutenance)
            ->with('succes', 'PV de soutenance créé. Vous pouvez maintenant y déposer les documents.');
    }

    public function show(PvSoutenance $pvSoutenance): View
    {
        $pvSoutenance->load(['filiere', 'creePar', 'fichiers.document', 'fichiers.champSupplementaire']);

        $fixes = $pvSoutenance->fichiers
            ->whereIn('role', array_keys(PvSoutenanceFichier::ROLES_FIXES))
            ->sortBy(fn (PvSoutenanceFichier $f) => array_search($f->role, array_keys(PvSoutenanceFichier::ROLES_FIXES), true));

        // Champs supplémentaires ajoutés depuis l'administration (voir
        // App\Models\ChampSupplementaire) — triés par leur "ordre" plutôt que
        // par id, pour que l'administration garde la main sur l'affichage.
        $supplementaires = $pvSoutenance->fichiers
            ->where('role', PvSoutenanceFichier::ROLE_SUPPLEMENTAIRE)
            ->sortBy(fn (PvSoutenanceFichier $f) => $f->champSupplementaire?->ordre ?? 0);

        $parametres = Parametre::actuel();

        return view('gestion.pv-soutenances.show', [
            'pvSoutenance' => $pvSoutenance,
            'fixes' => $fixes,
            'supplementaires' => $supplementaires,
            'tailleMaxMo' => $parametres->taille_max_document_mo,
        ]);
    }

    public function edit(PvSoutenance $pvSoutenance): View
    {
        abort_unless(ResourceAccess::can('pv_soutenances', 'edit'), 403);

        return view('gestion.pv-soutenances.form', [
            'pvSoutenance' => $pvSoutenance,
            'filieres' => Filiere::query()->where('actif', true)->orderBy('ordre')->get(),
            'niveaux' => PvSoutenance::NIVEAUX,
            'decisions' => PvSoutenance::DECISIONS,
            'mentions' => PvSoutenance::MENTIONS,
            'tailleMaxPhotoMo' => Parametre::actuel()->taille_max_avatar_mo,
        ]);
    }

    /**
     * Contrairement à PV de délibération/Relevé de notes (description
     * uniquement modifiable après création) : ici, TOUS les champs restent
     * modifiables — ce sont des données factuelles propres à un étudiant
     * (nom, dates, moyenne, jury...), saisies à la main depuis un registre
     * papier, donc susceptibles de correction après coup. Les deux
     * contraintes d'unicité (numero_ordre_registre par niveau, numero_pv)
     * restent bien sûr revalidées ici, en ignorant l'enregistrement courant.
     */
    public function update(Request $request, PvSoutenance $pvSoutenance): RedirectResponse
    {
        abort_unless(ResourceAccess::can('pv_soutenances', 'edit'), 403);

        $donnees = $this->valider($request, $pvSoutenance);

        $fichierPhoto = $request->file('photo');
        unset($donnees['photo']);

        $pvSoutenance->update($donnees);

        // Remplace la photo seulement si un nouveau fichier a bien été
        // envoyé (laisser le champ vide garde la photo actuelle — voir le
        // form-text correspondant dans form.blade.php). L'ancienne n'est
        // supprimée qu'APRÈS avoir enregistré la nouvelle avec succès, même
        // ordre que ProfilController::update() pour `avatar`.
        if ($fichierPhoto) {
            $anciennePhoto = $pvSoutenance->photo;
            $pvSoutenance->forceFill(['photo' => $fichierPhoto->store('pv-soutenances-photos', 'public')])->save();

            if ($anciennePhoto) {
                Storage::disk('public')->delete($anciennePhoto);
            }
        }

        return redirect()
            ->route('gestion.pv-soutenances.show', $pvSoutenance)
            ->with('succes', 'PV de soutenance modifié.');
    }

    public function destroy(PvSoutenance $pvSoutenance): RedirectResponse
    {
        abort_unless(ResourceAccess::can('pv_soutenances', 'delete'), 403);

        $pvSoutenance->delete();

        return redirect()->route('gestion.pv-soutenances.index')->with('succes', 'PV de soutenance mis à la corbeille.');
    }

    public function restore(int $pvSoutenance): RedirectResponse
    {
        abort_unless(ResourceAccess::can('pv_soutenances', 'delete'), 403);

        PvSoutenance::onlyTrashed()->findOrFail($pvSoutenance)->restore();

        return back()->with('succes', 'PV de soutenance restauré.');
    }

    /**
     * Suppression définitive : supprime aussi (mise à la corbeille) chaque
     * Document encore attaché à une case de ce PV — même raisonnement que
     * SessionDeliberationController::forceDestroy().
     */
    public function forceDestroy(int $pvSoutenance): RedirectResponse
    {
        abort_unless(ResourceAccess::can('pv_soutenances', 'delete'), 403);

        $pv = PvSoutenance::onlyTrashed()->with('fichiers')->findOrFail($pvSoutenance);

        foreach ($pv->fichiers as $fichier) {
            if ($fichier->document_id) {
                Document::query()->find($fichier->document_id)?->delete();
            }
        }

        $pv->forceDelete();

        return redirect()->route('gestion.pv-soutenances.index', ['corbeille' => 1])->with('succes', 'PV de soutenance supprimé définitivement.');
    }

    /**
     * Mise à la corbeille groupée : sélection multiple de PV (cases à
     * cocher, voir resources/views/gestion/pv-soutenances/index.blade.php)
     * mis à la corbeille en une seule action — MÊME PRINCIPE que
     * DocumentController::mettreCorbeilleGroupee(), sur demande explicite du
     * client ("permettre de faire des sélections pour des actions sur les
     * tableaux différents").
     */
    public function mettreCorbeilleGroupee(Request $request): RedirectResponse
    {
        abort_unless(ResourceAccess::can('pv_soutenances', 'delete'), 403);

        $donnees = $request->validate([
            'pvSoutenances' => ['required', 'array', 'min:1'],
            'pvSoutenances.*' => ['integer'],
        ]);

        $pvSoutenances = PvSoutenance::query()->whereIn('id', $donnees['pvSoutenances'])->get();

        foreach ($pvSoutenances as $pvSoutenance) {
            $pvSoutenance->delete();
        }

        return back()->with('succes', $pvSoutenances->count().' PV de soutenance mis à la corbeille.');
    }

    /** Restauration groupée — pendant de mettreCorbeilleGroupee() pour la corbeille. */
    public function restaurerGroupe(Request $request): RedirectResponse
    {
        abort_unless(ResourceAccess::can('pv_soutenances', 'delete'), 403);

        $donnees = $request->validate([
            'pvSoutenances' => ['required', 'array', 'min:1'],
            'pvSoutenances.*' => ['integer'],
        ]);

        $pvSoutenances = PvSoutenance::onlyTrashed()->whereIn('id', $donnees['pvSoutenances'])->get();

        foreach ($pvSoutenances as $pvSoutenance) {
            $pvSoutenance->restore();
        }

        return back()->with('succes', $pvSoutenances->count().' PV de soutenance restauré(s).');
    }

    /**
     * Suppression définitive groupée — MÊME RAISONNEMENT que forceDestroy()
     * ci-dessus : chaque document encore attaché à une case est mis à la
     * corbeille AVANT de supprimer le PV lui-même.
     */
    public function forceDestroyGroupe(Request $request): RedirectResponse
    {
        abort_unless(ResourceAccess::can('pv_soutenances', 'delete'), 403);

        $donnees = $request->validate([
            'pvSoutenances' => ['required', 'array', 'min:1'],
            'pvSoutenances.*' => ['integer'],
        ]);

        $pvSoutenances = PvSoutenance::onlyTrashed()->with('fichiers')->whereIn('id', $donnees['pvSoutenances'])->get();

        foreach ($pvSoutenances as $pvSoutenance) {
            foreach ($pvSoutenance->fichiers as $fichier) {
                if ($fichier->document_id) {
                    Document::query()->find($fichier->document_id)?->delete();
                }
            }

            $pvSoutenance->forceDelete();
        }

        return back()->with('succes', $pvSoutenances->count().' PV de soutenance supprimé(s) définitivement.');
    }

    /**
     * Dépose (ou remplace) le fichier d'UNE case. Crée un vrai
     * App\Models\Document derrière (voir le docblock de la classe) — s'il y
     * en avait déjà un, l'ancien est mis à la corbeille (jamais supprimé
     * physiquement tout de suite, même règle que DocumentController::update()).
     */
    public function uploaderFichier(Request $request, PvSoutenance $pvSoutenance, PvSoutenanceFichier $fichier): RedirectResponse
    {
        abort_unless(ResourceAccess::can('pv_soutenances', 'edit'), 403);
        abort_if($fichier->pv_soutenance_id !== $pvSoutenance->id, 404);

        $reponse = $this->refuserSiVerrouille($fichier->document);
        if ($reponse) {
            return $reponse;
        }

        // `type_document_id` est obligatoire (NOT NULL, restrictOnDelete) sur
        // App\Models\Document — un renommage/une suppression malencontreuse
        // du type PV_SOUTENANCE (voir TypeDocumentSeeder) casserait sinon ce
        // dépôt avec une erreur SQL brute plutôt qu'un message clair.
        $type = $this->typeDocumentPvSoutenance();
        abort_if(! $type, 500, "Le type de document \"PV_SOUTENANCE\" est introuvable — contactez l'administrateur.");

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

        $document = Document::create([
            'description' => $pvSoutenance->niveauLibelle().' — '.$pvSoutenance->nomComplet().' — '.$fichier->libelle(),
            'type_document_id' => $type->id,
            'sous_type_document_id' => null,
            'filiere_id' => $pvSoutenance->filiere_id,
            'niveau_id' => null,
            'annee_academique_id' => null,
            'semestre' => null,
            'theme_memoire' => $pvSoutenance->theme,
            'date_soutenance' => $pvSoutenance->date_soutenance,
            'chemin' => $chemin,
            'nom_original' => $fichierUploade->getClientOriginalName(),
            'extension' => pathinfo($fichierUploade->getClientOriginalName(), PATHINFO_EXTENSION),
            'taille' => $fichierUploade->getSize(),
            'uploaded_by' => Auth::id(),
        ]);

        $ancienDocumentId = $fichier->document_id;
        $fichier->update(['document_id' => $document->id]);

        if ($ancienDocumentId) {
            Document::query()->find($ancienDocumentId)?->delete();
        }

        return back()->with('succes', 'Document déposé.');
    }

    public function supprimerFichier(PvSoutenance $pvSoutenance, PvSoutenanceFichier $fichier): RedirectResponse
    {
        abort_unless(ResourceAccess::can('pv_soutenances', 'delete'), 403);
        abort_if($fichier->pv_soutenance_id !== $pvSoutenance->id, 404);

        $reponse = $this->refuserSiVerrouille($fichier->document);
        if ($reponse) {
            return $reponse;
        }

        if ($fichier->document_id) {
            Document::query()->find($fichier->document_id)?->delete();
            $fichier->update(['document_id' => null]);
        }

        return back()->with('succes', 'Document retiré. La case reste disponible pour un nouveau dépôt.');
    }

    /**
     * Enregistre (ou corrige) le retrait du diplôme — demande explicite du
     * client : savoir non seulement SI le diplôme a été retiré, mais QUI est
     * venu le chercher (l'étudiant lui-même, ou une autre personne — auquel
     * cas son nom est précisé) et À QUELLE DATE. Voir le docblock de
     * App\Models\PvSoutenance pour la distinction entre `diplome_date_retrait`
     * (saisie à la main) et `diplome_retrait_enregistre_le` (toujours
     * "maintenant", jamais modifiable). Peut être rappelée plusieurs fois
     * pour corriger une saisie (pas de verrou après le premier enregistrement).
     */
    public function enregistrerRetraitDiplome(Request $request, PvSoutenance $pvSoutenance): RedirectResponse
    {
        abort_unless(ResourceAccess::can('pv_soutenances', 'edit'), 403);

        $donnees = $request->validate([
            'diplome_retirant' => ['required', Rule::in(array_keys(PvSoutenance::RETIRANTS))],
            'diplome_nom_retirant' => [
                'nullable', 'string', 'max:150',
                'required_if:diplome_retirant,'.PvSoutenance::RETIRANT_AUTRE,
            ],
            'diplome_date_retrait' => ['required', 'date'],
        ]);

        // "L'étudiant lui-même" : on efface un éventuel nom saisi avant un
        // changement de choix, pour ne jamais garder un nom orphelin en base.
        if ($donnees['diplome_retirant'] === PvSoutenance::RETIRANT_ETUDIANT) {
            $donnees['diplome_nom_retirant'] = null;
        }

        $donnees['diplome_retrait_enregistre_par_id'] = Auth::id();
        $donnees['diplome_retrait_enregistre_le'] = now();

        $pvSoutenance->update($donnees);

        return back()->with('succes', 'Retrait du diplôme enregistré.');
    }

    /** Annule un retrait enregistré par erreur — remet le diplôme à "Non retiré". */
    public function annulerRetraitDiplome(PvSoutenance $pvSoutenance): RedirectResponse
    {
        abort_unless(ResourceAccess::can('pv_soutenances', 'edit'), 403);

        $pvSoutenance->update([
            'diplome_retirant' => null,
            'diplome_nom_retirant' => null,
            'diplome_date_retrait' => null,
            'diplome_retrait_enregistre_par_id' => null,
            'diplome_retrait_enregistre_le' => null,
        ]);

        return back()->with('succes', 'Retrait du diplôme annulé.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function valider(Request $request, ?PvSoutenance $pvSoutenance = null): array
    {
        $tailleMaxPhotoKo = Parametre::actuel()->taille_max_avatar_mo * 1024;

        return $request->validate([
            'photo' => ['nullable', 'image', 'mimetypes:'.implode(',', self::TYPES_PHOTO_ACCEPTES), 'max:'.$tailleMaxPhotoKo],
            'niveau' => ['required', Rule::in(array_keys(PvSoutenance::NIVEAUX))],
            'date_soutenance' => ['required', 'date'],
            // Unique PAR NIVEAU (chaque registre papier a sa propre
            // numérotation) — voir le docblock de la migration
            // 2026_10_04_000001_create_pv_soutenances_table.php.
            'numero_ordre_registre' => [
                'required', 'string', 'max:50',
                Rule::unique('pv_soutenances', 'numero_ordre_registre')
                    ->where(fn ($q) => $q->where('niveau', $request->input('niveau')))
                    ->ignore($pvSoutenance?->id),
            ],
            'nom' => ['required', 'string', 'max:150'],
            'prenoms' => ['required', 'string', 'max:150'],
            'date_naissance' => ['required', 'date'],
            'lieu_naissance' => ['required', 'string', 'max:150'],
            // Unique pour toute la plateforme.
            'numero_pv' => [
                'required', 'string', 'max:50',
                Rule::unique('pv_soutenances', 'numero_pv')->ignore($pvSoutenance?->id),
            ],
            'filiere_id' => ['required', Rule::exists('filieres', 'id')],
            'theme' => ['required', 'string', 'max:2000'],
            'decision_jury' => ['required', Rule::in(array_keys(PvSoutenance::DECISIONS))],
            'moyenne' => ['required', 'numeric', 'between:0,20'],
            'mention' => ['required', Rule::in(array_keys(PvSoutenance::MENTIONS))],
            'president_jury' => ['required', 'string', 'max:150'],
            'directeur_memoire' => ['nullable', 'string', 'max:150'],
            'rapporteur' => ['nullable', 'string', 'max:150'],
            'maitre_stage' => ['nullable', 'string', 'max:150'],
        ]);
    }

    /**
     * Crée toutes les cases vides d'un PV à sa création : les deux rôles
     * fixes (diplôme, image du registre) + une case par champ supplémentaire
     * actif applicable à ce niveau (voir creerCasesChampsSupplementaires()
     * ci-dessous).
     */
    protected function creerCasesVides(PvSoutenance $pvSoutenance): void
    {
        foreach (array_keys(PvSoutenanceFichier::ROLES_FIXES) as $role) {
            PvSoutenanceFichier::create([
                'pv_soutenance_id' => $pvSoutenance->id,
                'role' => $role,
                'champ_supplementaire_id' => null,
                'document_id' => null,
            ]);
        }

        $this->creerCasesChampsSupplementaires($pvSoutenance);
    }

    /**
     * Cases pour les champs supplémentaires actifs applicables à CE niveau
     * (voir App\Models\ChampSupplementaire::applicableSoutenance()) — réponse
     * du client à la question posée lors de la construction de cet écran
     * ("Oui, via l'administration"), ajoutables depuis Référentiels > Champs
     * supplémentaires sans toucher au code. Les mêmes cases sont ajoutées
     * rétroactivement sur les PV déjà existants par
     * App\Http\Controllers\ChampSupplementaireController::synchroniserCasesRetroactives()
     * quand un champ est créé/modifié après coup.
     */
    protected function creerCasesChampsSupplementaires(PvSoutenance $pvSoutenance): void
    {
        ChampSupplementaire::query()
            ->where('actif', true)
            ->where('pour_soutenance', true)
            ->get()
            ->filter(fn (ChampSupplementaire $champ) => $champ->applicableSoutenance($pvSoutenance->niveau))
            ->each(function (ChampSupplementaire $champ) use ($pvSoutenance) {
                PvSoutenanceFichier::create([
                    'pv_soutenance_id' => $pvSoutenance->id,
                    'role' => PvSoutenanceFichier::ROLE_SUPPLEMENTAIRE,
                    'champ_supplementaire_id' => $champ->id,
                    'document_id' => null,
                ]);
            });
    }

    /** Type de document PV_SOUTENANCE déjà existant (voir TypeDocumentSeeder) — jamais recréé ici. */
    protected function typeDocumentPvSoutenance(): ?TypeDocument
    {
        return TypeDocument::query()->where('code', 'PV_SOUTENANCE')->first();
    }
}
