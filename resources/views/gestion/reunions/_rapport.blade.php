{{--
    Partiel de la case fixe "rapport" (voir show.blade.php) : soit le fichier
    déjà déposé (lien de téléchargement + retrait), soit un petit formulaire
    de dépôt. Variables attendues : $rapport (App\Models\ReunionFichier, avec
    sa relation "document" déjà chargée), $reunion, $peutModifier,
    $peutSupprimer, $tailleMaxMo.

    Contrairement aux autres écrans dédiés (une case par fichier), la case
    "rapport" d'une réunion n'a pas de {fichier} dans son URL — une seule
    case possible par réunion, voir ReunionController::uploaderRapport()/
    supprimerRapport().
--}}
@if ($rapport?->estRemplie())
    <div class="d-flex flex-wrap align-items-center gap-2">
        {{-- Le nom ouvre un APERÇU dans un nouvel onglet ("voir", Content-Disposition: inline —
             voir routes/web.php) ; le bouton à côté force un vrai téléchargement.
             Demande explicite du client : pouvoir consulter un PDF/une image sans
             d'abord le télécharger. --}}
        <a href="{{ route('documents.voir', $rapport->document_id) }}" target="_blank" rel="noopener" class="text-decoration-none" title="Voir dans un nouvel onglet">
            <i class="bi bi-file-earmark-text"></i>
            {{ $rapport->document->nom_original }}
        </a>
        <span class="text-muted small">({{ $rapport->document->tailleFormatee() }})</span>
        <a href="{{ route('documents.telecharger', $rapport->document_id) }}" class="btn btn-sm btn-outline-secondary" title="Télécharger">
            <i class="bi bi-download"></i>
        </a>
        @if ($peutModifier)
            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#remplacerRapport{{ $reunion->id }}">
                Remplacer
            </button>
        @endif
        @if ($peutSupprimer)
            <form method="POST" action="{{ route('gestion.reunions.rapport.destroy', $reunion->id) }}"
                  onsubmit="return confirm('Retirer le rapport ? La case restera disponible pour un nouveau dépôt.');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-sm btn-outline-danger" title="Retirer">
                    <i class="bi bi-trash3"></i>
                </button>
            </form>
        @endif
    </div>
    @if ($peutModifier)
        <div class="collapse mt-2" id="remplacerRapport{{ $reunion->id }}">
            <form method="POST" action="{{ route('gestion.reunions.rapport.store', $reunion->id) }}" enctype="multipart/form-data" class="d-flex gap-2">
                @csrf
                <input type="file" name="fichier" class="form-control form-control-sm" required>
                <button type="submit" class="btn btn-sm btn-primary text-nowrap">Remplacer</button>
            </form>
        </div>
    @endif
@else
    @if ($peutModifier)
        <form method="POST" action="{{ route('gestion.reunions.rapport.store', $reunion->id) }}" enctype="multipart/form-data" class="d-flex gap-2">
            @csrf
            <input type="file" name="fichier" class="form-control form-control-sm" required>
            <button type="submit" class="btn btn-sm btn-primary text-nowrap">
                <i class="bi bi-upload"></i> Déposer
            </button>
        </form>
        <div class="form-text">Taille maximale : {{ $tailleMaxMo }} Mo.</div>
    @else
        <span class="text-muted small">Aucun rapport déposé.</span>
    @endif
@endif
