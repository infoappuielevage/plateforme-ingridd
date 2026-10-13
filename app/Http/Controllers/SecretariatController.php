<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ListeTriable;
use App\Models\AnneeAcademique;
use App\Models\Document;
use App\Models\Filiere;
use App\Models\Niveau;
use App\Models\SousTypeDocument;
use App\Models\TypeDocument;
use App\Models\User;
use App\Support\ResourceAccess;
use App\Support\TypesEcranDedie;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Une page dédiée par type de document "épinglé" (voir
 * App\Models\TypeDocument::$epingle_secretariat) — remplace
 * App\Filament\Pages\DocumentsParType (sur demande du client, qui ne veut
 * plus de Filament). Même principe : réutilise entièrement le formulaire et
 * les actions de DocumentController (créer/modifier/télécharger/retrait/
 * corbeille, via les routes gestion.documents.*), seule la LISTE affichée
 * ici change — filtrée sur ce type (et, le cas échéant, ce sous-type), avec
 * des colonnes adaptées aux champs complémentaires que ce type a activés
 * (voir resources/views/gestion/secretariat/show.blade.php).
 *
 * Priorité de mise en place demandée par le client ("avant de revoir les
 * autres") : PV de délibération, Relevés de notes, Diplômes/Attestations,
 * Registre des soutenances — voir TypeDocumentSeeder::CONFIGURATION, la
 * SEULE chose qui détermine aujourd'hui quels types ont une page dédiée.
 * Un administrateur peut épingler n'importe quel AUTRE type plus tard
 * depuis Administration > Référentiels > Types de documents, sans toucher
 * au code : cette page fonctionne pour tout type, épinglé ou non — le menu
 * (voir layouts/gestion.blade.php) ne fait que choisir lesquels afficher en
 * raccourci.
 *
 * Contrairement à App\Filament\Pages\DocumentsParType, `{sousType}` est un
 * paramètre de route OPTIONNEL classique (`{sousType?}`) : le bug Livewire/
 * Filament documenté dans DocumentsParType (paramètre de route optionnel
 * omis => tableau de paramètres mixte positionnel/nommé refusé par PHP 8)
 * ne concerne que le mécanisme interne de "mount()" de Livewire — un
 * contrôleur Laravel classique comme celui-ci reçoit simplement `null`,
 * sans ce problème.
 */
class SecretariatController extends Controller
{
    use ListeTriable;

    public function index(Request $request, string $code, ?int $sousType = null): View
    {
        abort_unless(ResourceAccess::can('documents', 'view'), 403);

        $type = TypeDocument::query()->where('code', $code)->firstOrFail();

        // Jamais pour un type à écran dédié (voir App\Support\TypesEcranDedie)
        // : son `code` n'est accessible ici que par manipulation directe de
        // l'URL (le menu ne pointe jamais vers un de ces cinq codes,
        // `epingle_secretariat` leur ayant été explicitement retiré — voir
        // database/seeders/TypeDocumentSeeder.php), et cette liste ne
        // vérifie que le droit générique "documents" -> "Voir" : sans ce
        // garde-fou, un rôle n'ayant QUE ce droit générique (ex.
        // "Gestionnaire", voir database/seeders/RoleSeeder.php) pouvait
        // consulter ici une liste complète de pièces d'étudiant ou de PV de
        // soutenance sans avoir le droit du module correspondant — même
        // faille, même correction que DocumentController::index() et les
        // routes documents.telecharger/voir ("revoir la sécurité pour
        // éviter l'accessibilité à des pages confidentielles sans
        // autorisation").
        abort_if(in_array($type->code, TypesEcranDedie::CODES, true), 404);

        $sousTypeDocument = null;

        if ($sousType !== null) {
            $sousTypeDocument = SousTypeDocument::query()
                ->where('id', $sousType)
                ->where('type_document_id', $type->id)
                ->firstOrFail();
        }

        $corbeille = $request->boolean('corbeille');

        $colonnesTriables = $this->colonnesTriables($type);
        $triResolu = $this->resoudreTri($request, $colonnesTriables, 'created_at', 'desc');

        $requete = Document::query()
            ->with(['sousTypeDocument', 'filiere', 'niveau', 'anneeAcademique', 'uploadePar'])
            ->where('type_document_id', $type->id)
            ->when($sousTypeDocument, fn ($q) => $q->where('sous_type_document_id', $sousTypeDocument->id))
            ->when($corbeille, fn ($q) => $q->onlyTrashed())
            ->when($request->filled('statut_retrait'), fn ($q) => $q->where('statut_retrait', $request->string('statut_retrait')))
            ->when($request->filled('recherche'), function ($q) use ($request) {
                $terme = '%'.$request->string('recherche').'%';
                $q->where(function ($q) use ($terme) {
                    $q->where('description', 'like', $terme)
                        ->orWhere('nom_original', 'like', $terme)
                        ->orWhere('nom_titulaire', 'like', $terme)
                        ->orWhere('numero_diplome', 'like', $terme)
                        ->orWhere('theme_memoire', 'like', $terme);
                });
            });
        $this->appliquerTri($requete, $colonnesTriables, $triResolu);

        $documents = $requete->paginate(8)->withQueryString();

        return view('gestion.secretariat.show', [
            'type' => $type,
            'sousTypeDocument' => $sousTypeDocument,
            // Les autres sous-types actifs de ce type (ex. les autres
            // catégories d'Attestation) — pour un sélecteur rapide qui
            // reste sur cette même page dédiée plutôt que de renvoyer vers
            // le filtre générique de la liste des documents.
            'sousTypesDisponibles' => $type->sousTypes()->where('actif', true)->get(),
            'documents' => $documents,
            'corbeille' => $corbeille,
            'triActuel' => $triResolu[0],
            'directionActuelle' => $triResolu[1],
        ]);
    }

    /**
     * Colonnes triables de la liste (voir App\Http\Controllers\Concerns\ListeTriable)
     * — DYNAMIQUE selon les champs complémentaires activés sur ce type (voir
     * resources/views/gestion/secretariat/show.blade.php, même condition
     * champs_diplome_actifs/champs_soutenance_actifs qui choisit les
     * colonnes affichées). "Sous-type" n'est volontairement pas incluse :
     * colonne elle-même conditionnelle à l'affichage (visible seulement
     * quand aucun sous-type précis n'est sélectionné), peu d'intérêt à la
     * trier. "Filière"/"Niveau"/"Année académique"/"Ajouté par" sont des
     * relations, donc une sous-requête corrélée plutôt qu'un orderBy
     * direct — même principe que PvSoutenanceController::colonnesTriables().
     * "created_at" reste le tri par défaut (identique à l'ancien
     * ->latest('created_at')) même si aucune colonne "Date d'ajout" dédiée
     * n'existe (elle est affichée à l'intérieur de la cellule "Fichier").
     *
     * @return array<string, string|\Closure>
     */
    protected function colonnesTriables(TypeDocument $type): array
    {
        $colonnes = [
            'created_at' => 'created_at',
            'nom_original' => 'nom_original',
            'ajoute_par' => fn ($query, string $direction) => $query->orderBy(
                User::select('name')->whereColumn('users.id', 'documents.uploaded_by'),
                $direction
            ),
        ];

        if ($type->champs_diplome_actifs) {
            return $colonnes + [
                'nom_titulaire' => 'nom_titulaire',
                'numero_diplome' => 'numero_diplome',
                'date_delivrance' => 'date_delivrance',
                'mention' => 'mention',
                'statut_retrait' => 'statut_retrait',
            ];
        }

        if ($type->champs_soutenance_actifs) {
            return $colonnes + [
                'nom_titulaire' => 'nom_titulaire',
                'theme_memoire' => 'theme_memoire',
                'date_soutenance' => 'date_soutenance',
                'membres_jury' => 'membres_jury',
            ];
        }

        return $colonnes + [
            'filiere' => fn ($query, string $direction) => $query->orderBy(
                Filiere::select('libelle')->whereColumn('filieres.id', 'documents.filiere_id'),
                $direction
            ),
            'niveau' => fn ($query, string $direction) => $query->orderBy(
                Niveau::select('libelle')->whereColumn('niveaux.id', 'documents.niveau_id'),
                $direction
            ),
            'annee_academique' => fn ($query, string $direction) => $query->orderBy(
                AnneeAcademique::select('libelle')->whereColumn('annees_academiques.id', 'documents.annee_academique_id'),
                $direction
            ),
        ];
    }
}
