{{--
    Partiel d'un fichier de la liste (voir show.blade.php) — toujours
    rempli, pas de "Remplacer" (retirer supprime la ligne entièrement),
    même principe que resources/views/gestion/recettes/dossiers/_fichier.blade.php.
    Variables attendues : $fichier (App\Models\ListeFinanceEtudiantFichier,
    avec sa relation "document" déjà chargée), $liste, $peutSupprimer.
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
        <form method="POST" action="{{ route('gestion.listes-finances-etudiants.fichiers.destroy', ['listeFinanceEtudiant' => $liste->id, 'fichier' => $fichier->id]) }}"
              onsubmit="return confirm('Retirer ce fichier ? Cette action est définitive.');">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-sm btn-outline-danger" title="Retirer">
                <i class="bi bi-trash3"></i>
            </button>
        </form>
    @endif
</div>
