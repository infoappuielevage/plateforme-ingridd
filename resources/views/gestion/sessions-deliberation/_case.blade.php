{{--
    Partiel d'une case (voir show.blade.php) : soit le fichier déjà déposé
    (lien de téléchargement + retrait), soit un petit formulaire de dépôt.
    Variables attendues : $fichier (App\Models\SessionDeliberationFichier,
    avec sa relation "document" déjà chargée), $session, $peutModifier,
    $peutSupprimer.
--}}
@if ($fichier->estRemplie())
    <div class="d-flex flex-wrap align-items-center gap-2">
        {{-- Le nom ouvre un APERÇU dans un nouvel onglet ("voir", Content-Disposition: inline —
             voir routes/web.php) ; le bouton à côté force un vrai téléchargement.
             Demande explicite du client : pouvoir consulter un PDF/une image sans
             d'abord le télécharger. --}}
        <a href="{{ route('documents.voir', $fichier->document_id) }}" target="_blank" rel="noopener" class="text-decoration-none" title="Voir dans un nouvel onglet">
            <i class="bi bi-file-earmark-text"></i>
            {{ $fichier->document->nom_original }}
        </a>
        <span class="text-muted small">({{ $fichier->document->tailleFormatee() }})</span>
        <a href="{{ route('documents.telecharger', $fichier->document_id) }}" class="btn btn-sm btn-outline-secondary" title="Télécharger">
            <i class="bi bi-download"></i>
        </a>
        @include('gestion.partials._lien_historique', ['type' => \App\Models\Document::class, 'id' => $fichier->document_id])
        @unless ($fichier->document->estVerrouille())
            @if ($peutModifier)
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#remplacer{{ $fichier->id }}">
                    Remplacer
                </button>
            @endif
            @if ($peutSupprimer)
                <form method="POST" action="{{ route('gestion.sessions-deliberation.fichiers.destroy', ['sessionDeliberation' => $session->id, 'fichier' => $fichier->id]) }}"
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
    @if ($peutModifier && ! $fichier->document->estVerrouille())
        <div class="collapse mt-2" id="remplacer{{ $fichier->id }}">
            <form method="POST" action="{{ route('gestion.sessions-deliberation.fichiers.store', ['sessionDeliberation' => $session->id, 'fichier' => $fichier->id]) }}" enctype="multipart/form-data" class="d-flex gap-2">
                @csrf
                <input type="file" name="fichier" class="form-control form-control-sm" required>
                <button type="submit" class="btn btn-sm btn-primary text-nowrap">Remplacer</button>
            </form>
        </div>
    @endif
@else
    @if ($peutModifier)
        <form method="POST" action="{{ route('gestion.sessions-deliberation.fichiers.store', ['sessionDeliberation' => $session->id, 'fichier' => $fichier->id]) }}" enctype="multipart/form-data" class="d-flex gap-2">
            @csrf
            <input type="file" name="fichier" class="form-control form-control-sm" required>
            <button type="submit" class="btn btn-sm btn-primary text-nowrap">
                <i class="bi bi-upload"></i> Déposer
            </button>
        </form>
    @else
        <span class="text-muted small">Aucun fichier déposé.</span>
    @endif
@endif
