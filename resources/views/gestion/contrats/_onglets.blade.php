{{--
    Onglets de navigation entre les catégories de contrats — liste DYNAMIQUE
    (voir App\Models\TypeContrat, gérée depuis Administration > Référentiels
    > Catégories de contrats), un onglet par catégorie ACTIVE, dans l'ordre
    défini par `ordre`.

    Design repris des onglets par année académique de PV de réunion (voir
    resources/views/gestion/reunions/index.blade.php et public/css/style.css)
    — demande explicite du client ("quand sa va devenir beaucoup sa sera
    compliqué intègre un scroll pour les categorie et ameliore le design et
    mets en gras") :
    - .nav-tabs-niveaux : onglets en pastilles arrondies, texte en gras
      (font-weight: 700), remplissage plein pour l'onglet actif ;
    - couleur PAR ROTATION (App\Models\TypeContrat::paletteIndex(), classes
      .palette-tab-N) — ce référentiel est OUVERT (une catégorie peut être
      ajoutée à tout moment), impossible de réserver une teinte dédiée à
      chacune à l'avance ;
    - .nav-tabs-scroll : les onglets restent sur UNE SEULE ligne qui défile
      horizontalement dès qu'ils ne tiennent plus, plutôt que de passer à la
      ligne ou de devenir illisibles à mesure que les catégories s'accumulent.

    Variables attendues : $categories (catégories actives, voir
    App\Http\Controllers\ContratController::categoriesActives()),
    $typeContrat (catégorie actuellement affichée).
--}}
<ul class="nav nav-tabs-niveaux nav-tabs-scroll mb-3">
    @foreach ($categories as $categorie)
        <li class="nav-item">
            <a href="{{ route('gestion.contrats.index', ['typeContrat' => $categorie->code]) }}"
               class="nav-link palette-tab-{{ $categorie->paletteIndex() }} {{ $categorie->id === $typeContrat->id ? 'active' : '' }}">
                {{ $categorie->libelle }}
            </a>
        </li>
    @endforeach
</ul>
