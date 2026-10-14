{{--
    Bulles d'une liste de messages — partagée entre l'affichage initial du
    fil (gestion/messagerie/index.blade.php) et le fragment HTML renvoyé par
    le sondage périodique (MessagerieController::messagesRecents(), voir le
    script en bas de index.blade.php qui l'injecte via innerHTML).

    Variables attendues : $messages (collection de App\Models\Message, avec
    `auteur` chargée), $utilisateurConnecte.

    "mine" (bulle à droite, en bleu) ne dépend pas de l'identité exacte de
    l'auteur mais de son CÔTÔ : pour un administrateur qui consulte, tous les
    messages envoyés par N'IMPORTE QUEL administrateur sont "les siens" (côté
    institution, boîte de réception partagée — voir le docblock de
    MessagerieController) ; pour l'utilisateur non-admin, seuls SES PROPRES
    messages le sont. Même conversation, donc, mais pas la même bulle "à
    droite" selon qui regarde — comme dans une vraie messagerie de support.
--}}
@foreach ($messages as $message)
    @php
        $coteInstitution = $message->auteur && $message->auteur->role === \App\Models\User::ROLE_ADMIN;
        $estMien = $utilisateurConnecte->role === \App\Models\User::ROLE_ADMIN
            ? $coteInstitution
            : $message->auteur_id === $utilisateurConnecte->id;
    @endphp
    <div class="msg-rangee {{ $estMien ? 'mine' : '' }}" data-message-id="{{ $message->id }}">
        <div class="msg-bloc">
            @unless ($estMien)
                <div class="msg-auteur">{{ $message->nomAuteur() }}</div>
            @endunless
            <div class="msg-bulle">
                @if ($message->titre)
                    <span class="msg-titre">{{ $message->titre }}</span>
                @endif
                {{ $message->contenu }}
                <span class="msg-heure">{{ $message->created_at->diffForHumans() }}</span>
            </div>
        </div>
    </div>
@endforeach
