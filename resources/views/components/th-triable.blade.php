{{--
    En-tête de colonne cliquable pour trier un tableau de liste — demande
    explicite du client ("ajoute la fonctionnalité de trier sur les
    tableaux"), appliquée à tous les tableaux de listes de l'application
    (voir App\Http\Controllers\Concerns\ListeTriable, qui calcule triActuel/
    directionActuelle côté contrôleur).

    Un simple lien GET (pas de JS) : cliquer une colonne non active trie
    dessus en ascendant ; recliquer la colonne déjà active inverse le sens.
    Tous les autres paramètres de la requête (filtres, onglet, corbeille...)
    sont préservés via request()->except(['tri', 'direction', 'page']) —
    même principe que les liens de filtre/onglet déjà utilisés ailleurs
    (ex. resources/views/gestion/documents/index.blade.php). `page` est
    volontairement exclu : changer le tri doit toujours revenir à la
    première page des résultats.

    Props :
    - cle (obligatoire) : clé publique de tri pour cette colonne (doit
      exister dans le tableau colonnesTriables() du contrôleur).
    - libelle (obligatoire) : texte affiché dans l'en-tête.
    - triActuel / directionActuelle (obligatoires) : valeurs renvoyées par
      ListeTriable::appliquerTri() côté contrôleur.
    - route (obligatoire) : nom de la route de la liste.
    - parametres : paramètres de route supplémentaires À PRÉSERVER qui ne
      sont PAS dans la query string (ex. le segment {referentiel} de
      gestion.referentiels.index, ou {code}/{sousType} de gestion.secretariat).
--}}
@props([
    'cle',
    'libelle',
    'triActuel',
    'directionActuelle',
    'route',
    'parametres' => [],
])

@php
    $estActif = $triActuel === $cle;
    $prochaineDirection = $estActif && $directionActuelle === 'asc' ? 'desc' : 'asc';
    $parametresLien = array_merge(
        request()->except(['tri', 'direction', 'page']),
        $parametres,
        ['tri' => $cle, 'direction' => $prochaineDirection]
    );
@endphp
<th>
    <a href="{{ route($route, $parametresLien) }}" class="th-triable {{ $estActif ? 'actif' : '' }}">
        {{ $libelle }}
        <span class="th-triable-fleche">
            @if ($estActif)
                <i class="bi bi-caret-{{ $directionActuelle === 'asc' ? 'up' : 'down' }}-fill"></i>
            @else
                <i class="bi bi-arrow-down-up"></i>
            @endif
        </span>
    </a>
</th>
