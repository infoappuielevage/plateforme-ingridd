{{--
    Contenu de la colonne "Verrouillage" d'un tableau : badge
    Verrouillé/Non verrouillé + bouton "Verrouiller"/"Déverrouiller" pour un
    document — voir App\Support\Verrouillage. Prévu pour sa PROPRE colonne
    (<td> dédiée), séparée des actions Voir/Télécharger/Remplacer/Retirer —
    demande explicite du client. Inclure ainsi :
    @include('gestion.partials._verrou_document', ['document' => $document])

    Les deux boutons sont gérés par le droit ResourceAccess
    "verrouillage_documents" > "Modifier" (voir Administration > Rôles &
    permissions) — demande explicite du client ("intégrer le verrouillage
    dans les rôles et permissions") plutôt que réservés en dur au rôle admin.
    Un rôle "admin" a de toute façon toujours ce droit (voir
    App\Support\ResourceAccess::can()).

    Affiche un tiret si $document est null (aucun fichier déposé) — safe à
    inclure inconditionnellement.
--}}
@if ($document)
    @if ($document->estVerrouille())
        <span class="badge text-bg-dark" title="{{ $document->raisonVerrouillage() }}">
            <i class="bi bi-lock-fill"></i> Verrouillé
        </span>
        @if (\App\Support\ResourceAccess::can('verrouillage_documents', 'edit'))
            <form method="POST" action="{{ route('gestion.documents.deverrouiller', $document) }}" class="d-inline"
                  onsubmit="return confirm('Déverrouiller ce document pour permettre son remplacement ou sa suppression ?');">
                @csrf
                <button type="submit" class="btn btn-outline-dark btn-sm" title="Déverrouiller">
                    <i class="bi bi-unlock"></i>
                </button>
            </form>
        @endif
    @else
        <span class="badge text-bg-light text-muted border">
            <i class="bi bi-unlock"></i> Non verrouillé
        </span>
        @if (\App\Support\ResourceAccess::can('verrouillage_documents', 'edit'))
            <form method="POST" action="{{ route('gestion.documents.verrouiller', $document) }}" class="d-inline"
                  onsubmit="return confirm('Verrouiller ce document ? Il ne pourra plus être remplacé ni supprimé sans déverrouillage.');">
                @csrf
                <button type="submit" class="btn btn-outline-secondary btn-sm" title="Verrouiller">
                    <i class="bi bi-lock"></i>
                </button>
            </form>
        @endif
    @endif
@else
    <span class="text-muted small">—</span>
@endif
