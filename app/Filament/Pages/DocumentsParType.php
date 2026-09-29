<?php

namespace App\Filament\Pages;

use App\Filament\Resources\DocumentResource;
use App\Models\Document;
use App\Models\SousTypeDocument;
use App\Models\TypeDocument;
use App\Support\ResourceAccess;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Une page par type de document (et, pour Attestation spécifiquement, une
 * page par sous-type) — sur demande explicite du client, pour "que chaque
 * type de document ait son tableau dissocié des autres" plutôt qu'une seule
 * liste avec des onglets. Ces pages apparaissent dans le menu "Secrétariat"
 * (voir AdminPanelProvider::elementsNavigationSecretariat()), qui construit
 * dynamiquement une entrée par ligne du référentiel TypeDocument (y compris
 * les types désactivés — Liste des étudiants/Diplôme/Facture/Rapport —, sur
 * demande du client, pour retrouver facilement les documents déjà classés
 * dedans) et une entrée par sous-type actif pour le type "Attestation" (qui
 * en a plusieurs : Succès, Vacation, Inscription...).
 *
 * UNE SEULE classe pour toutes ces pages (plutôt qu'une classe par type) :
 * le type — et, pour Attestation, le sous-type — vient de paramètres de
 * route (`{code}` et `{sousType?}`), lus dans mount(). Le formulaire
 * d'ajout/modification reste entièrement partagé : le tableau ci-dessous
 * réutilise DocumentResource::table() tel quel (mêmes colonnes, mêmes
 * filtres, mêmes actions, y compris "Modifier" qui ouvre le formulaire
 * habituel de DocumentResource), seule la requête de base change
 * (->query(), contrainte au type/sous-type de cette page) — voir table().
 *
 * `shouldRegisterNavigation()` renvoie false : cette classe ne doit PAS
 * apparaître elle-même comme une seule entrée de menu générique (Filament
 * enregistrerait sinon automatiquement "Documents Par Type" une fois) — les
 * vraies entrées, une par type/sous-type, sont ajoutées "à la main" dans
 * AdminPanelProvider (voir son docblock).
 */
class DocumentsParType extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $view = 'filament.pages.documents-par-type';

    public TypeDocument $typeDocument;

    public ?SousTypeDocument $sousTypeDocument = null;

    /**
     * Valeur d'URL utilisée pour "sousType" quand la page ne concerne aucun
     * sous-type (tous les types sauf Attestation) — voir le docblock de
     * getSlug() pour pourquoi ce segment ne peut PAS être simplement omis.
     */
    public const AUCUN_SOUS_TYPE = '-';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    /**
     * Route paramétrée (`{code}`, `{sousType}`) plutôt qu'un slug fixe :
     * c'est ce qui permet à UNE SEULE classe de page de servir les 30+
     * entrées du menu "Secrétariat" — voir mount() et le docblock de la
     * classe.
     *
     * `{sousType}` est OBLIGATOIRE (pas de `?`), même si la plupart des
     * types n'en ont pas besoin : quand ce segment est optionnel et omis de
     * l'URL, Laravel construit en interne un tableau de paramètres mixte
     * (une clé nommée pour "code" + une clé positionnelle 0 pour le
     * "sousType" par défaut), que PHP 8 refuse de dépaqueter avec `...`
     * dans Livewire (erreur "Cannot use positional argument after named
     * argument" — bug connu de l'écosystème Livewire/Filament sur les
     * paramètres de route optionnels, pas quelque chose de spécifique à ce
     * projet). En rendant le segment obligatoire et en fournissant TOUJOURS
     * une valeur (voir AUCUN_SOUS_TYPE, utilisé par
     * AdminPanelProvider::elementsNavigationSecretariat() pour les types
     * sans sous-type), ce cas ne se présente jamais.
     */
    public static function getSlug(): string
    {
        return 'secretariat/{code}/{sousType}';
    }

    public static function canAccess(): bool
    {
        return ResourceAccess::can('documents', 'view');
    }

    public function mount(string $code, ?string $sousType = null): void
    {
        abort_unless(ResourceAccess::can('documents', 'view'), 403);

        $this->typeDocument = TypeDocument::query()->where('code', $code)->firstOrFail();

        if ($sousType !== null && $sousType !== self::AUCUN_SOUS_TYPE) {
            $this->sousTypeDocument = SousTypeDocument::query()
                ->where('id', $sousType)
                ->where('type_document_id', $this->typeDocument->id)
                ->firstOrFail();
        }
    }

    public function getHeading(): string
    {
        return $this->sousTypeDocument
            ? "{$this->typeDocument->libelle} — {$this->sousTypeDocument->libelle}"
            : $this->typeDocument->libelle;
    }

    public function getTitle(): string
    {
        return $this->getHeading();
    }

    /**
     * "Ajouter un document" ouvre le formulaire habituel de DocumentResource
     * (formulaire partagé, voir le docblock de la classe) plutôt qu'un
     * formulaire propre à cette page — le type n'est pas présélectionné, le
     * choisir reste une action volontaire de l'utilisateur au moment de
     * remplir le formulaire.
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('ajouter')
                ->label('Ajouter un document')
                ->icon('heroicon-o-plus')
                ->url(DocumentResource::getUrl('create'))
                ->visible(fn () => ResourceAccess::can('documents', 'create')),
        ];
    }

    protected function requeteDeBase(): Builder
    {
        $query = Document::query()->where('type_document_id', $this->typeDocument->id);

        if ($this->sousTypeDocument) {
            $query->where('sous_type_document_id', $this->sousTypeDocument->id);
        }

        return $query;
    }

    /**
     * Reprend intégralement DocumentResource::table() (colonnes, filtres,
     * actions "Modifier"/"Marquer comme retiré"/corbeille...) : le seul
     * changement est la requête de base, restreinte au type (et sous-type)
     * de cette page — voir requeteDeBase(). La colonne "Type" reste affichée
     * (redondante ici puisque constante sur toute la page) plutôt que
     * retirée : la retirer obligerait à dupliquer la configuration complète
     * du tableau rien que pour enlever une colonne, ce qui casserait le
     * principe même du "formulaire/tableau partagé" demandé par le client.
     */
    public function table(Table $table): Table
    {
        return DocumentResource::table($table)->query($this->requeteDeBase());
    }
}
