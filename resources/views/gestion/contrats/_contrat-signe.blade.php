{{--
    Partiel de la case fixe "contrat signé" (voir show.blade.php) : soit le
    fichier déjà déposé (lien de téléchargement + retrait), soit un petit
    formulaire de dépôt. Variables attendues : $contratSigne
    (App\Models\ContratFichier, avec sa relation "document" déjà chargée),
    $contrat, $typeContrat, $peutModifier, $peutSupprimer, $tailleMaxMo.

    Même principe que resources/views/gestion/reunions/_rapport.blade.php :
    une seule case possible par contrat, pas de {fichier} dans son URL — voir
    ContratController::uploaderContratSigne()/supprimerContratSigne().
--}}
@if ($contratSigne?->estRemplie())
    <div class="d-flex flex-wrap align-items-center gap-2">
        <a href="{{ route('documents.voir', $contratSigne->document_id) }}" target="_blank" rel="noopener" class="text-decoration-none" title="Voir dans un nouvel onglet">
            <i class="bi bi-file-earmark-text"></i>
            {{ $contratSigne->document->nom_original }}
        </a>
        <span class="text-muted small">({{ $contratSigne->document->tailleFormatee() }})</span>
        <a href="{{ route('documents.telecharger', $contratSigne->document_id) }}" class="btn btn-sm btn-outline-secondary" title="Télécharger">
            <i class="bi bi-download"></i>
        </a>
        @include('gestion.partials._lien_historique', ['type' => \App\Models\Document::class, 'id' => $contratSigne->document_id])
        @include('gestion.partials._verrou_document', ['document' => $contratSigne->document])
        @unless ($contratSigne->document->estVerrouille())
            @if ($peutModifier)
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#remplacerContratSigne{{ $contrat->id }}">
                    Remplacer
                </button>
            @endif
            @if ($peutSupprimer)
                <form method="POST" action="{{ route('gestion.contrats.contrat-signe.destroy', ['typeContrat' => $typeContrat->code, 'contrat' => $contrat->id]) }}"
                      onsubmit="return confirm('Retirer le contrat signé ? La case restera disponible pour un nouveau dépôt.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Retirer">
                        <i class="bi bi-trash3"></i>
                    </button>
                </form>
            @endif
        @endunless
    </div>
    @if ($peutModifier && ! $contratSigne->document->estVerrouille())
        <div class="collapse mt-2" id="remplacerContratSigne{{ $contrat->id }}">
            <form method="POST" action="{{ route('gestion.contrats.contrat-signe.store', ['typeContrat' => $typeContrat->code, 'contrat' => $contrat->id]) }}" enctype="multipart/form-data" class="d-flex gap-2">
                @csrf
                <input type="file" name="fichier" class="form-control form-control-sm" required>
                <button type="submit" class="btn btn-sm btn-primary text-nowrap">Remplacer</button>
            </form>
        </div>
    @endif
@else
    @if ($peutModifier)
        <form method="POST" action="{{ route('gestion.contrats.contrat-signe.store', ['typeContrat' => $typeContrat->code, 'contrat' => $contrat->id]) }}" enctype="multipart/form-data" class="d-flex gap-2">
            @csrf
            <input type="file" name="fichier" class="form-control form-control-sm" required>
            <button type="submit" class="btn btn-sm btn-primary text-nowrap">
                <i class="bi bi-upload"></i> Déposer
            </button>
        </form>
        <div class="form-text">Taille maximale : {{ $tailleMaxMo }} Mo.</div>
    @else
        <span class="text-muted small">Aucun contrat signé déposé.</span>
    @endif
@endif
