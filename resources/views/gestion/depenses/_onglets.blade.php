{{--
    Onglets de navigation entre les catégories de dépenses — contrairement à
    resources/views/gestion/recettes/_onglets.blade.php (trois volets fixes,
    menant chacun à un écran DIFFÉRENT), la liste est DYNAMIQUE (voir
    App\Models\TypeDepense, gérée depuis Administration > Référentiels >
    Catégories de dépenses) : un onglet par catégorie ACTIVE, dans l'ordre
    défini par `ordre`.

    Design repris des onglets de resources/views/gestion/contrats/_onglets.blade.php
    (eux-mêmes repris des onglets par année académique de PV de réunion, voir
    public/css/style.css) — demande explicite du client ("fais de meme pour
    et recette et depense, etc..") :
    - .nav-tabs-niveaux : onglets en pastilles arrondies, texte en gras ;
    - couleur PAR ROTATION (App\Models\TypeDepense::paletteIndex(), classes
      .palette-tab-N) — ce référentiel est OUVERT, impossible de réserver une
      teinte dédiée à chaque catégorie à l'avance ;
    - .nav-tabs-scroll : les onglets restent sur UNE SEULE ligne qui défile
      horizontalement dès qu'ils ne tiennent plus, plutôt que de passer à la
      ligne à mesure que les catégories s'accumulent.

    Variables attendues : $categories (catégories actives, voir
    App\Http\Controllers\DepenseController::categoriesActives()),
    $typeDepense (catégorie actuellement affichée).
--}}
<ul class="nav nav-tabs-niveaux nav-tabs-scroll mb-3">
    @foreach ($categories as $categorie)
        <li class="nav-item">
            <a href="{{ route('gestion.depenses.index', ['typeDepense' => $categorie->code]) }}"
               class="nav-link palette-tab-{{ $categorie->paletteIndex() }} {{ $categorie->id === $typeDepense->id ? 'active' : '' }}">
                {{ $categorie->libelle }}
            </a>
        </li>
    @endforeach
</ul>
