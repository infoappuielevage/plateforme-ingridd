{{--
    Aperçu dans le navigateur d'un document dont le type n'est pas affichable
    nativement par le navigateur (Excel, Word, CSV, texte...) — voir
    routes/web.php (documents.voir). Le fichier n'est jamais converti ni
    réenregistré côté serveur : il est récupéré tel quel via la route de
    téléchargement existante (fetch, pas de navigation) et rendu entièrement
    côté client (SheetJS pour xlsx/xls/csv, docx-preview pour docx).
    Variables attendues : $document, $typeApercu ('tableur'|'docx'|'texte'|'indisponible'),
    $extension.
--}}
@extends('layouts.gestion')

@section('title', 'Aperçu — ' . $document->nom_original)

@section('content')
<div class="page-header-hero d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0 text-truncate">
        <i class="bi bi-file-earmark-text"></i> {{ $document->nom_original }}
    </h1>
    <div class="d-flex gap-2">
        <a href="{{ route('documents.telecharger', $document->id) }}" class="btn btn-outline-secondary">
            <i class="bi bi-download"></i> Télécharger
        </a>
    </div>
</div>

@if ($typeApercu === 'indisponible')
    <div class="alert alert-info d-flex align-items-center gap-2">
        <i class="bi bi-info-circle fs-5"></i>
        <div>
            L'aperçu dans le navigateur n'est pas disponible pour ce type de fichier (.{{ $extension }}).
            Utilisez le bouton « Télécharger » ci-dessus pour l'ouvrir.
        </div>
    </div>
@else
    <div id="apercu-chargement" class="d-flex align-items-center gap-2 text-muted py-5 justify-content-center">
        <div class="spinner-border spinner-border-sm" role="status"></div>
        Chargement de l'aperçu…
    </div>

    <div id="apercu-erreur" class="alert alert-warning d-none">
        <i class="bi bi-exclamation-triangle"></i>
        Impossible d'afficher l'aperçu de ce fichier ici. Utilisez le bouton « Télécharger » ci-dessus pour l'ouvrir.
    </div>

    @if ($typeApercu === 'tableur')
        <ul id="apercu-onglets" class="nav nav-tabs d-none mb-0"></ul>
        <div id="apercu-contenu" class="table-responsive border border-top-0 d-none" style="max-height: 70vh;"></div>
    @elseif ($typeApercu === 'docx')
        <div id="apercu-contenu" class="border rounded p-3 bg-white d-none" style="max-height: 80vh; overflow-y: auto;"></div>
    @elseif ($typeApercu === 'texte')
        <pre id="apercu-contenu" class="border rounded p-3 bg-white d-none" style="max-height: 80vh; overflow-y: auto; white-space: pre-wrap;"></pre>
    @endif
@endif
@endsection

@if ($typeApercu !== 'indisponible')
@section('scripts')
<script>
// IIFE entièrement protégée par un try/catch + un délai maximum sur CHAQUE
// étape (chargement des bibliothèques CDN, téléchargement, lecture) : avant
// cette version, un CDN lent/injoignable (connexion internet instable côté
// client — cas réel rencontré) laissait l'écran bloqué indéfiniment sur
// "Chargement de l'aperçu…", sans jamais basculer sur le message d'erreur
// (la balise <script src="..."> précédente bloquait le chargement de la
// page elle-même, donc CE script n'avait même pas encore pu s'exécuter).
// Les bibliothèques sont maintenant chargées dynamiquement ICI, avec un
// délai maximum explicite, plutôt que via une balise <script> statique.
(function () {
    const DELAI_MAX_BIBLIOTHEQUE_MS = 20000;
    const DELAI_MAX_TELECHARGEMENT_MS = 30000;

    const CDN = {
        xlsx: 'https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js',
        jszip: 'https://cdn.jsdelivr.net/npm/jszip@3.10.1/dist/jszip.min.js',
        docxPreview: 'https://cdn.jsdelivr.net/npm/docx-preview@0.3.0/dist/docx-preview.min.js',
    };

    const urlTelechargement = @json(route('documents.telecharger', $document->id));
    const typeApercu = @json($typeApercu);
    const extension = @json($extension);

    const elChargement = document.getElementById('apercu-chargement');
    const elErreur = document.getElementById('apercu-erreur');
    const elContenu = document.getElementById('apercu-contenu');
    const elOnglets = document.getElementById('apercu-onglets');

    function afficherErreur() {
        elChargement.classList.add('d-none');
        elErreur.classList.remove('d-none');
    }

    function afficherContenu() {
        elChargement.classList.add('d-none');
        elContenu.classList.remove('d-none');
        if (elOnglets) elOnglets.classList.remove('d-none');
    }

    function avecDelaiMax(promesse, ms) {
        return new Promise(function (resolve, reject) {
            const minuteur = setTimeout(function () {
                reject(new Error('Délai dépassé (' + ms + ' ms)'));
            }, ms);

            promesse.then(
                function (valeur) { clearTimeout(minuteur); resolve(valeur); },
                function (erreur) { clearTimeout(minuteur); reject(erreur); }
            );
        });
    }

    function chargerScript(src) {
        return new Promise(function (resolve, reject) {
            const script = document.createElement('script');
            script.src = src;
            script.onload = function () { resolve(); };
            script.onerror = function () { reject(new Error('Échec du chargement de ' + src)); };
            document.head.appendChild(script);
        });
    }

    function chargerBibliotheques() {
        if (typeApercu === 'tableur') {
            return chargerScript(CDN.xlsx);
        }
        if (typeApercu === 'docx') {
            return chargerScript(CDN.jszip).then(function () { return chargerScript(CDN.docxPreview); });
        }
        return Promise.resolve();
    }

    try {
        avecDelaiMax(chargerBibliotheques(), DELAI_MAX_BIBLIOTHEQUE_MS)
            .then(function () {
                return avecDelaiMax(fetch(urlTelechargement, { credentials: 'same-origin' }), DELAI_MAX_TELECHARGEMENT_MS);
            })
            .then(function (reponse) {
                if (!reponse.ok) throw new Error('Échec du téléchargement (' + reponse.status + ')');
                return reponse.arrayBuffer();
            })
            .then(function (donnees) {
                if (typeApercu === 'tableur') {
                    afficherTableur(donnees);
                } else if (typeApercu === 'docx') {
                    afficherDocx(donnees);
                } else if (typeApercu === 'texte') {
                    afficherTexte(donnees);
                }
            })
            .catch(function (erreur) {
                console.error(erreur);
                afficherErreur();
            });
    } catch (erreur) {
        console.error(erreur);
        afficherErreur();
    }

    function afficherTableur(donnees) {
        // Le CSV est lu comme une chaîne (encodage fiable), le reste (xlsx/xls)
        // comme un classeur binaire — comportement attendu de SheetJS.
        const classeur = (extension === 'csv')
            ? XLSX.read(new TextDecoder('utf-8').decode(donnees), { type: 'string' })
            : XLSX.read(donnees, { type: 'array' });

        const nomsFeuilles = classeur.SheetNames;
        if (!nomsFeuilles.length) throw new Error('Classeur vide');

        function rendreFeuille(nom) {
            const feuille = classeur.Sheets[nom];
            elContenu.innerHTML = XLSX.utils.sheet_to_html(feuille, { id: 'apercu-tableau' });
            const table = elContenu.querySelector('table');
            if (table) {
                table.classList.add('table', 'table-sm', 'table-bordered', 'table-striped', 'mb-0');
            }
        }

        if (nomsFeuilles.length > 1) {
            nomsFeuilles.forEach(function (nom, index) {
                const li = document.createElement('li');
                li.className = 'nav-item';
                const a = document.createElement('a');
                a.className = 'nav-link' + (index === 0 ? ' active' : '');
                a.href = '#';
                a.textContent = nom;
                a.addEventListener('click', function (e) {
                    e.preventDefault();
                    elOnglets.querySelectorAll('.nav-link').forEach(function (el) { el.classList.remove('active'); });
                    a.classList.add('active');
                    rendreFeuille(nom);
                });
                li.appendChild(a);
                elOnglets.appendChild(li);
            });
        }

        rendreFeuille(nomsFeuilles[0]);
        afficherContenu();
    }

    function afficherDocx(donnees) {
        docx.renderAsync(donnees, elContenu, undefined, {
            ignoreWidth: true,
            ignoreHeight: true,
        }).then(function () {
            afficherContenu();
        }).catch(function (erreur) {
            console.error(erreur);
            afficherErreur();
        });
    }

    function afficherTexte(donnees) {
        elContenu.textContent = new TextDecoder('utf-8').decode(donnees);
        afficherContenu();
    }
})();
</script>
@endsection
@endif
