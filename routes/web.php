<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ParametresController;
use App\Http\Controllers\ProfilController;
use App\Http\Controllers\ReferentielController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SauvegardeController;
use App\Http\Controllers\SecretariatController;
use App\Http\Controllers\TypeDocumentController;
use App\Http\Controllers\UserController;
use App\Models\Document;
use App\Models\User;
use App\Support\ResourceAccess;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/*
|--------------------------------------------------------------------------
| Espace de gestion (remplace le panneau Filament)
|--------------------------------------------------------------------------
|
| Sur demande explicite du client ("je ne veux plus utiliser Filament dans
| mon projet, trop complexe à modifier"), reconstruit en Blade + Bootstrap
| classique, dans le style de son projet "bibliothèque numérique" — voir
| resources/views/layouts/gestion.blade.php pour le détail.
|
| Préfixe `/gestion` VOLONTAIREMENT différent de `/admin` (toujours occupé
| par le panneau Filament, laissé en place le temps de la validation) : les
| deux coexistent sans se marcher dessus pendant la transition. Une fois
| cette nouvelle interface validée par le client, `/admin` sera libéré
| (Filament retiré) et ce préfixe pourra devenir `/admin` à son tour.
|
| Le nom de route `login` (et lui seul) est important : c'est celui que le
| middleware `auth` de Laravel utilise par défaut pour rediriger un visiteur
| non connecté.
*/

// Page d'accueil : renvoie directement vers la connexion (sur demande du
// client) — cette plateforme n'a pas de vitrine publique, la page par
// défaut de Laravel (resources/views/welcome.blade.php) n'a donc aucune
// utilité ici. `Auth::check()` redirige un utilisateur déjà connecté droit
// vers son tableau de bord plutôt que de lui remontrer l'écran de
// connexion inutilement.
Route::get('/', function () {
    return redirect()->route(Auth::check() ? 'gestion.dashboard' : 'login');
});

Route::middleware('web')->group(function () {
    Route::get('/connexion', [AuthController::class, 'formulaire'])->name('login');
    Route::post('/gestion/connexion', [AuthController::class, 'connexion'])->name('gestion.connexion.soumettre');
});

Route::middleware(['web', 'auth'])->prefix('gestion')->name('gestion.')->group(function () {
    Route::post('/deconnexion', [AuthController::class, 'deconnexion'])->name('deconnexion');
    Route::get('/tableau-de-bord', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/historique', [AuditLogController::class, 'index'])->name('historique.index');

    // Mon profil — chaque utilisateur connecté modifie son propre compte ici,
    // voir le docblock de App\Http\Controllers\ProfilController.
    Route::prefix('profil')->name('profil.')->group(function () {
        Route::get('/', [ProfilController::class, 'edit'])->name('edit');
        Route::put('/', [ProfilController::class, 'update'])->name('update');
    });

    Route::prefix('documents')->name('documents.')->group(function () {
        Route::get('/', [DocumentController::class, 'index'])->name('index');
        Route::get('/creer', [DocumentController::class, 'create'])->name('create');
        Route::post('/', [DocumentController::class, 'store'])->name('store');
        Route::get('/{document}/modifier', [DocumentController::class, 'edit'])->name('edit');
        Route::put('/{document}', [DocumentController::class, 'update'])->name('update');
        Route::delete('/{document}', [DocumentController::class, 'destroy'])->name('destroy');
        // `{document}` non typé (int, pas Document $document) sur restaurer/
        // supprimer définitivement : la liaison de route implicite habituelle
        // exclut les enregistrements corbeillés (SoftDeletes), voir
        // DocumentController::restore()/forceDestroy() qui vont chercher
        // eux-mêmes avec onlyTrashed().
        Route::post('/{document}/restaurer', [DocumentController::class, 'restore'])->name('restore');
        Route::delete('/{document}/definitivement', [DocumentController::class, 'forceDestroy'])->name('force-destroy');
        Route::post('/{document}/basculer-retrait', [DocumentController::class, 'basculerRetrait'])->name('basculer-retrait');
    });

    // Une page par type de document "épinglé" (voir
    // App\Models\TypeDocument::$epingle_secretariat) — remplace le menu
    // "Secrétariat" et App\Filament\Pages\DocumentsParType. `{sousType?}`
    // reste un paramètre OPTIONNEL classique ici (contrairement à
    // l'ancienne page Filament) : voir le docblock de
    // App\Http\Controllers\SecretariatController pour pourquoi le bug de
    // paramètre de route optionnel qui touchait Livewire ne s'applique pas
    // à un contrôleur Laravel ordinaire.
    Route::get('/secretariat/{code}/{sousType?}', [SecretariatController::class, 'index'])->name('secretariat');

    // Filières / Niveaux / Années académiques — un seul contrôleur pour les
    // trois, voir le docblock de App\Http\Controllers\ReferentielController.
    // Le type de document (champs et sous-référentiel en plus) a son propre
    // contrôleur dédié, voir plus bas.
    Route::prefix('referentiels/{referentiel}')->name('referentiels.')->group(function () {
        Route::get('/', [ReferentielController::class, 'index'])->name('index');
        Route::get('/creer', [ReferentielController::class, 'create'])->name('create');
        Route::post('/', [ReferentielController::class, 'store'])->name('store');
        Route::get('/{id}/modifier', [ReferentielController::class, 'edit'])->name('edit');
        Route::put('/{id}', [ReferentielController::class, 'update'])->name('update');
        Route::delete('/{id}', [ReferentielController::class, 'destroy'])->name('destroy');
        Route::post('/{id}/restaurer', [ReferentielController::class, 'restore'])->name('restore');
        Route::delete('/{id}/definitivement', [ReferentielController::class, 'forceDestroy'])->name('force-destroy');
    });

    // Types de documents — contrôleur dédié (champs + sous-types en plus),
    // voir le docblock de App\Http\Controllers\TypeDocumentController.
    Route::prefix('types-documents')->name('types-documents.')->group(function () {
        Route::get('/', [TypeDocumentController::class, 'index'])->name('index');
        Route::get('/creer', [TypeDocumentController::class, 'create'])->name('create');
        Route::post('/', [TypeDocumentController::class, 'store'])->name('store');
        Route::get('/{type}/modifier', [TypeDocumentController::class, 'edit'])->name('edit');
        Route::put('/{type}', [TypeDocumentController::class, 'update'])->name('update');
        Route::delete('/{type}', [TypeDocumentController::class, 'destroy'])->name('destroy');
        // `{id}` non typé sur restaurer/supprimer définitivement, comme pour
        // les autres référentiels (voir plus haut) : la liaison de route
        // implicite exclut les enregistrements corbeillés par défaut.
        Route::post('/{id}/restaurer', [TypeDocumentController::class, 'restore'])->name('restore');
        Route::delete('/{id}/definitivement', [TypeDocumentController::class, 'forceDestroy'])->name('force-destroy');

        // Sous-types — gérés directement sur la page de modification du type
        // parent (voir gestion/types-documents/form.blade.php), pas de liste
        // ni de corbeille propres.
        Route::prefix('/{type}/sous-types')->name('sous-types.')->group(function () {
            Route::post('/', [TypeDocumentController::class, 'storeSousType'])->name('store');
            Route::put('/{sousType}', [TypeDocumentController::class, 'updateSousType'])->name('update');
            Route::delete('/{sousType}', [TypeDocumentController::class, 'destroySousType'])->name('destroy');
        });
    });

    // Réglages de la plateforme — une seule ligne en base, réservée en dur au
    // rôle admin (voir le docblock de App\Http\Controllers\ParametresController).
    Route::prefix('parametres')->name('parametres.')->group(function () {
        Route::get('/', [ParametresController::class, 'index'])->name('index');
        Route::put('/', [ParametresController::class, 'update'])->name('update');
    });

    // Sauvegardes — fichiers sur le disque `sauvegardes`, pas de modèle
    // Eloquent (voir le docblock de App\Http\Controllers\SauvegardeController).
    Route::prefix('sauvegardes')->name('sauvegardes.')->group(function () {
        Route::get('/', [SauvegardeController::class, 'index'])->name('index');
        Route::post('/', [SauvegardeController::class, 'store'])->name('store');
        Route::delete('/{nom}', [SauvegardeController::class, 'destroy'])->name('destroy');
    });

    // Rôles & permissions — réservé en dur au rôle admin (voir le docblock de
    // App\Http\Controllers\RoleController).
    Route::prefix('roles')->name('roles.')->group(function () {
        Route::get('/', [RoleController::class, 'index'])->name('index');
        Route::get('/creer', [RoleController::class, 'create'])->name('create');
        Route::post('/', [RoleController::class, 'store'])->name('store');
        Route::get('/{role}/modifier', [RoleController::class, 'edit'])->name('edit');
        Route::put('/{role}', [RoleController::class, 'update'])->name('update');
        Route::delete('/{role}', [RoleController::class, 'destroy'])->name('destroy');
    });

    // Utilisateurs — réservé en dur au rôle admin (voir le docblock de
    // App\Http\Controllers\UserController).
    Route::prefix('utilisateurs')->name('utilisateurs.')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::get('/creer', [UserController::class, 'create'])->name('create');
        Route::post('/', [UserController::class, 'store'])->name('store');
        Route::get('/{utilisateur}/modifier', [UserController::class, 'edit'])->name('edit');
        Route::put('/{utilisateur}', [UserController::class, 'update'])->name('update');
        Route::delete('/{utilisateur}', [UserController::class, 'destroy'])->name('destroy');
    });
});

/*
|--------------------------------------------------------------------------
| Téléchargement d'un document
|--------------------------------------------------------------------------
|
| Le disque `documents` (voir config/filesystems.php) est privé — aucune
| URL publique ne sert ces fichiers directement. Cette route, protégée par
| l'authentification Filament et par ResourceAccess::can(), est le seul
| moyen d'obtenir le contenu d'un document.
|
| `{document}` est résolu manuellement avec `withTrashed()` plutôt que via
| la liaison de route implicite habituelle (`Document $document`) : celle-ci
| exclut les documents corbeillés par défaut (SoftDeletes), ce qui ferait
| échouer le téléchargement (404) depuis la corbeille — voir
| App\Filament\Resources\CorbeilleResource et le filtre "Corbeille" de
| DocumentResource, qui proposent tous les deux ce bouton sur des documents
| corbeillés.
*/
Route::middleware(['web', 'auth'])
    ->get('/documents/{document}/telecharger', function (int $document): StreamedResponse {
        abort_unless(ResourceAccess::can('documents', 'view'), 403);

        $document = Document::withTrashed()->findOrFail($document);

        abort_unless(Storage::disk('documents')->exists($document->chemin), 404);

        return Storage::disk('documents')->download($document->chemin, $document->nom_original);
    })
    ->name('documents.telecharger');

/*
|--------------------------------------------------------------------------
| Téléchargement d'une sauvegarde
|--------------------------------------------------------------------------
|
| Même logique que le téléchargement de document ci-dessus, mais réservée en
| dur au rôle admin (comme App\Filament\Pages\Sauvegardes) : une sauvegarde
| contient l'intégralité de la base de données et de tous les documents.
*/
Route::middleware(['web', 'auth'])
    ->get('/sauvegardes/{nom}/telecharger', function (string $nom): StreamedResponse {
        abort_unless(Auth::user()?->role === User::ROLE_ADMIN, 403);

        // `$nom` vient de l'URL : jamais de confiance avant de le passer à
        // Storage — un chemin contenant "/" ou "\" ne peut être qu'une
        // tentative de sortir du disque `sauvegardes`.
        abort_if(str_contains($nom, '/') || str_contains($nom, '\\'), 403);

        abort_unless(Storage::disk('sauvegardes')->exists($nom), 404);

        return Storage::disk('sauvegardes')->download($nom);
    })
    ->name('sauvegardes.telecharger');
