{{--
    Partiel d'une pièce jointe (voir show.blade.php) — toujours remplie
    (contrairement au rapport, aucune ligne vide n'est jamais créée, voir
    ReunionController::ajouterPieceJointe()). Variables attendues : $fichier
    (App\Models\ReunionFichier, rôle "piece_jointe", avec sa relation
    "document" déjà chargée), $reunion, $peutSupprimer.

    Pas de "Remplacer" ici : contrairement au rapport (case fixe), retirer une
    pièce jointe supprime la ligne entièrement (voir
    ReunionController::supprimerPieceJointe()) — en ajouter une autre se fait
    via le formulaire d'ajout, pas en remplaçant celle-ci.
--}}
<div class="d-flex flex-wrap align-items-center gap-2">
    <a href="{{ route('documents.voir', $fichier->document_id) }}" target="_blank" rel="noopener" class="text-decoration-none" title="Voir dans un nouvel onglet">
        <i class="bi bi-file-earmark-text"></i>
        {{ $fichier->libelle() }}
    </a>
    <span class="text-muted small">({{ $fichier->document->nom_original }} — {{ $fichier->document->tailleFormatee() }})</span>
    <a href="{{ route('documents.telecharger', $fichier->document_id) }}" class="btn btn-sm btn-outline-secondary" title="Télécharger">
        <i class="bi bi-download"></i>
    </a>
    @if ($peutSupprimer)
        <form method="POST" action="{{ route('gestion.reunions.pieces-jointes.destroy', ['reunion' => $reunion->id, 'fichier' => $fichier->id]) }}"
              onsubmit="return confirm('Retirer cette pièce jointe ? Cette action est définitive.');">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-sm btn-outline-danger" title="Retirer">
                <i class="bi bi-trash3"></i>
            </button>
        </form>
    @endif
</div>
