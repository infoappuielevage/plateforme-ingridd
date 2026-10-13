<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ListeTriable;
use App\Http\Controllers\Concerns\VerifieVerrouillage;
use App\Models\Depense;
use App\Models\Document;
use App\Models\Parametre;
use App\Models\TypeDepense;
use App\Models\TypeDocument;
use App\Support\ResourceAccess;
use App\Support\TypesFichiers;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Gestion des Dépenses — demande explicite du client ("intègre la GESTION
 * des DEPENSES dont : les depenses Bancaire ; les vacataire ; les Salaires;
 * les achats; tous composer de bordereau en pdf, et un fichier en exel.
 * avec la possibilité d'integrer d'autre depenses comme ce qui est en place
 * pour champs supplementaire").
 *
 * Catégories (App\Models\TypeDepense) gérées depuis l'administration, pas
 * ici — voir App\Http\Controllers\TypeDepenseController. Chaque onglet
 * (route `{typeDepense:code}`, liaison de modèle directement sur `code` —
 * ÉVITE le piège `->where()` chaîné sur un groupe de routes déjà rencontré
 * avec les Recettes, voir routes/web.php) affiche la liste des années
 * académiques pour cette catégorie, CHACUNE DÉJÀ POURVUE de sa ligne
 * App\Models\Depense (créée automatiquement, voir App\Models\TypeDepense::
 * synchroniserCasesRetroactives()/synchroniserPourAnnee()) — donc PAS
 * d'étape de création manuelle ici, contrairement à App\Http\Controllers\
 * RecetteDossierController (voir le docblock de la migration
 * 2026_10_20_000002_create_depenses_table.php) : uploaderBordereau()/
 * uploaderExcel() ci-dessous déposent directement le fichier sur la ligne
 * déjà existante.
 */
class DepenseController extends Controller
{
    use ListeTriable, VerifieVerrouillage;

    public function __construct()
    {
        abort_unless(ResourceAccess::can('depenses', 'view'), 403);
    }

    public function index(Request $request, TypeDepense $typeDepense): View
    {
        abort_if(! $typeDepense->actif, 404);

        $depenses = Depense::query()
            ->with(['anneeAcademique', 'bordereauDocument', 'excelDocument'])
            ->where('type_depense_id', $typeDepense->id)
            ->get();

        // Tri cliquable (demande explicite du client, voir le docblock de
        // App\Http\Controllers\Concerns\ListeTriable::trierCollection()) —
        // UNE seule colonne significative ici (l'année académique, les
        // autres colonnes du tableau sont des zones de dépôt de fichier, pas
        // des valeurs comparables) ; "desc" par défaut pour garder le même
        // ordre qu'avant cette fonctionnalité (année la plus récente en
        // premier).
        [$triActuel, $directionActuelle] = $this->resoudreTri($request, $this->colonnesTriables(), 'annee_academique', 'desc');
        $depenses = $this->trierCollection($depenses, $this->colonnesTriables(), [$triActuel, $directionActuelle]);

        $parametres = Parametre::actuel();

        return view('gestion.depenses.index', [
            'typeDepense' => $typeDepense,
            'categories' => $this->categoriesActives(),
            'depenses' => $depenses,
            'tailleMaxMo' => $parametres->taille_max_document_mo,
            'triActuel' => $triActuel,
            'directionActuelle' => $directionActuelle,
        ]);
    }

    /** @return array<string, \Closure(Depense): mixed> */
    protected function colonnesTriables(): array
    {
        return [
            'annee_academique' => fn (Depense $d) => $d->anneeAcademique?->libelle,
        ];
    }

    /** Dépose (ou remplace) le bordereau PDF d'une dépense. */
    public function uploaderBordereau(Request $request, TypeDepense $typeDepense, Depense $depense): RedirectResponse
    {
        abort_unless(ResourceAccess::can('depenses', 'edit'), 403);
        abort_if($depense->type_depense_id !== $typeDepense->id, 404);

        $reponse = $this->refuserSiVerrouille($depense->bordereauDocument);
        if ($reponse) {
            return $reponse;
        }

        $document = $this->creerDocumentDepose($request, $depense, 'Bordereau');

        $ancienDocumentId = $depense->bordereau_document_id;
        $depense->update(['bordereau_document_id' => $document->id]);

        if ($ancienDocumentId) {
            Document::query()->find($ancienDocumentId)?->delete();
        }

        return back()->with('succes', 'Bordereau déposé.');
    }

    public function supprimerBordereau(TypeDepense $typeDepense, Depense $depense): RedirectResponse
    {
        abort_unless(ResourceAccess::can('depenses', 'delete'), 403);
        abort_if($depense->type_depense_id !== $typeDepense->id, 404);

        $reponse = $this->refuserSiVerrouille($depense->bordereauDocument);
        if ($reponse) {
            return $reponse;
        }

        if ($depense->bordereau_document_id) {
            Document::query()->find($depense->bordereau_document_id)?->delete();
            $depense->update(['bordereau_document_id' => null]);
        }

        return back()->with('succes', 'Bordereau retiré.');
    }

    /** Dépose (ou remplace) le fichier Excel d'une dépense. */
    public function uploaderExcel(Request $request, TypeDepense $typeDepense, Depense $depense): RedirectResponse
    {
        abort_unless(ResourceAccess::can('depenses', 'edit'), 403);
        abort_if($depense->type_depense_id !== $typeDepense->id, 404);

        $reponse = $this->refuserSiVerrouille($depense->excelDocument);
        if ($reponse) {
            return $reponse;
        }

        $document = $this->creerDocumentDepose($request, $depense, 'Fichier Excel');

        $ancienDocumentId = $depense->excel_document_id;
        $depense->update(['excel_document_id' => $document->id]);

        if ($ancienDocumentId) {
            Document::query()->find($ancienDocumentId)?->delete();
        }

        return back()->with('succes', 'Fichier Excel déposé.');
    }

    public function supprimerExcel(TypeDepense $typeDepense, Depense $depense): RedirectResponse
    {
        abort_unless(ResourceAccess::can('depenses', 'delete'), 403);
        abort_if($depense->type_depense_id !== $typeDepense->id, 404);

        $reponse = $this->refuserSiVerrouille($depense->excelDocument);
        if ($reponse) {
            return $reponse;
        }

        if ($depense->excel_document_id) {
            Document::query()->find($depense->excel_document_id)?->delete();
            $depense->update(['excel_document_id' => null]);
        }

        return back()->with('succes', 'Fichier Excel retiré.');
    }

    /** Catégories actives, pour les onglets (voir gestion/depenses/_onglets.blade.php) — partagé par index() et la redirection de routes/web.php. */
    protected function categoriesActives()
    {
        return TypeDepense::query()->where('actif', true)->orderBy('ordre')->get();
    }

    /** Dépose le fichier envoyé et crée le App\Models\Document derrière — même principe que EtatFinancierController::creerDocumentDepose(). */
    protected function creerDocumentDepose(Request $request, Depense $depense, string $libelleCase): Document
    {
        $type = $this->typeDocumentDepense();
        abort_if(! $type, 500, "Le type de document \"DEPENSE\" est introuvable — contactez l'administrateur.");

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

        $depense->loadMissing(['typeDepense', 'anneeAcademique']);

        return Document::create([
            'description' => $depense->typeDepense->libelle.' '.$depense->anneeAcademique?->libelle.' — '.$libelleCase,
            'type_document_id' => $type->id,
            'annee_academique_id' => $depense->annee_academique_id,
            'chemin' => $chemin,
            'nom_original' => $fichierUploade->getClientOriginalName(),
            'extension' => pathinfo($fichierUploade->getClientOriginalName(), PATHINFO_EXTENSION),
            'taille' => $fichierUploade->getSize(),
            'uploaded_by' => Auth::id(),
        ]);
    }

    /** Type de document DEPENSE déjà existant (voir TypeDocumentSeeder) — jamais recréé ici. */
    protected function typeDocumentDepense(): ?TypeDocument
    {
        return TypeDocument::query()->where('code', 'DEPENSE')->first();
    }
}
