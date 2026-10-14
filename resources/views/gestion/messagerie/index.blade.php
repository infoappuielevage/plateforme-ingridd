{{--
    Messagerie — remplace l'ancien écran "Annonces" (demande explicite du
    client : "améliore la gestion des annonces pour que ça soit comme une
    messagerie"). Voir App\Http\Controllers\MessagerieController et
    App\Models\Conversation pour le fonctionnement complet.

    Un seul gabarit pour les trois cas :
    - Administrateur sans conversation choisie (MessagerieController::index()) :
      colonne de gauche remplie, colonne de droite en état vide.
    - Administrateur avec une conversation ouverte (::afficher()) : les deux
      colonnes sont remplies.
    - Utilisateur non-admin (::afficher(), toujours SA conversation) : pas de
      colonne de gauche (il n'en a qu'une), uniquement le fil à droite.

    Variables attendues : $conversations (vide pour un non-admin),
    $conversationActive (nullable), $messages (collection, vide si aucune
    conversation active).
--}}
@extends('layouts.gestion')

@section('title', 'Messagerie')

@section('content')
@php
    $utilisateurConnecte = auth()->user();
    $estAdmin = $utilisateurConnecte->role === \App\Models\User::ROLE_ADMIN;
@endphp

<div class="page-header-hero d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h3 mb-0">Messagerie</h1>
        <p class="text-muted small mb-0">
            @if ($estAdmin)
                Vos conversations avec les utilisateurs de la plateforme.
            @else
                Vos échanges avec l'administration.
            @endif
        </p>
    </div>
    @if ($estAdmin)
        <a href="{{ route('gestion.messagerie.nouveau') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Nouveau message
        </a>
    @endif
</div>

<div class="messagerie-shell {{ $conversationActive ? 'a-une-conversation-active' : '' }}">
    @if ($estAdmin)
        <div class="messagerie-liste">
            @forelse ($conversations as $conversation)
                @php
                    $nonLue = $conversation->estNonLuePour($utilisateurConnecte);
                    $dernier = $conversation->dernierMessage;
                    $nomInterlocuteur = $conversation->utilisateur->name ?? 'Utilisateur supprimé';
                @endphp
                <a href="{{ route('gestion.messagerie.afficher', $conversation) }}"
                   class="messagerie-conversation-item {{ $conversationActive && $conversationActive->id === $conversation->id ? 'active' : '' }} {{ $nonLue ? 'non-lue' : '' }}">
                    <span class="messagerie-avatar">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($nomInterlocuteur, 0, 1)) }}</span>
                    <span class="messagerie-conversation-infos">
                        <span class="messagerie-conversation-nom d-block">{{ $nomInterlocuteur }}</span>
                        <span class="messagerie-conversation-apercu d-block">
                            @if ($dernier)
                                @if ($dernier->titre)<strong>{{ $dernier->titre }}</strong> — @endif{{ \Illuminate\Support\Str::limit($dernier->contenu, 44) }}
                            @endif
                        </span>
                    </span>
                    <span class="messagerie-conversation-meta">
                        <span class="messagerie-conversation-heure">{{ $dernier?->created_at?->diffForHumans(null, true) }}</span>
                        @if ($nonLue)<span class="messagerie-point-non-lu" title="Non lu"></span>@endif
                    </span>
                </a>
            @empty
                <div class="empty-state text-center py-5 px-3">
                    <i class="bi bi-chat-dots fs-1 text-muted"></i>
                    <p class="text-muted mb-0 small">Aucune conversation pour l'instant.</p>
                </div>
            @endforelse
        </div>
    @endif

    <div class="messagerie-fil">
        @if ($conversationActive)
            @php $nomInterlocuteurActif = $conversationActive->utilisateur->name ?? 'Utilisateur supprimé'; @endphp
            <div class="messagerie-fil-entete">
                @if ($estAdmin)
                    <a href="{{ route('gestion.messagerie.index') }}" class="btn btn-sm btn-outline-secondary d-md-none" title="Retour à la liste">
                        <i class="bi bi-arrow-left"></i>
                    </a>
                    <span class="messagerie-avatar">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($nomInterlocuteurActif, 0, 1)) }}</span>
                    <strong>{{ $nomInterlocuteurActif }}</strong>
                @else
                    <i class="bi bi-building fs-5"></i>
                    <strong>Administration</strong>
                @endif
            </div>

            <div class="messagerie-fil-corps"
                 id="messagerie-fil-corps"
                 data-url-recents="{{ route('gestion.messagerie.messages-recents', $conversationActive) }}"
                 data-dernier-id="{{ $messages->last()->id ?? 0 }}">
                @if ($messages->isEmpty())
                    <div class="text-center text-muted small py-4">
                        Aucun message pour l'instant.
                        @unless ($estAdmin)<br>Un message de l'administration apparaîtra ici.@endunless
                    </div>
                @else
                    @include('gestion.messagerie.partials.messages', ['messages' => $messages, 'utilisateurConnecte' => $utilisateurConnecte])
                @endif
            </div>

            <div class="messagerie-fil-pied">
                @error('contenu')
                    <div class="text-danger small mb-2">{{ $message }}</div>
                @enderror
                <form method="POST" action="{{ route('gestion.messagerie.repondre', $conversationActive) }}" id="messagerie-form-reponse" class="d-flex gap-2 align-items-end">
                    @csrf
                    <textarea name="contenu" class="form-control" rows="1" maxlength="2000" placeholder="Écrire un message…" required></textarea>
                    <button type="submit" class="btn btn-primary flex-shrink-0" title="Envoyer">
                        <i class="bi bi-send-fill"></i>
                    </button>
                </form>
            </div>
        @else
            <div class="d-flex align-items-center justify-content-center flex-fill">
                <div class="empty-state text-center py-5">
                    <i class="bi bi-chat-square-text fs-1 text-muted"></i>
                    <p class="text-muted mb-0">Sélectionnez une conversation pour l'afficher.</p>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection

@if ($conversationActive)
    @section('scripts')
    <script>
        (function () {
            const corps = document.getElementById('messagerie-fil-corps');
            const form = document.getElementById('messagerie-form-reponse');
            if (!corps) return;

            corps.scrollTop = corps.scrollHeight;

            const urlRecents = corps.getAttribute('data-url-recents');

            function recupererNouveaux() {
                const apresId = corps.getAttribute('data-dernier-id') || 0;

                fetch(urlRecents + '?apres_id=' + encodeURIComponent(apresId), {
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                })
                    .then(function (reponse) { return reponse.ok ? reponse.json() : null; })
                    .then(function (donnees) {
                        if (!donnees || !donnees.html) return;

                        corps.insertAdjacentHTML('beforeend', donnees.html);
                        corps.setAttribute('data-dernier-id', donnees.dernier_id);
                        corps.scrollTop = corps.scrollHeight;
                    })
                    .catch(function (erreur) { console.error(erreur); });
            }

            // Sondage périodique plutôt qu'une connexion temps réel
            // (websocket/Pusher) : pas d'infrastructure de ce type sur
            // l'hébergement du client (XAMPP/Windows classique), voir le
            // docblock du contrôleur. 8 secondes = compromis entre
            // réactivité perçue et charge serveur pour une petite équipe.
            const intervalle = setInterval(recupererNouveaux, 8000);
            window.addEventListener('beforeunload', function () { clearInterval(intervalle); });

            if (form) {
                const champ = form.querySelector('textarea[name="contenu"]');
                if (champ) {
                    champ.addEventListener('keydown', function (evenement) {
                        if (evenement.key === 'Enter' && !evenement.shiftKey) {
                            evenement.preventDefault();
                            form.requestSubmit();
                        }
                    });
                }
            }
        })();
    </script>
    @endsection
@endif
