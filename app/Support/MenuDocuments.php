<?php

namespace App\Support;

use App\Models\SousTypeDocument;
use App\Models\TypeDocument;
use Illuminate\Support\Collection;

/**
 * Construit la structure du sous-menu "Types de documents" du layout (voir
 * resources/views/layouts/gestion.blade.php, sous "Documents") à partir du
 * référentiel App\Models\TypeDocument/SousTypeDocument, avec deux
 * ajustements PUREMENT VISUELS demandés par le client — aucun des deux ne
 * touche aux données ni à `type_document_id` d'un document existant :
 *
 * - GROUPES : certains types, bien que distincts dans le référentiel (champs
 *   dédiés différents — voir TypeDocumentSeeder, "Soutenance" a ses propres
 *   champs, "Délibération" volontairement aucun), sont perçus par le client
 *   comme une seule famille dans le menu : pour l'instant "PV" (Délibération
 *   + PV de soutenance). Regroupés ici sous une entrée dépliable commune,
 *   chaque sous-entrée reste un lien filtré sur SON type réel.
 *
 * - "Autres types" : sur demande explicite du client ("met les dans
 *   autres"), tout type actif SANS sous-type et hors des groupes ci-dessus
 *   (la majorité des ~27 types du référentiel : Contrats, Courrier,
 *   Organigramme...) est rangé dans ce seau plutôt que d'avoir sa propre
 *   entrée de premier niveau (menu interminable) — en attendant que le
 *   client recense les vraies sous-catégories type par type plutôt que
 *   d'en inventer de fausses ici.
 *
 * Recalculé à chaque appel (pas de cache), même choix que le reste du menu
 * (voir gestion.blade.php) : ce référentiel change rarement, et un
 * administrateur qui ajoute un sous-type doit le voir apparaître
 * immédiatement, sans redéploiement.
 */
class MenuDocuments
{
    /** @var array<string, array{libelle: string, codes: array<int, string>}> */
    private const GROUPES = [
        'pv' => ['libelle' => 'PV', 'codes' => ['PV_DELIBERATION', 'PV_SOUTENANCE']],
    ];

    /**
     * @return Collection<int, array{
     *     cle: string,
     *     libelle: string,
     *     icone: string,
     *     urlType: ?string,
     *     actifType: bool,
     *     enfants: Collection<int, array{libelle: string, url: string, actif: bool}>,
     * }>
     */
    public static function elements(): Collection
    {
        $types = TypeDocument::query()
            ->where('actif', true)
            ->orderBy('ordre')
            ->with(['sousTypes' => fn ($q) => $q->where('actif', true)])
            ->get();

        $codesGroupes = collect(self::GROUPES)->flatMap(fn (array $groupe) => $groupe['codes'])->all();

        $elements = collect();

        foreach (self::GROUPES as $cle => $groupe) {
            $typesDuGroupe = $types->whereIn('code', $groupe['codes']);

            if ($typesDuGroupe->isEmpty()) {
                continue;
            }

            $elements->push([
                'cle' => 'groupe-'.$cle,
                'libelle' => $groupe['libelle'],
                'icone' => 'bi-folder-fill',
                // Pas de lien "Tous" pour un groupe visuel : ses membres sont
                // des types réels différents, aucun `type_document_id` ne
                // couvre "tout le groupe" à la fois (voir le docblock).
                'urlType' => null,
                'actifType' => $typesDuGroupe->contains(fn (TypeDocument $type) => self::surCeType($type)),
                'enfants' => $typesDuGroupe->map(fn (TypeDocument $type) => [
                    'libelle' => $type->libelle,
                    'url' => self::urlType($type),
                    'actif' => self::surCeType($type),
                ])->values(),
            ]);
        }

        foreach ($types as $type) {
            if (in_array($type->code, $codesGroupes, true) || $type->sousTypes->isEmpty()) {
                continue;
            }

            $elements->push([
                'cle' => 'type-'.$type->id,
                'libelle' => $type->libelle,
                'icone' => 'bi-folder-fill',
                'urlType' => self::urlType($type),
                'actifType' => self::surCeType($type),
                'enfants' => $type->sousTypes->map(fn (SousTypeDocument $sousType) => [
                    'libelle' => $sousType->libelle,
                    'url' => self::urlSousType($type, $sousType),
                    'actif' => self::surCeType($type) && (int) request('sous_type_document_id') === $sousType->id,
                ])->values(),
            ]);
        }

        $typesSimples = $types->reject(
            fn (TypeDocument $type) => in_array($type->code, $codesGroupes, true) || $type->sousTypes->isNotEmpty()
        );

        if ($typesSimples->isNotEmpty()) {
            $elements->push([
                'cle' => 'autres',
                'libelle' => 'Autres types',
                'icone' => 'bi-three-dots',
                'urlType' => null,
                'actifType' => $typesSimples->contains(fn (TypeDocument $type) => self::surCeType($type)),
                'enfants' => $typesSimples->map(fn (TypeDocument $type) => [
                    'libelle' => $type->libelle,
                    'url' => self::urlType($type),
                    'actif' => self::surCeType($type),
                ])->values(),
            ]);
        }

        return $elements;
    }

    private static function urlType(TypeDocument $type): string
    {
        return route('gestion.documents.index', ['type_document_id' => $type->id]);
    }

    private static function urlSousType(TypeDocument $type, SousTypeDocument $sousType): string
    {
        return route('gestion.documents.index', ['type_document_id' => $type->id, 'sous_type_document_id' => $sousType->id]);
    }

    /** Le document en cours de consultation (liste, pas corbeille) est-il filtré sur ce type ? */
    private static function surCeType(TypeDocument $type): bool
    {
        return request()->routeIs('gestion.documents.*')
            && ! request()->boolean('corbeille')
            && (int) request('type_document_id') === $type->id;
    }
}
