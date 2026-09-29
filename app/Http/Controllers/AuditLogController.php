<?php

namespace App\Http\Controllers;

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

        $entrees = AuditLog::query()
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->string('action')))
            ->when($request->filled('recherche'), function ($q) use ($request) {
                $terme = '%'.$request->string('recherche').'%';
                $q->where(function ($q) use ($terme) {
                    $q->where('user_name', 'like', $terme)->orWhere('resume', 'like', $terme);
                });
            })
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();

        return view('gestion.historique.index', [
            'entrees' => $entrees,
            'actions' => self::ACTIONS,
        ]);
    }
}
