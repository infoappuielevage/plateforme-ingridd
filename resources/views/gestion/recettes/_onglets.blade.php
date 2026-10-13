{{--
    Onglets de navigation entre les trois volets de Gestion des Recettes —
    demande explicite du client, choix confirmé en amont ("2 écrans, avec
    onglets internes") : Scolarité/Retrait diplôme/Frais de soutenance
    partagent cette même barre, affichée en haut de leurs pages
    index/show respectives (voir
    resources/views/gestion/recettes/scolarite/*.blade.php et
    resources/views/gestion/recettes/dossiers/*.blade.php).

    Design en pastilles arrondies/gras (.nav-tabs-niveaux, voir
    public/css/style.css) — demande explicite du client ("fais de meme pour
    et recette et depense, etc.."), même esprit que les onglets Dépenses/
    Contrats/PV de réunion ailleurs dans l'application. Nombre FIXE à 3
    (jamais de scroll nécessaire ici, contrairement aux référentiels OUVERTS
    Dépenses/Contrats) : ici chaque onglet mène à un ÉCRAN DIFFÉRENT, pas un
    filtre au sein d'un même écran — chacun reçoit donc sa PROPRE couleur
    dédiée (.recette-tab-*), jamais les classes .niveau-tab-*/.palette-tab-*
    elles-mêmes, réservées à d'autres référentiels.

    Variable attendue : $ongletActif ('scolarite'|'retrait-diplome'|'frais-soutenance').
--}}
<ul class="nav nav-tabs-niveaux mb-3">
    <li class="nav-item">
        <a href="{{ route('gestion.recettes.scolarite.index') }}" class="nav-link recette-tab-scolarite {{ $ongletActif === 'scolarite' ? 'active' : '' }}">
            <i class="bi bi-mortarboard"></i> Scolarité
        </a>
    </li>
    <li class="nav-item">
        <a href="{{ route('gestion.recettes.dossiers.index', ['type' => 'retrait-diplome']) }}" class="nav-link recette-tab-retrait-diplome {{ $ongletActif === 'retrait-diplome' ? 'active' : '' }}">
            <i class="bi bi-mortarboard-fill"></i> Retrait diplôme
        </a>
    </li>
    <li class="nav-item">
        <a href="{{ route('gestion.recettes.dossiers.index', ['type' => 'frais-soutenance']) }}" class="nav-link recette-tab-frais-soutenance {{ $ongletActif === 'frais-soutenance' ? 'active' : '' }}">
            <i class="bi bi-cash-coin"></i> Frais de soutenance
        </a>
    </li>
</ul>
