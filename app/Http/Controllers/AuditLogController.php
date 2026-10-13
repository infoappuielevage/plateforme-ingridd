<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ListeTriable;
use App\Models\AuditLog;
use App\Support\ResourceAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Historique (audit) — remplace App\Filament\Resources\AuditLogResource sur
 * demande du client (plus de Filament). Lecture seule pour tout le monde,
 * comme la Resource Filament d'origine (voir son docblock) : aucune route
 * create/edit/delete n'existe ici, volontairement — la purge automatique des
 * entrées trop anciennes reste réservée à la commande artisan planifiée
 * (voir routes/console.php).
 *
 * Contrairement à Rôles/Utilisateurs/Paramètres/Sauvegardes, cet écran PASSE
 * par la matrice App\Support\ResourceAccess (clé "audit_logs") plutôt que
 * d'être réservé en dur à l'admin : la Resource Filament d'origine faisait de
 * même (HasRoleBasedAccess), un rôle peut donc se voir accorder ce droit de
 * lecture sans devenir admin pour autant.
 */
class AuditLogController extends Controller
{
    use ListeTriable;

    protected const ACTIONS = [
        'creation' => 'Création',
        'modification' => 'Modification',
        'suppression' => 'Suppression définitive',
        'mise_en_corbeille' => 'Mise à la corbeille',
        'restauration' => 'Restauration',
        'connexion' => 'Connexion',
        'deconnexion' => 'Déconnexion',
        'connexion_echouee' => 'Connexion échouée',
        'connexion_verrouillee' => 'Connexion verrouillée',
    ];

    public function index(Request $request): View
    {
        abort_unless(ResourceAccess::can('audit_logs', 'view'), 403);

        $colonnesTriables = $this->colonnesTriables();
        $triResolu = $this->resoudreTri($request, $colonnesTriables, 'created_at', 'desc');

        // `model_type`/`model_id` : filtre optionnel vers l'historique d'UN
        // SEUL enregistrement (document, dépense, dossier de recette...),
        // demande explicite du client ("raccourci vers l'historique de ce
        // document" plutôt que de devoir chercher dans l'historique
        // global) — voir resources/views/gestion/partials/_lien_historique.blade.php,
        // inclus directement sur les fiches concernées. Ignoré silencieusement
        // si model_type n'est pas une classe de modèle valide (URL modifiée
        // à la main) : on retombe simplement sur l'historique complet.
        $modeleFiltre = $request->filled('model_type') && $request->filled('model_id') && class_exists($request->string('model_type')->toString())
            ? $request->string('model_type')->toString()
            : null;

        $requete = AuditLog::query()
            ->when($modeleFiltre, fn ($q) => $q->where('model_type', $modeleFiltre)->where('model_id', $request->integer('model_id')))
            ->when(! $modeleFiltre && $request->filled('action'), fn ($q) => $q->where('action', $request->string('action')))
            ->when(! $modeleFiltre && $request->filled('recherche'), function ($q) use ($request) {
                $terme = '%'.$request->string('recherche').'%';
                $q->where(function ($q) use ($terme) {
                    $q->where('user_name', 'like', $terme)->orWhere('resume', 'like', $terme);
                });
            });
        $this->appliquerTri($requete, $colonnesTriables, $triResolu);

        $entrees = $requete->paginate(8)->withQueryString();

        $donnees = [
            'entrees' => $entrees,
            'actions' => self::ACTIONS,
            'triActuel' => $triResolu[0],
            'directionActuelle' => $triResolu[1],
            // Libellé affiché en en-tête quand on arrive filtré sur un seul
            // enregistrement (voir ci-dessus) — class_basename() donne un nom
            // lisible ("Document", "Depense"...) sans exposer le namespace complet.
            'filtreModeleLibelle' => $modeleFiltre ? class_basename($modeleFiltre).' #'.$request->integer('model_id') : null,
        ];

        // `fragment=1` : ne renvoie QUE le tableau de résultats (pas toute la
        // page, pas le layout) — demande explicite du client ("rester sur la
        // même page pour voir l'historique d'un document" plutôt que d'être
        // renvoyé vers l'écran Historique). Utilisé par le raccourci "Voir
        // l'historique" (resources/views/gestion/partials/_lien_historique.blade.php),
        // qui charge ce fragment en arrière-plan (fetch) dans une fenêtre
        // modale partagée (voir resources/views/layouts/gestion.blade.php) au
        // lieu de naviguer vers gestion.historique.index.
        if ($request->boolean('fragment')) {
            return view('gestion.historique._tableau', $donnees);
        }

        return view('gestion.historique.index', $donnees);
    }

    /**
     * Colonnes triables de la liste (voir App\Http\Controllers\Concerns\ListeTriable)
     * — le tri par défaut (created_at desc, le plus récent en premier) est
     * CONSERVÉ à l'identique, c'est le seul écran de l'application où ce
     * choix a un sens métier fort (historique = journal chronologique).
     * "user_name" est une colonne dénormalisée (pas une relation vers
     * App\Models\User — voir le docblock d'App\Models\AuditLog), donc un
     * orderBy() direct suffit, comme pour les autres colonnes.
     *
     * @return array<string, string>
     */
    protected function colonnesTriables(): array
    {
        return [
            'created_at' => 'created_at',
            'user_name' => 'user_name',
            'action' => 'action',
            'resume' => 'resume',
        ];
    }
}
