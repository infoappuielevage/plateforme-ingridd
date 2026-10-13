{{--
    Partiel d'une case fixe (bordereau OU fichier Excel) — même principe que
    resources/views/gestion/recettes/scolarite/_case-fixe.blade.php.
    Variables attendues : $document (App\Models\Document, ou null si la case
    est vide), $depense, $typeDepense, $champ ('bordereau'|'excel'),
    $peutModifier, $peutSupprimer, $tailleMaxMo.
--}}
@php
    $routeStore = $champ === 'bordereau' ? 'gestion.depenses.bordereau.store' : 'gestion.depenses.excel.store';
    $routeDestroy = $champ === 'bordereau' ? 'gestion.depenses.bordereau.destroy' : 'gestion.depenses.excel.destroy';
    $parametresRoute = ['typeDepense' => $typeDepense->code, 'depense' => $depense->id];
@endphp
@if ($document)
    <div class="d-flex flex-wrap align-items-center gap-2">
        <a href="{{ route('documents.voir', $document->id) }}" target="_blank" rel="noopener" class="text-decoration-none" title="Voir dans un nouvel onglet">
            <i class="bi bi-file-earmark-text"></i>
            {{ $document->nom_original }}
        </a>
        <span class="text-muted small">({{ $document->tailleFormatee() }})</span>
        <a href="{{ route('documents.telecharger', $document->id) }}" class="btn btn-sm btn-outline-secondary" title="Télécharger">
            <i class="bi bi-download"></i>
        </a>
        @include('gestion.partials._lien_historique', ['type' => \App\Models\Document::class, 'id' => $document->id])
        @unless ($document->estVerrouille())
            @if ($peutModifier)
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#remplacer{{ $champ }}{{ $depense->id }}">
                    Remplacer
                </button>
            @endif
            @if ($peutSupprimer)
                <form method="POST" action="{{ route($routeDestroy, $parametresRoute) }}"
                      onsubmit="return confirm('Retirer ce fichier ? La case restera disponible pour un nouveau dépôt.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Retirer">
                        <i class="bi bi-trash3"></i>
                    </button>
                </form>
            @endif
        @endunless
    </div>
    @if ($peutModifier && ! $document->estVerrouille())
        <div class="collapse mt-2" id="remplacer{{ $champ }}{{ $depense->id }}">
            <form method="POST" action="{{ route($routeStore, $parametresRoute) }}" enctype="multipart/form-data" class="d-flex gap-2">
                @csrf
                <input type="file" name="fichier" class="form-control form-control-sm" required>
                <button type="submit" class="btn btn-sm btn-primary text-nowrap">Remplacer</button>
            </form>
        </div>
    @endif
@else
    @if ($peutModifier)
        <form method="POST" action="{{ route($routeStore, $parametresRoute) }}" enctype="multipart/form-data" class="d-flex gap-2">
            @csrf
            <input type="file" name="fichier" class="form-control form-control-sm" required>
            <button type="submit" class="btn btn-sm btn-primary text-nowrap">
                <i class="bi bi-upload"></i> Déposer
            </button>
        </form>
        <div class="form-text">Taille maximale : {{ $tailleMaxMo }} Mo.</div>
    @else
        <span class="text-muted small">Aucun fichier déposé.</span>
    @endif
@endif
