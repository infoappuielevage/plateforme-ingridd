{{--
    Messagerie — utilisateur non-admin SANS conversation pour l'instant (voir
    MessagerieController::index()) : personne ne peut lui écrire en premier
    hormis un administrateur (voir le docblock du contrôleur), donc rien à
    afficher tant qu'aucune annonce/message ne lui a été envoyé.
--}}
@extends('layouts.gestion')

@section('title', 'Messagerie')

@section('content')
<div class="page-header-hero d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h3 mb-0">Messagerie</h1>
        <p class="text-muted small mb-0">Vos échanges avec l'administration.</p>
    </div>
</div>

<div class="card shadow-sm">
    <div class="empty-state text-center py-5">
        <i class="bi bi-chat-square-text fs-1 text-muted"></i>
        <p class="text-muted mb-0">Aucune conversation pour l'instant.</p>
        <p class="text-muted small mb-0">Un message de l'administration apparaîtra ici.</p>
    </div>
</div>
@endsection
