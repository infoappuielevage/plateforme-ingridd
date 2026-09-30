<?php

namespace App\Support;

/**
 * Regroupement des types de document en familles logiques, pour les onglets
 * de la liste des documents (voir DocumentController::index() et
 * resources/views/gestion/documents/index.blade.php) — sur demande du
 * client, pour faciliter la gestion sur une liste qui compte de plus en
 * plus de types (26+ depuis la refonte complète du référentiel, voir
 * TypeDocumentSeeder) plutôt qu'un onglet par type exact, beaucoup trop
 * chargé visuellement pour un gain limité (la liste a de toute façon déjà
 * un filtre "Type" précis).
 *
 * Reprend à l'identique le regroupement qu'avait
 * App\Filament\Resources\DocumentResource\Pages\ListDocuments::getTabs()
 * (remplacée, plus de Filament) : basé sur le `code` des types (pas leur id
 * ni leur libellé, modifiables depuis Administration > Référentiels > Types
 * de documents) — un type renommé reste dans son onglet, un type
 * introuvable (référentiel vidé) fait simplement que l'onglet correspondant
 * n'affiche rien, sans erreur.
 *
 * Un nouveau type de document créé depuis l'administration avec un `code`
 * qui n'est dans aucune des listes ci-dessous n'apparaît que dans "Tous" —
 * limite connue d'un classement en dur, mais un onglet fixe est ce qui a
 * été demandé (l'alternative, un réglage d'administration pour ranger
 * chaque type dans une famille, serait disproportionnée pour ce besoin).
 */
class FamillesDocument
{
    /** Listes d'étudiants, relevés de notes, délibérations, PV de soutenance, syllabus, cahier pédagogique, bilan des cours. */
    private const CODES_ACADEMIQUE = [
        'LISTE_ETUDIANTS', 'RELEVE_NOTES', 'PV_DELIBERATION', 'PV_SOUTENANCE',
        'SYLLABUS_FILIERES', 'CAHIER_PEDAGOGIQUE', 'BILAN_COURS_LIGNE',
    ];

    /** Attestation, ancien "Diplôme" (désactivé mais des documents peuvent encore y être classés), listes des diplômés. */
    private const CODES_DIPLOMES_ATTESTATIONS = ['DIPLOME', 'ATTESTATION', 'LISTE_DIPLOME'];

    /** Instances et dossiers institutionnels : conseils, réunions, extension de filière, cahier des charges. */
    private const CODES_GOUVERNANCE = [
        'CONSEIL_ETABLISSEMENT', 'CONSEIL_SCIENTIFIQUE', 'PV_REUNION',
        'DOSSIER_EXTENSION_FILIERE', 'CAHIER_CHARGE_MINISTERE',
    ];

    /** Congés, personnel, missions, candidatures BTS. */
    private const CODES_RH_PERSONNEL = ['CONGE', 'LISTE_PERSONNEL', 'ORDRE_MISSION', 'CANDIDAT_BTS'];

    /** Tout le reste : contrats, correspondance, bordereaux, communication interne/externe, et les anciens types génériques désactivés (Facture, Rapport). */
    private const CODES_ADMINISTRATIF = [
        'CONTRAT', 'COURRIER', 'BORDEREAU', 'NOTICE_RENSEIGNEMENT', 'NOTE_INFORMATION',
        'ORGANIGRAMME', 'SPOT_TELE_RADIO', 'UNUPEL', 'CANEVAS_RAPPORT_RENTREE',
        'FACTURE', 'RAPPORT', 'AUTRE',
    ];

    /**
     * @return array<string, array{label: string, codes: ?array<int, string>}>
     *         'tous' a codes=null (aucun filtre à appliquer), dans l'ordre
     *         d'affichage des onglets.
     */
    public static function onglets(): array
    {
        return [
            'tous' => ['label' => 'Tous', 'codes' => null],
            'academique' => ['label' => 'Académique', 'codes' => self::CODES_ACADEMIQUE],
            'diplomes_attestations' => ['label' => 'Diplômes / Attestations', 'codes' => self::CODES_DIPLOMES_ATTESTATIONS],
            'gouvernance' => ['label' => 'Gouvernance', 'codes' => self::CODES_GOUVERNANCE],
            'rh_personnel' => ['label' => 'RH / Personnel', 'codes' => self::CODES_RH_PERSONNEL],
            'administratif' => ['label' => 'Administratif', 'codes' => self::CODES_ADMINISTRATIF],
        ];
    }

    /** Codes du référentiel appartenant à cet onglet, ou null pour "tous" (aucun filtre) — null aussi pour un onglet inconnu. */
    public static function codesPour(string $onglet): ?array
    {
        return self::onglets()[$onglet]['codes'] ?? null;
    }

    /** Un onglet manquant/inconnu dans l'URL (lien externe, favori obsolète...) retombe silencieusement sur "tous" plutôt qu'une erreur. */
    public static function ongletValide(string $onglet): string
    {
        return array_key_exists($onglet, self::onglets()) ? $onglet : 'tous';
    }
}
