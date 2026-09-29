<?php

namespace App\Filament\Resources\DocumentResource\Pages;

use App\Filament\Resources\DocumentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

/**
 * Onglets par famille logique de type de document (sur demande du client,
 * pour faciliter la gestion sur une liste qui compte de plus en plus de
 * types — 26+ depuis la refonte complète du référentiel, voir
 * TypeDocumentSeeder) plutôt qu'un onglet par type exact — beaucoup trop
 * chargé visuellement pour un gain limité, la liste ayant de toute façon
 * déjà un filtre "Type" précis.
 *
 * Regroupement basé sur le `code` des types (pas leur id ni leur libellé,
 * modifiables depuis Administration > Référentiels > Types de documents) —
 * même principe défensif qu'ailleurs dans DocumentResource (semestre,
 * champs diplôme/attestation) : un type renommé reste dans son onglet, un
 * type introuvable (référentiel vidé) fait simplement que l'onglet
 * correspondant n'affiche rien, sans erreur.
 *
 * Un nouveau type de document créé depuis l'administration avec un `code`
 * qui n'est dans aucune des listes ci-dessous n'apparaît que dans "Tous" —
 * limite connue d'un classement en dur, mais un onglet fixe est ce qui a été
 * demandé (l'alternative, un réglage d'administration pour ranger chaque
 * type dans une famille, serait disproportionnée pour ce besoin). Cinq
 * familles ont semblé le bon compromis pour la nouvelle liste (contre
 * quatre avant la refonte) ; à ajuster si l'usage réel en montre le besoin.
 */
class ListDocuments extends ListRecords
{
    protected static string $resource = DocumentResource::class;

    /** Listes d'étudiants, relevés de notes, délibérations, PV de soutenance, syllabus, cahier pédagogique, bilan des cours. */
    private const CODES_ACADEMIQUE = [
        'LISTE_ETUDIANTS', 'RELEVE_NOTES', 'PV_DELIBERATION', 'PV_SOUTENANCE',
        'SYLLABUS_FILIERES', 'CAHIER_PEDAGOGIQUE', 'BILAN_COURS_LIGNE',
    ];

    /**
     * Attestation, ancien "Diplôme" (désactivé mais des documents peuvent
     * encore y être classés), listes des diplômés.
     *
     * Codes en dur (pas App\Support\TypesDiplome::ids(), qui répond à une
     * question différente — "quels types ont les champs complémentaires
     * activés depuis l'administration ?" — désormais piloté par la colonne
     * `champs_diplome_actifs`, voir la migration 2026_09_29_000021_...) :
     * ce regroupement en onglets est volontairement fixe par famille de
     * code, comme les quatre autres onglets ci-dessous — voir le docblock
     * de la classe.
     */
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

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'tous' => Tab::make('Tous'),
            'academique' => Tab::make('Académique')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas(
                    'typeDocument',
                    fn (Builder $q) => $q->whereIn('code', self::CODES_ACADEMIQUE)
                )),
            'diplomes_attestations' => Tab::make('Diplômes / Attestations')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas(
                    'typeDocument',
                    fn (Builder $q) => $q->whereIn('code', self::CODES_DIPLOMES_ATTESTATIONS)
                )),
            'gouvernance' => Tab::make('Gouvernance')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas(
                    'typeDocument',
                    fn (Builder $q) => $q->whereIn('code', self::CODES_GOUVERNANCE)
                )),
            'rh_personnel' => Tab::make('RH / Personnel')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas(
                    'typeDocument',
                    fn (Builder $q) => $q->whereIn('code', self::CODES_RH_PERSONNEL)
                )),
            'administratif' => Tab::make('Administratif')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas(
                    'typeDocument',
                    fn (Builder $q) => $q->whereIn('code', self::CODES_ADMINISTRATIF)
                )),
        ];
    }
}
