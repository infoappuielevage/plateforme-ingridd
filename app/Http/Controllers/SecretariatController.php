<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\SousTypeDocument;
use App\Models\TypeDocument;
use App\Support\ResourceAccess;
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
    public function index(Request $request, string $code, ?int $sousType = null): View
    {
        abort_unless(ResourceAccess::can('documents', 'view'), 403);

        $type = TypeDocument::query()->where('code', $code)->firstOrFail();

        $sousTypeDocument = null;

        if ($sousType !== null) {
            $sousTypeDocument = SousTypeDocument::query()
                ->where('id', $sousType)
                ->where('type_document_id', $type->id)
                ->firstOrFail();
        }

        $corbeille = $request->boolean('corbeille');

        $documents = Document::query()
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
            })
            ->latest('created_at')
            ->paginate(20)
            ->withQueryString();

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
        ]);
    }
}
