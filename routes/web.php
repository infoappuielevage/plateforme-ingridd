<?php

use App\Http\Controllers\AnnonceController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\ChampSupplementaireController;
use App\Http\Controllers\ContratController;
use App\Http\Controllers\CorbeilleGlobaleController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepenseController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\EtatFinancierController;
use App\Http\Controllers\EtudiantController;
use App\Http\Controllers\ListeFinanceEtudiantController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ParametresController;
use App\Http\Controllers\ProfilController;
use App\Http\Controllers\PvSoutenanceController;
use App\Http\Controllers\RecetteDossierController;
use App\Http\Controllers\RecetteScolariteController;
use App\Http\Controllers\ReferentielController;
use App\Http\Controllers\ReunionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SauvegardeController;
use App\Http\Controllers\SecretariatController;
use App\Http\Controllers\SessionDeliberationController;
use App\Http\Controllers\SessionReleveController;
use App\Http\Controllers\TypeContratController;
use App\Http\Controllers\TypeDepenseController;
use App\Http\Controllers\TypeDocumentController;
use App\Http\Controllers\TypePieceEtudiantController;
use App\Http\Controllers\UserController;
use App\Models\Document;
use App\Models\TypeContrat;
use App\Models\TypeDepense;
use App\Models\User;
use App\Support\ResourceAccess;
use App\Support\TypesEcranDedie;
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

    // Mot de passe oublié : s'appuie sur le broker Laravel standard déjà
    // configuré (config/auth.php, table password_reset_tokens — rien de
    // nouveau à migrer). Noms de routes volontairement différents des noms
    // par défaut de Laravel ("password.request"/"password.reset") pour
    // rester dans le style français du reste du projet — voir
    // AppServiceProvider::boot() qui indique à Laravel d'utiliser
    // "mot-de-passe.reinitialiser" (ResetPassword::createUrlUsing) à la
    // place du nom par défaut pour construire le lien envoyé par email.
    Route::get('/mot-de-passe-oublie', [AuthController::class, 'formulaireOubli'])->name('mot-de-passe.oublie');
    Route::post('/mot-de-passe-oublie', [AuthController::class, 'envoyerLienReinitialisation'])->name('mot-de-passe.envoyer');
    Route::get('/reinitialiser-mot-de-passe/{token}', [AuthController::class, 'formulaireReinitialisation'])->name('mot-de-passe.reinitialiser');
    Route::post('/reinitialiser-mot-de-passe', [AuthController::class, 'reinitialiserMotDePasse'])->name('mot-de-passe.mettre-a-jour');
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

    // Cloche de notifications (voir layouts/gestion.blade.php) — chacun ne
    // gère que les SIENNES, voir le docblock de NotificationController.
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::post('/tout-marquer-comme-lu', [NotificationController::class, 'toutMarquerCommeLue'])->name('tout-marquer-comme-lu');
        Route::post('/{notification}/marquer-comme-lue', [NotificationController::class, 'marquerCommeLue'])->name('marquer-comme-lue');
    });

    Route::prefix('documents')->name('documents.')->group(function () {
        Route::get('/', [DocumentController::class, 'index'])->name('index');
        Route::get('/creer', [DocumentController::class, 'create'])->name('create');
        Route::post('/', [DocumentController::class, 'store'])->name('store');
        // Export Excel — demandé par le client, voir le docblock de export().
        Route::get('/export', [DocumentController::class, 'export'])->name('export');
        // Avant les routes `{document}` ci-dessous : "corbeille" ne doit
        // jamais être interprété comme un id de document.
        Route::delete('/corbeille/vider', [DocumentController::class, 'viderCorbeille'])->name('corbeille.vider');
        // Actions groupées (sélection multiple via cases à cocher, voir
        // resources/views/gestion/documents/index.blade.php) — en POST,
        // jamais DELETE : un même <form> partage plusieurs boutons de
        // soumission avec des `formaction` différents, et HTML ne connaît
        // pas de `formmethod="delete"`.
        Route::post('/corbeille-groupee', [DocumentController::class, 'mettreCorbeilleGroupee'])->name('corbeille-groupee');
        Route::post('/corbeille/restaurer-groupe', [DocumentController::class, 'restaurerGroupe'])->name('corbeille.restaurer-groupe');
        Route::post('/corbeille/definitivement-groupe', [DocumentController::class, 'forceDestroyGroupe'])->name('corbeille.force-destroy-groupe');
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
        // Verrouillage des documents (voir App\Support\Verrouillage) —
        // verrouillage manuel et déverrouillage explicite, gérés par le droit
        // ResourceAccess "verrouillage_documents" > "Modifier" (voir
        // DocumentController::verrouiller()/deverrouiller()).
        Route::post('/{document}/verrouiller', [DocumentController::class, 'verrouiller'])->name('verrouiller');
        Route::post('/{document}/deverrouiller', [DocumentController::class, 'deverrouiller'])->name('deverrouiller');
    });

    // Gestion des PV de délibération — structure dédiée (voir le docblock de
    // App\Http\Controllers\SessionDeliberationController), remplace le simple
    // "un document = un fichier" utilisé jusqu'ici pour ce type. Chaque
    // fichier déposé (note d'information, PV par semestre/année de thèse)
    // reste un App\Models\Document ordinaire, d'où les deux sous-routes
    // `fichiers/{fichier}` qui délèguent le dépôt/retrait à ce contrôleur
    // plutôt qu'à DocumentController.
    Route::prefix('sessions-deliberation')->name('sessions-deliberation.')->group(function () {
        Route::get('/', [SessionDeliberationController::class, 'index'])->name('index');
        Route::get('/creer', [SessionDeliberationController::class, 'create'])->name('create');
        Route::post('/', [SessionDeliberationController::class, 'store'])->name('store');
        // Export Excel — demandé par le client, AVANT "/{sessionDeliberation}"
        // pour les mêmes raisons que les actions groupées ci-dessous.
        Route::get('/export', [SessionDeliberationController::class, 'export'])->name('export');
        // "Vider la corbeille" — MÊME PRINCIPE que documents.corbeille.vider
        // plus haut, déclarée AVANT les routes `{sessionDeliberation}` pour
        // que "corbeille" ne soit jamais pris pour un id.
        Route::delete('/corbeille/vider', [SessionDeliberationController::class, 'viderCorbeille'])->name('corbeille.vider');
        // Actions groupées (sélection multiple via cases à cocher, voir
        // resources/views/gestion/sessions-deliberation/index.blade.php) —
        // MÊME PRINCIPE que documents.corbeille-groupee et consorts plus
        // haut, déclarées AVANT les routes `{sessionDeliberation}` pour que
        // "corbeille-groupee" ne soit jamais pris pour un id.
        Route::post('/corbeille-groupee', [SessionDeliberationController::class, 'mettreCorbeilleGroupee'])->name('corbeille-groupee');
        Route::post('/corbeille/restaurer-groupe', [SessionDeliberationController::class, 'restaurerGroupe'])->name('corbeille.restaurer-groupe');
        Route::post('/corbeille/definitivement-groupe', [SessionDeliberationController::class, 'forceDestroyGroupe'])->name('corbeille.force-destroy-groupe');
        Route::get('/{sessionDeliberation}', [SessionDeliberationController::class, 'show'])->name('show');
        Route::put('/{sessionDeliberation}', [SessionDeliberationController::class, 'update'])->name('update');
        Route::delete('/{sessionDeliberation}', [SessionDeliberationController::class, 'destroy'])->name('destroy');
        // Non typés (int), comme les autres restaurer/définitivement : la
        // liaison de route implicite exclut les enregistrements corbeillés.
        Route::post('/{sessionDeliberation}/restaurer', [SessionDeliberationController::class, 'restore'])->name('restore');
        Route::delete('/{sessionDeliberation}/definitivement', [SessionDeliberationController::class, 'forceDestroy'])->name('force-destroy');
        Route::post('/{sessionDeliberation}/fichiers/{fichier}', [SessionDeliberationController::class, 'uploaderFichier'])->name('fichiers.store');
        Route::delete('/{sessionDeliberation}/fichiers/{fichier}', [SessionDeliberationController::class, 'supprimerFichier'])->name('fichiers.destroy');
    });

    // Gestion des Relevés de notes — MIROIR volontaire du groupe
    // "sessions-deliberation" ci-dessus (voir le docblock de
    // App\Http\Controllers\SessionReleveController), "même chose que pour le
    // PV de délibération" sur demande explicite du client.
    Route::prefix('sessions-releves')->name('sessions-releves.')->group(function () {
        Route::get('/', [SessionReleveController::class, 'index'])->name('index');
        Route::get('/creer', [SessionReleveController::class, 'create'])->name('create');
        Route::post('/', [SessionReleveController::class, 'store'])->name('store');
        // Export Excel — même principe que "sessions-deliberation" ci-dessus.
        Route::get('/export', [SessionReleveController::class, 'export'])->name('export');
        // "Vider la corbeille" — même principe que le groupe "sessions-deliberation" ci-dessus.
        Route::delete('/corbeille/vider', [SessionReleveController::class, 'viderCorbeille'])->name('corbeille.vider');
        // Actions groupées — même principe que le groupe "sessions-deliberation" ci-dessus.
        Route::post('/corbeille-groupee', [SessionReleveController::class, 'mettreCorbeilleGroupee'])->name('corbeille-groupee');
        Route::post('/corbeille/restaurer-groupe', [SessionReleveController::class, 'restaurerGroupe'])->name('corbeille.restaurer-groupe');
        Route::post('/corbeille/definitivement-groupe', [SessionReleveController::class, 'forceDestroyGroupe'])->name('corbeille.force-destroy-groupe');
        Route::get('/{sessionReleve}', [SessionReleveController::class, 'show'])->name('show');
        Route::put('/{sessionReleve}', [SessionReleveController::class, 'update'])->name('update');
        Route::delete('/{sessionReleve}', [SessionReleveController::class, 'destroy'])->name('destroy');
        Route::post('/{sessionReleve}/restaurer', [SessionReleveController::class, 'restore'])->name('restore');
        Route::delete('/{sessionReleve}/definitivement', [SessionReleveController::class, 'forceDestroy'])->name('force-destroy');
        Route::post('/{sessionReleve}/fichiers/{fichier}', [SessionReleveController::class, 'uploaderFichier'])->name('fichiers.store');
        Route::delete('/{sessionReleve}/fichiers/{fichier}', [SessionReleveController::class, 'supprimerFichier'])->name('fichiers.destroy');
    });

    // Gestion des PV de soutenance — structure dédiée (demande explicite du
    // client, à partir d'un exemple de registre papier), voir le docblock de
    // App\Http\Controllers\PvSoutenanceController. Contrairement aux deux
    // groupes ci-dessus, UN enregistrement = UN étudiant (pas une filière
    // entière pour une session), d'où une route `/{pvSoutenance}/modifier`
    // classique en plus (tous les champs restent modifiables ici, voir le
    // docblock de update() sur le contrôleur).
    Route::prefix('pv-soutenances')->name('pv-soutenances.')->group(function () {
        Route::get('/', [PvSoutenanceController::class, 'index'])->name('index');
        Route::get('/creer', [PvSoutenanceController::class, 'create'])->name('create');
        Route::post('/', [PvSoutenanceController::class, 'store'])->name('store');
        // Export Excel — demandé par le client, AVANT "/{pvSoutenance}" pour
        // les mêmes raisons que les actions groupées ci-dessous.
        Route::get('/export', [PvSoutenanceController::class, 'export'])->name('export');
        // Import Excel (liste des étudiants avec toutes leurs informations) —
        // demande explicite du client ("importer la liste des étudiants avec
        // tout les information a traver un fichier excel, après on pourrai
        // importer les fichier comme ce qui est deja en place"), MÊME
        // PRINCIPE que etudiants.import.* ci-dessous : déclarées AVANT
        // "/{pvSoutenance}" pour que "import"/"export" ne soient jamais pris
        // pour un id.
        Route::get('/import', [PvSoutenanceController::class, 'formulaireImport'])->name('import.formulaire');
        Route::get('/import/modele', [PvSoutenanceController::class, 'modeleImport'])->name('import.modele');
        Route::post('/import', [PvSoutenanceController::class, 'import'])->name('import');
        // "Vider la corbeille" — MÊME PRINCIPE que documents.corbeille.vider plus haut.
        Route::delete('/corbeille/vider', [PvSoutenanceController::class, 'viderCorbeille'])->name('corbeille.vider');
        // Actions groupées (sélection multiple via cases à cocher, voir
        // resources/views/gestion/pv-soutenances/index.blade.php) — MÊME
        // PRINCIPE que documents.corbeille-groupee et consorts plus haut.
        Route::post('/corbeille-groupee', [PvSoutenanceController::class, 'mettreCorbeilleGroupee'])->name('corbeille-groupee');
        Route::post('/corbeille/restaurer-groupe', [PvSoutenanceController::class, 'restaurerGroupe'])->name('corbeille.restaurer-groupe');
        Route::post('/corbeille/definitivement-groupe', [PvSoutenanceController::class, 'forceDestroyGroupe'])->name('corbeille.force-destroy-groupe');
        Route::get('/{pvSoutenance}', [PvSoutenanceController::class, 'show'])->name('show');
        Route::get('/{pvSoutenance}/modifier', [PvSoutenanceController::class, 'edit'])->name('edit');
        Route::put('/{pvSoutenance}', [PvSoutenanceController::class, 'update'])->name('update');
        Route::delete('/{pvSoutenance}', [PvSoutenanceController::class, 'destroy'])->name('destroy');
        Route::post('/{pvSoutenance}/restaurer', [PvSoutenanceController::class, 'restore'])->name('restore');
        Route::delete('/{pvSoutenance}/definitivement', [PvSoutenanceController::class, 'forceDestroy'])->name('force-destroy');
        Route::post('/{pvSoutenance}/fichiers/{fichier}', [PvSoutenanceController::class, 'uploaderFichier'])->name('fichiers.store');
        Route::delete('/{pvSoutenance}/fichiers/{fichier}', [PvSoutenanceController::class, 'supprimerFichier'])->name('fichiers.destroy');
        // Retrait PHYSIQUE du diplôme — qui est venu le chercher et quand
        // (demande explicite du client), voir le docblock de
        // PvSoutenanceController::enregistrerRetraitDiplome(). Rattaché au PV
        // lui-même (pas à une case), voir le docblock d'App\Models\PvSoutenance.
        Route::post('/{pvSoutenance}/retrait-diplome', [PvSoutenanceController::class, 'enregistrerRetraitDiplome'])->name('retrait-diplome.store');
        Route::delete('/{pvSoutenance}/retrait-diplome', [PvSoutenanceController::class, 'annulerRetraitDiplome'])->name('retrait-diplome.destroy');
    });

    // Gestion des PV de réunion — structure dédiée (demande explicite du
    // client, "avançons sur le PV des réunions"), voir le docblock de
    // App\Http\Controllers\ReunionController. Comme PvSoutenanceController,
    // TOUS les champs restent modifiables après création, d'où la route
    // `/{reunion}/modifier` classique. Contrairement aux trois groupes
    // ci-dessus, deux sous-routes fichiers distinctes : `rapport` (case fixe
    // unique) et `pieces-jointes` (liste en nombre libre, voir le docblock
    // du contrôleur).
    Route::prefix('reunions')->name('reunions.')->group(function () {
        Route::get('/', [ReunionController::class, 'index'])->name('index');
        Route::get('/creer', [ReunionController::class, 'create'])->name('create');
        Route::post('/', [ReunionController::class, 'store'])->name('store');
        // Export Excel — demandé par le client, AVANT "/{reunion}" pour les
        // mêmes raisons que les actions groupées ci-dessous.
        Route::get('/export', [ReunionController::class, 'export'])->name('export');
        // "Vider la corbeille" — même principe que les autres écrans dédiés ci-dessus.
        Route::delete('/corbeille/vider', [ReunionController::class, 'viderCorbeille'])->name('corbeille.vider');
        // Actions groupées — même principe que les autres écrans dédiés ci-dessus.
        Route::post('/corbeille-groupee', [ReunionController::class, 'mettreCorbeilleGroupee'])->name('corbeille-groupee');
        Route::post('/corbeille/restaurer-groupe', [ReunionController::class, 'restaurerGroupe'])->name('corbeille.restaurer-groupe');
        Route::post('/corbeille/definitivement-groupe', [ReunionController::class, 'forceDestroyGroupe'])->name('corbeille.force-destroy-groupe');
        Route::get('/{reunion}', [ReunionController::class, 'show'])->name('show');
        Route::get('/{reunion}/modifier', [ReunionController::class, 'edit'])->name('edit');
        Route::put('/{reunion}', [ReunionController::class, 'update'])->name('update');
        Route::delete('/{reunion}', [ReunionController::class, 'destroy'])->name('destroy');
        Route::post('/{reunion}/restaurer', [ReunionController::class, 'restore'])->name('restore');
        Route::delete('/{reunion}/definitivement', [ReunionController::class, 'forceDestroy'])->name('force-destroy');
        Route::post('/{reunion}/rapport', [ReunionController::class, 'uploaderRapport'])->name('rapport.store');
        Route::delete('/{reunion}/rapport', [ReunionController::class, 'supprimerRapport'])->name('rapport.destroy');
        Route::post('/{reunion}/pieces-jointes', [ReunionController::class, 'ajouterPieceJointe'])->name('pieces-jointes.store');
        Route::delete('/{reunion}/pieces-jointes/{fichier}', [ReunionController::class, 'supprimerPieceJointe'])->name('pieces-jointes.destroy');
    });

    // Gestion du dossier étudiant — structure dédiée (demande explicite du
    // client, "travaillons sur le dossier étudiant"), voir le docblock de
    // App\Http\Controllers\EtudiantController. Particularité propre à cet
    // écran : un historique d'inscriptions par année académique (niveau +
    // filière changent chaque année) en plus des pièces en liste libre déjà
    // vues sur les réunions.
    Route::prefix('etudiants')->name('etudiants.')->group(function () {
        Route::get('/', [EtudiantController::class, 'index'])->name('index');
        Route::get('/creer', [EtudiantController::class, 'create'])->name('create');
        Route::post('/', [EtudiantController::class, 'store'])->name('store');
        // Actions groupées (sélection multiple via cases à cocher, voir
        // resources/views/gestion/etudiants/index.blade.php) — MÊME PRINCIPE
        // que documents.corbeille-groupee/corbeille.restaurer-groupe/
        // corbeille.force-destroy-groupe ci-dessus : toujours en POST (un
        // même <form> partage plusieurs boutons avec des `formaction`
        // différentes, et HTML ne connaît pas de `formmethod="delete"`), et
        // déclarées AVANT les routes `{etudiant}` ci-dessous pour que
        // "corbeille-groupee" ne soit jamais pris pour un id d'étudiant.
        Route::post('/corbeille-groupee', [EtudiantController::class, 'mettreCorbeilleGroupee'])->name('corbeille-groupee');
        Route::post('/corbeille/restaurer-groupe', [EtudiantController::class, 'restaurerGroupe'])->name('corbeille.restaurer-groupe');
        Route::post('/corbeille/definitivement-groupe', [EtudiantController::class, 'forceDestroyGroupe'])->name('corbeille.force-destroy-groupe');
        // "Vider la corbeille" — même principe que les actions groupées ci-dessus.
        Route::delete('/corbeille/vider', [EtudiantController::class, 'viderCorbeille'])->name('corbeille.vider');
        // Export/import Excel — demandés par le client, voir le docblock de
        // export()/import() ci-dessous. Mêmes précautions d'ordre que les
        // actions groupées plus haut : déclarées AVANT "/{etudiant}" pour
        // que "export"/"import" ne soient jamais pris pour un id.
        Route::get('/export', [EtudiantController::class, 'export'])->name('export');
        Route::get('/import', [EtudiantController::class, 'formulaireImport'])->name('import.formulaire');
        Route::get('/import/modele', [EtudiantController::class, 'modeleImport'])->name('import.modele');
        Route::post('/import', [EtudiantController::class, 'import'])->name('import');
        Route::get('/{etudiant}', [EtudiantController::class, 'show'])->name('show');
        Route::get('/{etudiant}/modifier', [EtudiantController::class, 'edit'])->name('edit');
        Route::put('/{etudiant}', [EtudiantController::class, 'update'])->name('update');
        Route::delete('/{etudiant}', [EtudiantController::class, 'destroy'])->name('destroy');
        Route::post('/{etudiant}/restaurer', [EtudiantController::class, 'restore'])->name('restore');
        Route::delete('/{etudiant}/definitivement', [EtudiantController::class, 'forceDestroy'])->name('force-destroy');
        Route::post('/{etudiant}/inscriptions', [EtudiantController::class, 'ajouterInscription'])->name('inscriptions.store');
        Route::put('/{etudiant}/inscriptions/{inscription}', [EtudiantController::class, 'modifierInscription'])->name('inscriptions.update');
        Route::delete('/{etudiant}/inscriptions/{inscription}', [EtudiantController::class, 'supprimerInscription'])->name('inscriptions.destroy');
        Route::post('/{etudiant}/fichiers/{fichier}', [EtudiantController::class, 'uploaderFichier'])->name('fichiers.store');
        Route::delete('/{etudiant}/fichiers/{fichier}', [EtudiantController::class, 'supprimerFichier'])->name('fichiers.destroy');
    });

    // États financiers — structure dédiée (demande explicite du client,
    // "intègre la gestion des États financières"), voir le docblock de
    // App\Http\Controllers\EtatFinancierController. Un seul fichier par
    // enregistrement (pas de table de "cases" séparée) : les routes
    // `fichier` ci-dessous visent directement l'état financier, sans
    // {fichier} dans l'URL — même raisonnement que `rapport` sur les PV de
    // réunion.
    Route::prefix('etats-financiers')->name('etats-financiers.')->group(function () {
        Route::get('/', [EtatFinancierController::class, 'index'])->name('index');
        Route::get('/creer', [EtatFinancierController::class, 'create'])->name('create');
        Route::post('/', [EtatFinancierController::class, 'store'])->name('store');
        Route::post('/corbeille-groupee', [EtatFinancierController::class, 'mettreCorbeilleGroupee'])->name('corbeille-groupee');
        Route::post('/corbeille/restaurer-groupe', [EtatFinancierController::class, 'restaurerGroupe'])->name('corbeille.restaurer-groupe');
        Route::post('/corbeille/definitivement-groupe', [EtatFinancierController::class, 'forceDestroyGroupe'])->name('corbeille.force-destroy-groupe');
        Route::delete('/corbeille/vider', [EtatFinancierController::class, 'viderCorbeille'])->name('corbeille.vider');
        Route::get('/{etatFinancier}', [EtatFinancierController::class, 'show'])->name('show');
        Route::get('/{etatFinancier}/modifier', [EtatFinancierController::class, 'edit'])->name('edit');
        Route::put('/{etatFinancier}', [EtatFinancierController::class, 'update'])->name('update');
        Route::delete('/{etatFinancier}', [EtatFinancierController::class, 'destroy'])->name('destroy');
        Route::post('/{etatFinancier}/restaurer', [EtatFinancierController::class, 'restore'])->name('restore');
        Route::delete('/{etatFinancier}/definitivement', [EtatFinancierController::class, 'forceDestroy'])->name('force-destroy');
        Route::post('/{etatFinancier}/fichier', [EtatFinancierController::class, 'uploaderFichier'])->name('fichier.store');
        Route::delete('/{etatFinancier}/fichier', [EtatFinancierController::class, 'supprimerFichier'])->name('fichier.destroy');
    });

    // Gestion des Recettes — demande explicite du client ("intègre la
    // Gestion des Recette qui est composer de : la Scolarité... le retrait
    // diplome... Frais de soutenance"), DEUX contrôleurs pour trois volets
    // (voir leurs docblocks respectifs) : Scolarité a sa propre structure de
    // cases (bordereaux + 3 relevés Mobile Money fixes + autres documents),
    // Retrait diplôme/Frais de soutenance partagent EXACTEMENT la même
    // structure (une liste libre de fichiers par année académique) via
    // RecetteDossierController, `{type}` ("retrait-diplome"|
    // "frais-soutenance") contraint ci-dessous.
    //
    // Route d'accueil "/recettes" : simple redirection vers l'onglet
    // Scolarité (premier des trois, voir resources/views/gestion/recettes/
    // _onglets.blade.php) — le lien de la sidebar pointe directement vers
    // scolarite.index, cette route ne sert qu'aux liens/favoris pointant
    // vers la racine du module.
    Route::get('/recettes', function () {
        abort_unless(ResourceAccess::can('recettes', 'view'), 403);

        return redirect()->route('gestion.recettes.scolarite.index');
    })->name('recettes.index');

    // Contrainte sur {role}/{type} : appliquée directement sur chaque route
    // qui déclare le paramètre (et non en la chaînant sur
    // Route::prefix(...)->name(...)->where(...)->group(...), qui casse le
    // merge interne des groupes de routes — RouteGroup::formatWhere() reçoit
    // alors le tableau d'attributs brut au lieu du sous-tableau "where" et
    // ça fait planter array_merge() ; vu en pratique au `php artisan
    // migrate` du client) — défense en profondeur, en plus de la liste
    // blanche déjà vérifiée côté contrôleur (RecetteScolariteFichier::
    // ROLES_FIXES, RecetteDossierController::TYPES_URL, etc.).
    Route::prefix('recettes/scolarite')->name('recettes.scolarite.')
        ->group(function () {
            Route::get('/', [RecetteScolariteController::class, 'index'])->name('index');
            Route::get('/creer', [RecetteScolariteController::class, 'create'])->name('create');
            Route::post('/', [RecetteScolariteController::class, 'store'])->name('store');
            // "Vider la corbeille" — déclarée AVANT "/{recetteScolarite}"
            // ci-dessous pour que "corbeille" ne soit jamais pris pour un id.
            Route::delete('/corbeille/vider', [RecetteScolariteController::class, 'viderCorbeille'])->name('corbeille.vider');
            Route::get('/{recetteScolarite}', [RecetteScolariteController::class, 'show'])->name('show');
            Route::delete('/{recetteScolarite}', [RecetteScolariteController::class, 'destroy'])->name('destroy');
            Route::post('/{recetteScolarite}/restaurer', [RecetteScolariteController::class, 'restore'])->name('restore');
            Route::delete('/{recetteScolarite}/definitivement', [RecetteScolariteController::class, 'forceDestroy'])->name('force-destroy');
            // Les trois cases fixes (Orange/Moov/Telecel Money).
            Route::post('/{recetteScolarite}/case/{role}', [RecetteScolariteController::class, 'uploaderCaseFixe'])->name('case.store')
                ->where('role', 'orange_money|moov_money|telecel_money');
            Route::delete('/{recetteScolarite}/case/{role}', [RecetteScolariteController::class, 'supprimerCaseFixe'])->name('case.destroy')
                ->where('role', 'orange_money|moov_money|telecel_money');
            // Les deux listes en nombre libre (bordereau/autre).
            Route::post('/{recetteScolarite}/liste/{role}', [RecetteScolariteController::class, 'ajouterDocumentListe'])->name('liste.store')
                ->where('role', 'bordereau|autre');
            Route::delete('/{recetteScolarite}/liste/{role}/{fichier}', [RecetteScolariteController::class, 'supprimerDocumentListe'])->name('liste.destroy')
                ->where('role', 'bordereau|autre');
        });

    Route::prefix('recettes/{type}')->name('recettes.dossiers.')
        ->group(function () {
            Route::get('/', [RecetteDossierController::class, 'index'])->name('index')
                ->where('type', 'retrait-diplome|frais-soutenance');
            Route::get('/creer', [RecetteDossierController::class, 'create'])->name('create')
                ->where('type', 'retrait-diplome|frais-soutenance');
            Route::post('/', [RecetteDossierController::class, 'store'])->name('store')
                ->where('type', 'retrait-diplome|frais-soutenance');
            // "Vider la corbeille" — déclarée AVANT "/{dossier}" ci-dessous
            // pour que "corbeille" ne soit jamais pris pour un id.
            Route::delete('/corbeille/vider', [RecetteDossierController::class, 'viderCorbeille'])->name('corbeille.vider')
                ->where('type', 'retrait-diplome|frais-soutenance');
            Route::get('/{dossier}', [RecetteDossierController::class, 'show'])->name('show')
                ->where('type', 'retrait-diplome|frais-soutenance');
            Route::delete('/{dossier}', [RecetteDossierController::class, 'destroy'])->name('destroy')
                ->where('type', 'retrait-diplome|frais-soutenance');
            Route::post('/{dossier}/restaurer', [RecetteDossierController::class, 'restore'])->name('restore')
                ->where('type', 'retrait-diplome|frais-soutenance');
            Route::delete('/{dossier}/definitivement', [RecetteDossierController::class, 'forceDestroy'])->name('force-destroy')
                ->where('type', 'retrait-diplome|frais-soutenance');
            Route::post('/{dossier}/fichiers', [RecetteDossierController::class, 'ajouterFichier'])->name('fichiers.store')
                ->where('type', 'retrait-diplome|frais-soutenance');
            Route::delete('/{dossier}/fichiers/{fichier}', [RecetteDossierController::class, 'supprimerFichier'])->name('fichiers.destroy')
                ->where('type', 'retrait-diplome|frais-soutenance');
        });

    // Gestion des Dépenses — demande explicite du client ("intègre la
    // GESTION des DEPENSES... avec la possibilité d'integrer d'autre
    // depenses comme ce qui est en place pour champs supplementaire"), voir
    // le docblock de App\Http\Controllers\DepenseController. `{typeDepense}`
    // est lié directement SUR LE CODE (`{typeDepense:code}`, voir
    // App\Models\TypeDepense) plutôt que contraint par une regex fixe
    // (impossible ici : les catégories sont une liste OUVERTE, gérée depuis
    // l'administration — voir types-depenses ci-dessous) — un code inconnu
    // échoue simplement la liaison de modèle (404 automatique de Laravel),
    // aucun risque de retomber dans le piège ->where()->group() déjà
    // rencontré avec les Recettes.
    Route::prefix('depenses')->name('depenses.')->group(function () {
        Route::get('/{typeDepense:code}', [DepenseController::class, 'index'])->name('index');
        Route::post('/{typeDepense:code}/{depense}/bordereau', [DepenseController::class, 'uploaderBordereau'])->name('bordereau.store');
        Route::delete('/{typeDepense:code}/{depense}/bordereau', [DepenseController::class, 'supprimerBordereau'])->name('bordereau.destroy');
        Route::post('/{typeDepense:code}/{depense}/excel', [DepenseController::class, 'uploaderExcel'])->name('excel.store');
        Route::delete('/{typeDepense:code}/{depense}/excel', [DepenseController::class, 'supprimerExcel'])->name('excel.destroy');
    });

    // Route d'accueil "/depenses" : redirige vers la première catégorie
    // active (par `ordre`), même principe que "/recettes" ci-dessus — sauf
    // s'il n'en existe encore aucune (base fraîchement migrée, pas encore
    // seedée), auquel cas un message explicite vaut mieux qu'une redirection
    // en boucle vers une page 404.
    Route::get('/depenses', function () {
        abort_unless(ResourceAccess::can('depenses', 'view'), 403);

        $premiere = TypeDepense::query()->where('actif', true)->orderBy('ordre')->first();

        abort_if(! $premiere, 404, "Aucune catégorie de dépense n'est encore configurée — voir Administration > Référentiels > Catégories de dépenses.");

        return redirect()->route('gestion.depenses.index', ['typeDepense' => $premiere->code]);
    })->name('depenses.accueil');

    // "Liste des étudiants (finances)" — demande explicite du client
    // ("intègre également la GESTION de la listes des etudiant... dans la
    // partie Finance"), voir le docblock de
    // App\Http\Controllers\ListeFinanceEtudiantController. Écran séparé de
    // Gestion des recettes (confirmé en amont), mais structure identique à
    // "recettes/{type}" ci-dessus — sans le segment `{type}`, un seul volet
    // ici.
    Route::prefix('listes-finances-etudiants')->name('listes-finances-etudiants.')->group(function () {
        Route::get('/', [ListeFinanceEtudiantController::class, 'index'])->name('index');
        Route::get('/creer', [ListeFinanceEtudiantController::class, 'create'])->name('create');
        Route::post('/', [ListeFinanceEtudiantController::class, 'store'])->name('store');
        // "Vider la corbeille" — déclarée AVANT "/{listeFinanceEtudiant}"
        // ci-dessous pour que "corbeille" ne soit jamais pris pour un id.
        Route::delete('/corbeille/vider', [ListeFinanceEtudiantController::class, 'viderCorbeille'])->name('corbeille.vider');
        Route::get('/{listeFinanceEtudiant}', [ListeFinanceEtudiantController::class, 'show'])->name('show');
        Route::delete('/{listeFinanceEtudiant}', [ListeFinanceEtudiantController::class, 'destroy'])->name('destroy');
        Route::post('/{listeFinanceEtudiant}/restaurer', [ListeFinanceEtudiantController::class, 'restore'])->name('restore');
        Route::delete('/{listeFinanceEtudiant}/definitivement', [ListeFinanceEtudiantController::class, 'forceDestroy'])->name('force-destroy');
        Route::post('/{listeFinanceEtudiant}/fichiers', [ListeFinanceEtudiantController::class, 'ajouterFichier'])->name('fichiers.store');
        Route::delete('/{listeFinanceEtudiant}/fichiers/{fichier}', [ListeFinanceEtudiantController::class, 'supprimerFichier'])->name('fichiers.destroy');
    });

    // Référentiel des pièces attendues dans un dossier étudiant — voir le
    // docblock de App\Http\Controllers\TypePieceEtudiantController, même
    // principe que le groupe "champs-supplementaires" ci-dessus.
    Route::prefix('types-pieces-etudiants')->name('types-pieces-etudiants.')->group(function () {
        Route::get('/', [TypePieceEtudiantController::class, 'index'])->name('index');
        Route::get('/creer', [TypePieceEtudiantController::class, 'create'])->name('create');
        Route::post('/', [TypePieceEtudiantController::class, 'store'])->name('store');
        // "Vider la corbeille" — déclarée AVANT "/{typePieceEtudiant}"
        // ci-dessous pour que "corbeille" ne soit jamais pris pour un id.
        Route::delete('/corbeille/vider', [TypePieceEtudiantController::class, 'viderCorbeille'])->name('corbeille.vider');
        Route::get('/{typePieceEtudiant}/modifier', [TypePieceEtudiantController::class, 'edit'])->name('edit');
        Route::put('/{typePieceEtudiant}', [TypePieceEtudiantController::class, 'update'])->name('update');
        Route::delete('/{typePieceEtudiant}', [TypePieceEtudiantController::class, 'destroy'])->name('destroy');
        Route::post('/{id}/restaurer', [TypePieceEtudiantController::class, 'restore'])->name('restore');
        Route::delete('/{id}/definitivement', [TypePieceEtudiantController::class, 'forceDestroy'])->name('force-destroy');
    });

    // Référentiel des catégories de dépenses — voir le docblock de
    // App\Http\Controllers\TypeDepenseController, même principe que le
    // groupe "types-pieces-etudiants" ci-dessus.
    Route::prefix('types-depenses')->name('types-depenses.')->group(function () {
        Route::get('/', [TypeDepenseController::class, 'index'])->name('index');
        Route::get('/creer', [TypeDepenseController::class, 'create'])->name('create');
        Route::post('/', [TypeDepenseController::class, 'store'])->name('store');
        // "Vider la corbeille" — déclarée AVANT "/{typeDepense}" ci-dessous
        // pour que "corbeille" ne soit jamais pris pour un id.
        Route::delete('/corbeille/vider', [TypeDepenseController::class, 'viderCorbeille'])->name('corbeille.vider');
        Route::get('/{typeDepense}/modifier', [TypeDepenseController::class, 'edit'])->name('edit');
        Route::put('/{typeDepense}', [TypeDepenseController::class, 'update'])->name('update');
        Route::delete('/{typeDepense}', [TypeDepenseController::class, 'destroy'])->name('destroy');
        Route::post('/{id}/restaurer', [TypeDepenseController::class, 'restore'])->name('restore');
        Route::delete('/{id}/definitivement', [TypeDepenseController::class, 'forceDestroy'])->name('force-destroy');
    });

    // Gestion des contrats — demande explicite du client ("intègre à
    // présent la gestion des contrat, on aurait les partenariat... les
    // contrat de prestation de Service, et tout autre contrat dont tu pense
    // que c'est necessaire"), voir le docblock de
    // App\Http\Controllers\ContratController. `{typeContrat:code}` lié
    // directement SUR LE CODE, EXACTEMENT comme `{typeDepense:code}`
    // ci-dessus (catégories en liste OUVERTE, gérée depuis l'administration
    // — voir types-contrats plus bas) : un code inconnu échoue simplement la
    // liaison de modèle (404 automatique), aucun risque du piège
    // ->where()->group() déjà rencontré avec les Recettes. Contrairement aux
    // Dépenses (lignes auto-générées, pas de création manuelle), un contrat
    // est créé À LA DEMANDE — d'où le CRUD complet ci-dessous, même
    // structure que "reunions" plus haut, simplement préfixée par l'onglet
    // de catégorie.
    Route::prefix('contrats')->name('contrats.')->group(function () {
        Route::get('/{typeContrat:code}', [ContratController::class, 'index'])->name('index');
        Route::get('/{typeContrat:code}/creer', [ContratController::class, 'create'])->name('create');
        Route::post('/{typeContrat:code}', [ContratController::class, 'store'])->name('store');
        Route::get('/{typeContrat:code}/export', [ContratController::class, 'export'])->name('export');
        Route::delete('/{typeContrat:code}/corbeille/vider', [ContratController::class, 'viderCorbeille'])->name('corbeille.vider');
        Route::post('/{typeContrat:code}/corbeille-groupee', [ContratController::class, 'mettreCorbeilleGroupee'])->name('corbeille-groupee');
        Route::post('/{typeContrat:code}/corbeille/restaurer-groupe', [ContratController::class, 'restaurerGroupe'])->name('corbeille.restaurer-groupe');
        Route::post('/{typeContrat:code}/corbeille/definitivement-groupe', [ContratController::class, 'forceDestroyGroupe'])->name('corbeille.force-destroy-groupe');
        Route::get('/{typeContrat:code}/{contrat}', [ContratController::class, 'show'])->name('show');
        Route::get('/{typeContrat:code}/{contrat}/modifier', [ContratController::class, 'edit'])->name('edit');
        Route::put('/{typeContrat:code}/{contrat}', [ContratController::class, 'update'])->name('update');
        Route::delete('/{typeContrat:code}/{contrat}', [ContratController::class, 'destroy'])->name('destroy');
        Route::post('/{typeContrat:code}/{contrat}/restaurer', [ContratController::class, 'restore'])->name('restore');
        Route::delete('/{typeContrat:code}/{contrat}/definitivement', [ContratController::class, 'forceDestroy'])->name('force-destroy');
        Route::post('/{typeContrat:code}/{contrat}/contrat-signe', [ContratController::class, 'uploaderContratSigne'])->name('contrat-signe.store');
        Route::delete('/{typeContrat:code}/{contrat}/contrat-signe', [ContratController::class, 'supprimerContratSigne'])->name('contrat-signe.destroy');
        Route::post('/{typeContrat:code}/{contrat}/avenants', [ContratController::class, 'ajouterAvenant'])->name('avenants.store');
        Route::delete('/{typeContrat:code}/{contrat}/avenants/{fichier}', [ContratController::class, 'supprimerAvenant'])->name('avenants.destroy');
    });

    // Route d'accueil "/contrats" : redirige vers la première catégorie
    // active (par `ordre`), même principe que "/depenses" ci-dessus.
    Route::get('/contrats', function () {
        abort_unless(ResourceAccess::can('contrats', 'view'), 403);

        $premiere = TypeContrat::query()->where('actif', true)->orderBy('ordre')->first();

        abort_if(! $premiere, 404, "Aucune catégorie de contrat n'est encore configurée — voir Administration > Référentiels > Catégories de contrats.");

        return redirect()->route('gestion.contrats.index', ['typeContrat' => $premiere->code]);
    })->name('contrats.accueil');

    // Référentiel des catégories de contrats — voir le docblock de
    // App\Http\Controllers\TypeContratController, même principe que le
    // groupe "types-depenses" ci-dessus.
    Route::prefix('types-contrats')->name('types-contrats.')->group(function () {
        Route::get('/', [TypeContratController::class, 'index'])->name('index');
        Route::get('/creer', [TypeContratController::class, 'create'])->name('create');
        Route::post('/', [TypeContratController::class, 'store'])->name('store');
        // "Vider la corbeille" — déclarée AVANT "/{typeContrat}" ci-dessous
        // pour que "corbeille" ne soit jamais pris pour un id.
        Route::delete('/corbeille/vider', [TypeContratController::class, 'viderCorbeille'])->name('corbeille.vider');
        Route::get('/{typeContrat}/modifier', [TypeContratController::class, 'edit'])->name('edit');
        Route::put('/{typeContrat}', [TypeContratController::class, 'update'])->name('update');
        Route::delete('/{typeContrat}', [TypeContratController::class, 'destroy'])->name('destroy');
        Route::post('/{id}/restaurer', [TypeContratController::class, 'restore'])->name('restore');
        Route::delete('/{id}/definitivement', [TypeContratController::class, 'forceDestroy'])->name('force-destroy');
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

        // Export/import Excel — demandés par le client, voir le docblock de
        // export()/import() ci-dessous. Déclarés AVANT "/{id}" plus bas pour
        // les mêmes raisons que les actions groupées.
        Route::get('/export', [ReferentielController::class, 'export'])->name('export');
        Route::get('/import', [ReferentielController::class, 'formulaireImport'])->name('import.formulaire');
        Route::get('/import/modele', [ReferentielController::class, 'modeleImport'])->name('import.modele');
        Route::post('/import', [ReferentielController::class, 'import'])->name('import');

        // Actions groupées : MÊME PRINCIPE que documents.corbeille-groupee et
        // consorts plus haut — toujours en POST (un navigateur ne peut pas
        // envoyer de corps avec DELETE sur un <form> HTML classique) et
        // déclarées AVANT les routes à "/{id}" ci-dessous, pour que
        // "corbeille-groupee" ne soit jamais pris pour un id de référentiel.
        Route::post('/corbeille-groupee', [ReferentielController::class, 'mettreCorbeilleGroupee'])->name('corbeille-groupee');
        Route::post('/corbeille/restaurer-groupe', [ReferentielController::class, 'restaurerGroupe'])->name('corbeille.restaurer-groupe');
        Route::post('/corbeille/definitivement-groupe', [ReferentielController::class, 'forceDestroyGroupe'])->name('corbeille.force-destroy-groupe');
        Route::delete('/corbeille/vider', [ReferentielController::class, 'viderCorbeille'])->name('corbeille.vider');

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
        // Export Excel — demandé par le client, voir le docblock de export().
        Route::get('/export', [TypeDocumentController::class, 'export'])->name('export');
        // "Vider la corbeille" — déclarée AVANT "/{type}" ci-dessous pour
        // que "corbeille" ne soit jamais pris pour un id/code.
        Route::delete('/corbeille/vider', [TypeDocumentController::class, 'viderCorbeille'])->name('corbeille.vider');
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

    // Champs supplémentaires — documents propres ajoutables aux PV de
    // délibération et/ou aux Relevés de notes depuis l'administration, sans
    // toucher au code (voir le docblock de
    // App\Http\Controllers\ChampSupplementaireController). `{id}` non typé
    // sur restaurer/supprimer définitivement, comme pour les autres
    // référentiels (voir plus haut) : la liaison de route implicite exclut
    // les enregistrements corbeillés par défaut.
    Route::prefix('champs-supplementaires')->name('champs-supplementaires.')->group(function () {
        Route::get('/', [ChampSupplementaireController::class, 'index'])->name('index');
        Route::get('/creer', [ChampSupplementaireController::class, 'create'])->name('create');
        Route::post('/', [ChampSupplementaireController::class, 'store'])->name('store');
        // "Vider la corbeille" — déclarée AVANT "/{champSupplementaire}"
        // ci-dessous pour que "corbeille" ne soit jamais pris pour un id.
        Route::delete('/corbeille/vider', [ChampSupplementaireController::class, 'viderCorbeille'])->name('corbeille.vider');
        Route::get('/{champSupplementaire}/modifier', [ChampSupplementaireController::class, 'edit'])->name('edit');
        Route::put('/{champSupplementaire}', [ChampSupplementaireController::class, 'update'])->name('update');
        Route::delete('/{champSupplementaire}', [ChampSupplementaireController::class, 'destroy'])->name('destroy');
        Route::post('/{id}/restaurer', [ChampSupplementaireController::class, 'restore'])->name('restore');
        Route::delete('/{id}/definitivement', [ChampSupplementaireController::class, 'forceDestroy'])->name('force-destroy');
    });

    // Réglages de la plateforme — une seule ligne en base, réservée en dur au
    // rôle admin (voir le docblock de App\Http\Controllers\ParametresController).
    //
    // {section?}/{section} (plutôt qu'un unique "/") : demande explicite du
    // client ("je ne veux pas que tout soit sur la même page") — chaque
    // section (voir ParametresController::SECTIONS) a sa propre URL, son
    // propre sous-menu et son propre enregistrement.
    Route::prefix('parametres')->name('parametres.')->group(function () {
        Route::get('/{section?}', [ParametresController::class, 'index'])->name('index');
        Route::put('/{section}', [ParametresController::class, 'update'])->name('update');
    });

    // Sauvegardes — fichiers sur le disque `sauvegardes`, pas de modèle
    // Eloquent (voir le docblock de App\Http\Controllers\SauvegardeController).
    Route::prefix('sauvegardes')->name('sauvegardes.')->group(function () {
        Route::get('/', [SauvegardeController::class, 'index'])->name('index');
        Route::post('/', [SauvegardeController::class, 'store'])->name('store');
        Route::delete('/{nom}', [SauvegardeController::class, 'destroy'])->name('destroy');
    });

    // Corbeilles — vue d'ensemble de toutes les corbeilles de l'application
    // (voir le docblock de App\Http\Controllers\CorbeilleGlobaleController).
    Route::prefix('corbeilles')->name('corbeilles.')->group(function () {
        Route::get('/', [CorbeilleGlobaleController::class, 'index'])->name('index');
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
        // Blocage/déblocage et réinitialisation du mot de passe par un
        // administrateur — voir les docblocks de UserController::
        // basculerBlocage() et reinitialiserMotDePasse().
        Route::post('/{utilisateur}/bloquer', [UserController::class, 'basculerBlocage'])->name('bloquer');
        Route::post('/{utilisateur}/reinitialiser-mot-de-passe', [UserController::class, 'reinitialiserMotDePasse'])->name('reinitialiser-mot-de-passe');
    });

    // Annonces — réservé en dur au rôle admin (voir le docblock de
    // App\Http\Controllers\AnnonceController).
    Route::prefix('annonces')->name('annonces.')->group(function () {
        Route::get('/', [AnnonceController::class, 'create'])->name('create');
        Route::post('/', [AnnonceController::class, 'store'])->name('store');
    });
});

/*
|--------------------------------------------------------------------------
| Téléchargement d'un document
|--------------------------------------------------------------------------
|
| Le disque `documents` (voir config/filesystems.php) est privé — aucune
| URL publique ne sert ces fichiers directement. Cette route, protégée par
| le middleware `auth` et par ResourceAccess::can(), est le seul moyen
| d'obtenir le contenu d'un document.
|
| `{document}` est résolu manuellement avec `withTrashed()` plutôt que via
| la liaison de route implicite habituelle (`Document $document`) : celle-ci
| exclut les documents corbeillés par défaut (SoftDeletes), ce qui ferait
| échouer le téléchargement (404) depuis la corbeille — voir le filtre
| "Corbeille" de gestion/documents/index.blade.php.
|
| Route PARTAGÉE par tous les écrans (générale ET dédiés, voir
| gestion/etudiants/_fichier.blade.php, sessions-deliberation/_case.blade.php...) :
| le droit générique "documents" -> "Voir" seul ne suffit PAS pour une pièce
| d'un écran dédié (dossier étudiant, PV de soutenance...) — voir
| App\Support\TypesEcranDedie::ressourcePourCode(), faille corrigée sur
| demande explicite du client ("revoir la sécurité pour éviter
| l'accessibilité à des pages confidentielles sans autorisation") : un rôle
| ayant seulement "documents" (ex. "Gestionnaire", voir
| database/seeders/RoleSeeder.php) pouvait jusqu'ici télécharger n'importe
| quelle pièce d'étudiant ou de soutenance via cette route partagée, sans
| avoir le droit du module correspondant.
*/
Route::middleware(['web', 'auth'])
    ->get('/documents/{document}/telecharger', function (int $document): StreamedResponse {
        abort_unless(ResourceAccess::can('documents', 'view'), 403);

        $document = Document::withTrashed()->with('typeDocument')->findOrFail($document);

        // Une pièce d'un écran dédié (dossier étudiant, PV de soutenance...)
        // exige AUSSI le droit "Voir" du module propriétaire, en plus du
        // droit générique "documents" ci-dessus — voir le docblock de
        // App\Support\TypesEcranDedie::RESSOURCE_PAR_CODE.
        $ressourceDediee = TypesEcranDedie::ressourcePourCode($document->typeDocument?->code);
        abort_unless(! $ressourceDediee || ResourceAccess::can($ressourceDediee, 'view'), 403);

        abort_unless(Storage::disk('documents')->exists($document->chemin), 404);

        return Storage::disk('documents')->download($document->chemin, $document->nom_original);
    })
    ->name('documents.telecharger');

/*
|--------------------------------------------------------------------------
| Aperçu d'un document (ouverture dans un nouvel onglet, sans téléchargement)
|--------------------------------------------------------------------------
|
| Même contrôle d'accès que le téléchargement ci-dessus, mais avec
| "Content-Disposition: inline" (comportement par défaut de
| Storage::response(), contrairement à Storage::download() qui force
| "attachment") — demande explicite du client : pouvoir consulter un PDF/une
| image directement dans le navigateur plutôt que d'avoir à d'abord le
| télécharger. Pour les types que le navigateur ne sait pas afficher
| nativement (.docx, .xlsx, .csv, .txt...), on affiche une page d'aperçu
| (gestion.documents.apercu) qui les rend côté client en JavaScript, sans
| jamais les convertir/stocker sur le serveur. Pour le reste (vieux .doc,
| .ppt...), cette page affiche un message de repli avec un lien de
| téléchargement.
*/
Route::middleware(['web', 'auth'])
    ->get('/documents/{document}/voir', function (int $document) {
        abort_unless(ResourceAccess::can('documents', 'view'), 403);

        $document = Document::withTrashed()->with('typeDocument')->findOrFail($document);

        // Même contrôle supplémentaire que documents.telecharger ci-dessus.
        $ressourceDediee = TypesEcranDedie::ressourcePourCode($document->typeDocument?->code);
        abort_unless(! $ressourceDediee || ResourceAccess::can($ressourceDediee, 'view'), 403);

        abort_unless(Storage::disk('documents')->exists($document->chemin), 404);

        $extension = strtolower($document->extension ?? '');

        $typesNatifs = ['pdf', 'png', 'jpg', 'jpeg', 'webp', 'gif', 'bmp', 'svg'];
        if (in_array($extension, $typesNatifs, true)) {
            return Storage::disk('documents')->response($document->chemin, $document->nom_original);
        }

        $typeApercu = match (true) {
            in_array($extension, ['xlsx', 'xls', 'csv'], true) => 'tableur',
            $extension === 'docx' => 'docx',
            $extension === 'txt' => 'texte',
            default => 'indisponible',
        };

        return view('gestion.documents.apercu', [
            'document' => $document,
            'typeApercu' => $typeApercu,
            'extension' => $extension,
        ]);
    })
    ->name('documents.voir');

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
