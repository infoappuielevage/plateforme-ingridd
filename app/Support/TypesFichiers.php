<?php

namespace App\Support;

/**
 * Catalogue des familles de formats de fichiers acceptées pour un document —
 * voir Administration > Paramètres (section "Types de fichiers acceptés") et
 * App\Filament\Resources\DocumentResource::form().
 *
 * Volontairement une liste blanche FERMÉE de familles pré-approuvées plutôt
 * qu'une saisie libre de types MIME : l'admin peut activer/désactiver des
 * familles depuis l'interface sans toucher au code, mais ajouter une famille
 * entièrement nouvelle (ex. audio/vidéo) reste un changement de code — sur
 * un outil d'archivage, accepter n'importe quel type sur simple déclaration
 * texte affaiblirait la protection qu'une liste blanche est censée apporter
 * (voir le commentaire de DocumentResource::form() sur ce choix).
 */
class TypesFichiers
{
    public const CATEGORIES = [
        'documents' => [
            'label' => 'Documents (PDF, Word)',
            'mimes' => [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ],
        ],
        'tableurs' => [
            'label' => 'Tableurs (Excel)',
            'mimes' => [
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ],
        ],
        'presentations' => [
            'label' => 'Présentations (PowerPoint)',
            'mimes' => [
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            ],
        ],
        'texte' => [
            'label' => 'Texte brut / CSV',
            'mimes' => [
                'text/plain',
                'text/csv',
            ],
        ],
        'images' => [
            'label' => 'Images (PNG, JPEG, WebP)',
            'mimes' => [
                'image/png',
                'image/jpeg',
                'image/webp',
            ],
        ],
        'archives' => [
            'label' => 'Archives ZIP',
            'mimes' => [
                'application/zip',
            ],
        ],
    ];

    /** @return array<int, string> */
    public static function toutesLesCles(): array
    {
        return array_keys(self::CATEGORIES);
    }

    /** @return array<string, string> pour un CheckboxList Filament (clé => libellé) */
    public static function optionsPourFormulaire(): array
    {
        return collect(self::CATEGORIES)->map(fn (array $categorie) => $categorie['label'])->all();
    }

    /**
     * @param  array<int, string>|null  $categories  clés de self::CATEGORIES ; null ou vide = toutes (comportement par défaut)
     * @return array<int, string> types MIME acceptés par Filament\Forms\Components\FileUpload::acceptedFileTypes()
     */
    public static function mimesPour(?array $categories): array
    {
        $categories = ! empty($categories) ? $categories : self::toutesLesCles();

        return collect(self::CATEGORIES)
            ->only($categories)
            ->flatMap(fn (array $categorie) => $categorie['mimes'])
            ->values()
            ->all();
    }
}
