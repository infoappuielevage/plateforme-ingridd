<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;

/**
 * "Mes notifications" — cloche de la barre de navigation (voir layouts/
 * gestion.blade.php, qui affiche déjà les 8 plus récentes et le nombre non
 * lues) et cet écran, qui liste TOUTES les notifications de la personne
 * connectée, paginées comme le reste de l'application (voir le commentaire
 * équivalent dans UserController::index()).
 *
 * Volontairement réservé à SES PROPRES notifications : aucune route
 * n'accepte un identifiant de notification appartenant à quelqu'un d'autre
 * (voir le abort_unless dans marquerCommeLue()) — même un administrateur ne
 * doit jamais pouvoir lire/marquer comme lue la notification d'un autre
 * compte depuis cet écran.
 */
class NotificationController extends Controller
{
    public function index(): View
    {
        return view('gestion.notifications.index', [
            // paginate(8) plutôt que get() : demande explicite du client
            // (voir le commentaire équivalent dans UserController::index()).
            'notifications' => Auth::user()->notifications()->paginate(8),
        ]);
    }

    public function marquerCommeLue(DatabaseNotification $notification): RedirectResponse
    {
        abort_unless(
            $notification->notifiable_type === User::class && $notification->notifiable_id === Auth::id(),
            403
        );

        $notification->markAsRead();

        // Si la notification porte un lien utile (ex. vers Administration >
        // Sauvegardes pour une alerte de sauvegarde), on y envoie
        // directement plutôt que de renvoyer simplement vers la page
        // précédente — évite un clic supplémentaire.
        $url = $notification->data['url'] ?? null;

        return $url ? redirect($url) : redirect()->back();
    }

    public function toutMarquerCommeLue(): RedirectResponse
    {
        Auth::user()->unreadNotifications->markAsRead();

        return redirect()->back();
    }
}
