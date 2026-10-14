<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\ConversationLecture;
use App\Models\Role;
use App\Models\User;
use App\Notifications\AnnonceAdministrateurNotification;
use App\Notifications\NouveauMessageNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

/**
 * Administration > Messagerie — remplace l'ancien écran "Annonces" (sur
 * demande explicite du client : "améliore la gestion des annonces pour que
 * ça soit comme une messagerie"). Voir App\Models\Conversation pour le
 * fonctionnement détaillé du modèle de données.
 *
 * Principe retenu (gardé volontairement simple malgré l'UI "façon chat") :
 * chaque utilisateur non-admin a AU PLUS une conversation, avec
 * "l'institution" comme interlocuteur collectif — n'importe quel compte
 * admin peut la voir et y répondre (boîte de réception PARTAGÉE entre tous
 * les administrateurs, comme une messagerie de support classique), mais
 * seul un administrateur peut EN CRÉER une nouvelle (voir nouveau()/
 * envoyerNouveau(), qui reprennent exactement la logique de diffusion de
 * l'ancien AnnonceController : tout le monde / un rôle / un utilisateur
 * précis). Un utilisateur non-admin ne peut donc jamais écrire en premier ni
 * démarrer une conversation avec un autre utilisateur non-admin — seulement
 * répondre dans SA PROPRE conversation.
 *
 * Toujours aucun modèle de restauration/import ici : voir le reste du
 * contrôleur, uniquement des créations (nouveau message, réponse) et de la
 * lecture.
 */
class MessagerieController extends Controller
{
    public function index(): View|RedirectResponse
    {
        $utilisateur = Auth::user();

        if ($utilisateur->role === User::ROLE_ADMIN) {
            $conversations = $this->conversationsPourBoiteReception($utilisateur);

            return view('gestion.messagerie.index', [
                'conversations' => $conversations,
                'conversationActive' => null,
                'messages' => collect(),
            ]);
        }

        $conversation = Conversation::query()->where('utilisateur_id', $utilisateur->id)->first();

        if (! $conversation) {
            return view('gestion.messagerie.vide');
        }

        return redirect()->route('gestion.messagerie.afficher', $conversation);
    }

    public function afficher(Conversation $conversation): View
    {
        $utilisateur = Auth::user();
        $this->autoriserAcces($conversation, $utilisateur);

        // orderBy('id') explicite ici : voir le docblock de
        // Conversation::messages() — la relation elle-même n'impose plus
        // aucun tri, chaque appelant précise le sien (ici, ordre
        // chronologique d'affichage du fil).
        $conversation->load([
            'messages' => fn ($requete) => $requete->orderBy('id')->with('auteur'),
            'utilisateur',
        ]);
        $this->marquerCommeLue($conversation, $utilisateur);

        $conversations = $utilisateur->role === User::ROLE_ADMIN
            ? $this->conversationsPourBoiteReception($utilisateur)
            : collect();

        return view('gestion.messagerie.index', [
            'conversations' => $conversations,
            'conversationActive' => $conversation,
            'messages' => $conversation->messages,
        ]);
    }

    public function nouveau(): View
    {
        abort_unless(Auth::user()?->role === User::ROLE_ADMIN, 403);

        return view('gestion.messagerie.nouveau', [
            'roles' => Role::query()->orderBy('name')->pluck('name', 'slug'),
            'utilisateurs' => User::query()->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function envoyerNouveau(Request $request): RedirectResponse
    {
        $admin = Auth::user();
        abort_unless($admin?->role === User::ROLE_ADMIN, 403);

        $donnees = $request->validate([
            'destinataire_type' => ['required', Rule::in(['tous', 'role', 'utilisateur'])],
            'role' => ['required_if:destinataire_type,role', 'nullable', Rule::exists('roles', 'slug')],
            'utilisateur_id' => ['required_if:destinataire_type,utilisateur', 'nullable', Rule::exists('users', 'id')],
            'titre' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $destinataires = match ($donnees['destinataire_type']) {
            'tous' => User::query()->get(),
            'role' => User::query()->where('role', $donnees['role'])->get(),
            'utilisateur' => User::query()->where('id', $donnees['utilisateur_id'])->get(),
        };

        if ($destinataires->isEmpty()) {
            return back()->withErrors(['destinataire_type' => "Aucun destinataire ne correspond à ce choix."])->withInput();
        }

        foreach ($destinataires as $destinataire) {
            // firstOrCreate() plutôt que create() : si une conversation
            // existe déjà avec ce destinataire (annonce précédente, réponse
            // de sa part...), le nouveau message vient s'y ajouter au lieu
            // d'en ouvrir une seconde — voir le docblock de App\Models\Conversation.
            $conversation = Conversation::query()->firstOrCreate(['utilisateur_id' => $destinataire->id]);

            $message = $conversation->messages()->create([
                'auteur_id' => $admin->id,
                'titre' => $donnees['titre'],
                'contenu' => $donnees['message'],
            ]);

            $conversation->update(['dernier_message_a' => $message->created_at]);

            $destinataire->notify(new AnnonceAdministrateurNotification(
                $donnees['titre'],
                $donnees['message'],
                route('gestion.messagerie.afficher', $conversation)
            ));
        }

        $nombre = $destinataires->count();
        $libelleDestinataires = $nombre > 1 ? "{$nombre} utilisateurs" : '1 utilisateur';

        return redirect()->route('gestion.messagerie.index')->with('succes', "Message envoyé à {$libelleDestinataires}.");
    }

    public function repondre(Request $request, Conversation $conversation): RedirectResponse
    {
        $utilisateur = Auth::user();
        $this->autoriserAcces($conversation, $utilisateur);

        $donnees = $request->validate([
            'contenu' => ['required', 'string', 'max:2000'],
        ]);

        $message = $conversation->messages()->create([
            'auteur_id' => $utilisateur->id,
            'contenu' => $donnees['contenu'],
        ]);

        $conversation->update(['dernier_message_a' => $message->created_at]);
        $this->marquerCommeLue($conversation, $utilisateur);

        // Qui notifier côté "autre participant" : si c'est un admin qui
        // vient d'écrire, on prévient l'utilisateur concerné ; si c'est
        // l'utilisateur, on prévient TOUS les administrateurs (boîte de
        // réception partagée, voir le docblock de la classe).
        $destinataires = $utilisateur->role === User::ROLE_ADMIN
            ? User::query()->where('id', $conversation->utilisateur_id)->get()
            : User::query()->where('role', User::ROLE_ADMIN)->get();

        Notification::send($destinataires, new NouveauMessageNotification($message));

        return redirect()->route('gestion.messagerie.afficher', $conversation);
    }

    /**
     * Récupère les messages postés après `apres_id` (sondage périodique côté
     * navigateur, voir le script dans resources/views/gestion/messagerie/
     * index.blade.php) et renvoie directement le HTML des bulles déjà
     * formatées — même principe que le chargement de l'historique dans
     * layouts/gestion.blade.php (fetch() + innerHTML), pas d'API JSON à
     * interpréter côté client.
     */
    public function messagesRecents(Request $request, Conversation $conversation): JsonResponse
    {
        $utilisateur = Auth::user();
        $this->autoriserAcces($conversation, $utilisateur);

        $apresId = (int) $request->query('apres_id', 0);

        $nouveaux = $conversation->messages()
            ->with('auteur')
            ->where('id', '>', $apresId)
            ->orderBy('id')
            ->get();

        if ($nouveaux->isNotEmpty()) {
            $this->marquerCommeLue($conversation, $utilisateur);
        }

        return response()->json([
            'dernier_id' => $nouveaux->last()->id ?? $apresId,
            'html' => view('gestion.messagerie.partials.messages', [
                'messages' => $nouveaux,
                'utilisateurConnecte' => $utilisateur,
            ])->render(),
        ]);
    }

    /** Nombre de conversations non lues pour la cloche/le badge de la sidebar — voir layouts/gestion.blade.php. */
    public function nombreNonLus(): JsonResponse
    {
        $utilisateur = Auth::user();

        if ($utilisateur->role === User::ROLE_ADMIN) {
            $nombre = $this->conversationsPourBoiteReception($utilisateur)
                ->filter(fn (Conversation $conversation) => $conversation->estNonLuePour($utilisateur))
                ->count();
        } else {
            $conversation = Conversation::query()
                ->with(['dernierMessage', 'lectures' => fn ($q) => $q->where('user_id', $utilisateur->id)])
                ->where('utilisateur_id', $utilisateur->id)
                ->first();

            $nombre = $conversation && $conversation->estNonLuePour($utilisateur) ? 1 : 0;
        }

        return response()->json(['nombre' => $nombre]);
    }

    private function conversationsPourBoiteReception(User $admin)
    {
        return Conversation::query()
            ->whereNotNull('dernier_message_a')
            ->with([
                'utilisateur',
                'dernierMessage',
                'lectures' => fn ($q) => $q->where('user_id', $admin->id),
            ])
            ->orderByDesc('dernier_message_a')
            ->get();
    }

    private function marquerCommeLue(Conversation $conversation, User $utilisateur): void
    {
        ConversationLecture::query()->updateOrCreate(
            ['conversation_id' => $conversation->id, 'user_id' => $utilisateur->id],
            ['lu_jusqua' => now()]
        );
    }

    /** Un administrateur accède à toutes les conversations ; un utilisateur non-admin seulement à la sienne. */
    private function autoriserAcces(Conversation $conversation, User $utilisateur): void
    {
        abort_unless(
            $utilisateur->role === User::ROLE_ADMIN || $conversation->utilisateur_id === $utilisateur->id,
            403
        );
    }
}
