{{--
    Partiel d'un avenant (voir show.blade.php) — toujours rempli
    (contrairement au contrat signé, aucune ligne vide n'est jamais créée,
    voir ContratController::ajouterAvenant()). Variables attendues :
    $fichier (App\Models\ContratFichier, rôle "avenant", avec sa relation
    "document" déjà chargée), $contrat, $typeContrat, $peutSupprimer.

    Pas de "Remplacer" ici : contrairement au contrat signé (case fixe),
    retirer un avenant supprime la ligne entièrement (voir
    ContratController::supprimerAvenant()) — en ajouter un autre se fait via
    le formulaire d'ajout, pas en remplaçant celui-ci.
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
    @include('gestion.partials._lien_historique', ['type' => \App\Models\Document::class, 'id' => $fichier->document_id])
    @if ($peutSupprimer && ! $fichier->document->estVerrouille())
        <form method="POST" action="{{ route('gestion.contrats.avenants.destroy', ['typeContrat' => $typeContrat->code, 'contrat' => $contrat->id, 'fichier' => $fichier->id]) }}"
              onsubmit="return confirm('Retirer cet avenant ? Cette action est définitive.');">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-sm btn-outline-danger" title="Retirer">
                <i class="bi bi-trash3"></i>
            </button>
        </form>
    @endif
</div>
