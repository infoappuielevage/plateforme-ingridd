@extends('layouts.gestion')

@section('title', 'Sauvegardes')

@section('content')
<div class="page-header-hero d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0">Sauvegardes</h1>
    <div class="d-flex flex-wrap gap-2">
        <form method="POST" action="{{ route('gestion.sauvegardes.store') }}" onsubmit="return confirm('Créer une nouvelle sauvegarde (base de données + documents) maintenant ? Cette opération peut prendre quelques instants.');">
            @csrf
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-plus-lg"></i> Créer une sauvegarde
            </button>
        </form>
        <form method="POST" action="{{ route('gestion.sauvegardes.store-projet') }}" onsubmit="return confirm('Créer une sauvegarde du projet (code source) maintenant ? Cette opération peut prendre quelques instants.');">
            @csrf
            <button type="submit" class="btn btn-outline-secondary">
                <i class="bi bi-file-earmark-zip"></i> Sauvegarde du projet
            </button>
        </form>
    </div>
</div>

@if ($errors->any())
    <div class="alert alert-danger">
        @foreach ($errors->all() as $erreur)
            <div>{{ $erreur }}</div>
        @endforeach
    </div>
@endif

<div class="alert alert-info">
    <i class="bi bi-info-circle"></i>
    Une sauvegarde <strong>« Créer une sauvegarde »</strong> contient la base de données ET tous les documents déposés sur la plateforme, dans une seule archive .zip.
    <strong>Téléchargez-la régulièrement et conservez-la ailleurs</strong> (votre ordinateur, une clé USB, un espace de stockage externe...) :
    en cas de problème sur l'hébergement, seule une copie gardée hors de ce serveur permet de ne pas perdre vos documents.
    La fréquence de création automatique se règle dans
    <a href="{{ route('gestion.parametres.index') }}">Administration &gt; Paramètres</a>.
</div>

<div class="alert alert-secondary">
    <i class="bi bi-file-earmark-zip"></i>
    Une sauvegarde <strong>« Sauvegarde du projet »</strong> contient le CODE de l'application (sans la base de données ni les documents, déjà couverts par l'autre type ci-dessus) :
    utile pour garder une copie de l'application telle qu'elle tourne aujourd'hui, ou la redéployer ailleurs.
    Le fichier <code>.env</code> (mots de passe, clé de chiffrement) en est volontairement exclu pour des raisons de sécurité — à conserver séparément, en lieu sûr.
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Nom du fichier</th>
                    <th>Type</th>
                    <th>Taille</th>
                    <th>Créée le</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($sauvegardes as $sauvegarde)
                    <tr>
                        <td><code>{{ $sauvegarde['nom'] }}</code></td>
                        <td>
                            @if ($sauvegarde['type'] === 'projet')
                                <span class="badge text-bg-secondary"><i class="bi bi-file-earmark-zip"></i> Projet</span>
                            @else
                                <span class="badge text-bg-info"><i class="bi bi-database"></i> Données</span>
                            @endif
                        </td>
                        <td class="text-muted small">{{ $sauvegarde['taille'] }}</td>
                        <td class="text-muted small">{{ $sauvegarde['date'] }}</td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('sauvegardes.telecharger', $sauvegarde['nom']) }}" class="btn btn-outline-secondary" title="Télécharger">
                                    <i class="bi bi-download"></i>
                                </a>
                                <form method="POST" action="{{ route('gestion.sauvegardes.destroy', $sauvegarde['nom']) }}" class="d-inline"
                                      onsubmit="return confirm('Supprimer définitivement cette sauvegarde ? Cette action est irréversible.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger" title="Supprimer">
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">
                            <div class="empty-state text-center py-5">
                                <i class="bi bi-archive fs-1 text-muted"></i>
                                <p class="text-muted mb-0">Aucune sauvegarde pour l'instant.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($sauvegardes->hasPages())
        <div class="card-footer bg-transparent">
            {{ $sauvegardes->links('pagination::bootstrap-5') }}
        </div>
    @endif
</div>
@endsection
