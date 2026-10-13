{{--
    Raccourci "Voir l'historique" vers l'historique filtré d'UN SEUL
    enregistrement (document, dépense, dossier de recette, PV...) — demande
    explicite du client ("savoir qui a enregistré/modifié quoi et quand pour
    chaque fichier"), plutôt que devoir chercher dans l'historique global
    (Administration > Historique, voir App\Http\Controllers\AuditLogController).

    Paramètres attendus : $type (nom de classe complet, ex. \App\Models\Depense::class)
    et $id (clé primaire de l'enregistrement). Exemple d'inclusion :
    @include('gestion.partials._lien_historique', ['type' => \App\Models\Depense::class, 'id' => $depense->id])

    Nécessite le droit "audit_logs" > voir (même droit que l'écran Historique
    lui-même, voir App\Support\ResourceAccess) — un rôle qui n'a pas accès à
    l'historique global ne doit pas non plus y accéder par ce raccourci.

    Ouvre la fenêtre modale PARTAGÉE #modalHistorique (une seule, définie une
    fois dans resources/views/layouts/gestion.blade.php) plutôt que de
    naviguer vers gestion.historique.index — demande explicite du client
    ("rester sur la même page pour voir l'historique du fichier"). La modale
    charge le contenu via fetch (voir son script dans le layout) à partir de
    `data-historique-url`, qui pointe vers CE MÊME index() mais avec
    `fragment=1` (juste le tableau, sans le layout complet).
--}}
@if (\App\Support\ResourceAccess::can('audit_logs', 'view'))
    <button type="button" class="btn btn-outline-secondary btn-sm js-historique-bouton" title="Voir l'historique"
            data-bs-toggle="modal" data-bs-target="#modalHistorique"
            data-historique-url="{{ route('gestion.historique.index', ['model_type' => $type, 'model_id' => $id, 'fragment' => 1]) }}"
            data-historique-url-complet="{{ route('gestion.historique.index', ['model_type' => $type, 'model_id' => $id]) }}"
            data-historique-titre="{{ class_basename($type) }} #{{ $id }}">
        <i class="bi bi-clock-history"></i>
    </button>
@endif
