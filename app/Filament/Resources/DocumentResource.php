<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasRoleBasedAccess;
use App\Filament\Resources\DocumentResource\Pages;
use App\Models\AnneeAcademique;
use App\Models\Document;
use App\Models\Filiere;
use App\Models\Niveau;
use App\Models\Parametre;
use App\Models\SousTypeDocument;
use App\Models\TypeDocument;
use App\Support\ResourceAccess;
use App\Support\Semestres;
use App\Support\TypesDiplome;
use App\Support\TypesFichiers;
use App\Support\TypesPvSoutenance;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ForceDeleteAction;
use Filament\Tables\Actions\ForceDeleteBulkAction;
use Filament\Tables\Actions\RestoreAction;
use Filament\Tables\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Cœur de la plateforme. Le champ `fichier` n'est pas une colonne du modèle :
 * c'est un champ de formulaire Filament dont la valeur (chemin sur le disque
 * `documents`, privé — voir config/filesystems.php) est recopiée vers
 * `chemin` dans Pages\CreateDocument/EditDocument, en même temps que sont
 * calculés `nom_original`, `extension` et `taille`.
 *
 * Le téléchargement ne passe jamais par une URL publique : voir
 * routes/web.php (route authentifiée, vérifiée via ResourceAccess).
 *
 * Suppression = corbeille (Document::class utilise SoftDeletes) : le filtre
 * "Corbeille" (TrashedFilter) permet de voir les documents supprimés, les
 * restaurer, ou les supprimer définitivement (seul cas où le fichier
 * physique est vraiment effacé — voir Document::booted()).
 *
 * Sélection multiple (cases à cocher) : actions groupées (mettre à la
 * corbeille / restaurer / supprimer définitivement), toutes soumises à la
 * même permission "documents.delete" que les actions individuelles.
 */
class DocumentResource extends Resource
{
    use HasRoleBasedAccess;

    protected static ?string $model = Document::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Documents';

    protected static ?string $navigationLabel = 'Documents';

    protected static ?string $modelLabel = 'document';

    protected static ?string $pluralModelLabel = 'documents';

    public static function resourceAccessKey(): string
    {
        return 'documents';
    }

    public static function form(Form $form): Form
    {
        // Lu une fois à la construction du formulaire (pas de Closure passée
        // à ->maxSize()/->helperText()/->acceptedFileTypes() : plus simple,
        // et évite de dépendre de la prise en charge des Closures par ces
        // méthodes précises selon la version de Filament).
        $parametres = Parametre::actuel();
        $tailleMaxMo = $parametres->taille_max_document_mo;

        // Calculé une fois : id du type "Relevé de notes" (s'il existe), pour
        // n'afficher le champ "Semestre" que pour ce type de document — voir
        // le champ `semestre` ci-dessous. Si ce type a été renommé/supprimé,
        // le champ reste simplement toujours masqué (pas d'erreur).
        $typeReleveNotesId = TypeDocument::query()->where('code', 'RELEVE_NOTES')->value('id');

        // Id(s) des types "Diplôme"/"Attestation" — les champs complémentaires
        // ci-dessous (titulaire, numéro, date de délivrance, mention, statut
        // de retrait) ne sont affichés/enregistrés que pour ces deux types.
        // Même logique de dégradation silencieuse que $typeReleveNotesId : si
        // le référentiel a été vidé/renommé, ces champs restent simplement
        // toujours masqués (pas d'erreur).
        $typeDiplomeAttestationIds = TypesDiplome::ids();
        $estDiplomeOuAttestation = fn (Get $get): bool => in_array((int) $get('type_document_id'), $typeDiplomeAttestationIds, true);

        // Id du type "PV de soutenance" (s'il existe) — numérisation du
        // registre papier des soutenances de mémoire (voir
        // App\Support\TypesPvSoutenance). `nom_titulaire`/`mention`
        // ci-dessous sont partagés avec les diplômes/attestations (mêmes
        // colonnes, même sens : nom de l'étudiant, mention obtenue) ; les
        // trois champs `theme_memoire`/`date_soutenance`/`membres_jury` ne
        // concernent que ce type précis. Même dégradation silencieuse que
        // pour $typeDiplomeAttestationIds si le référentiel est vidé/renommé.
        $typePvSoutenanceIds = TypesPvSoutenance::ids();
        $estPvSoutenance = fn (Get $get): bool => in_array((int) $get('type_document_id'), $typePvSoutenanceIds, true);
        $aTitulaireEtMention = fn (Get $get): bool => $estDiplomeOuAttestation($get) || $estPvSoutenance($get);

        // Sous-types actifs du type actuellement choisi (voir
        // App\Models\SousTypeDocument) : liste gérée par type depuis
        // Administration > Référentiels > Types de documents, pas figée dans
        // le code (contrairement à $typeReleveNotesId ci-dessus) — utile pour
        // des listes ouvertes comme "Bordereau" (BNSP, CNSS, Coris Banque...).
        // Le champ "Sous-type" ci-dessous ne s'affiche que si le type
        // sélectionné en a au moins un actif.
        $sousTypesActifsPourType = fn (Get $get) => SousTypeDocument::query()
            ->where('type_document_id', $get('type_document_id'))
            ->where('actif', true)
            ->orderBy('ordre')
            ->pluck('libelle', 'id');

        return $form->schema([
            Select::make('type_document_id')
                ->label('Type de document')
                ->relationship('typeDocument', 'libelle', fn ($query) => $query->where('actif', true))
                ->searchable()
                ->preload()
                ->required()
                // ->live() : nécessaire pour que les champs qui dépendent du
                // type (Semestre, Sous-type, champs diplôme/attestation/PV de
                // soutenance) apparaissent/disparaissent dynamiquement selon
                // le type choisi, sans recharger la page.
                ->live()
                // Un sous-type choisi pour un type A n'a plus de sens si
                // l'utilisateur change ensuite pour un type B (même si B a
                // aussi des sous-types actifs, ce ne sont pas les mêmes
                // lignes) — sans ce reset, `sous_type_document_id` garderait
                // silencieusement une valeur appartenant à l'ancien type.
                ->afterStateUpdated(fn (Set $set) => $set('sous_type_document_id', null)),
            Select::make('sous_type_document_id')
                ->label('Sous-type')
                ->options($sousTypesActifsPourType)
                ->visible(fn (Get $get) => $sousTypesActifsPourType($get)->isNotEmpty())
                ->dehydrated(fn (Get $get) => $sousTypesActifsPourType($get)->isNotEmpty())
                ->searchable()
                ->helperText('N\'apparaît que si le type sélectionné a des sous-types configurés (Administration > Référentiels > Types de documents).'),
            Select::make('filiere_id')
                ->label('Filière')
                ->relationship('filiere', 'libelle', fn ($query) => $query->where('actif', true))
                ->searchable()
                ->preload()
                ->helperText('Facultatif : à renseigner pour les documents liés à une filière précise (listes d\'étudiants, relevés de notes, délibérations...).'),
            Select::make('niveau_id')
                ->label('Niveau')
                ->relationship('niveau', 'libelle', fn ($query) => $query->where('actif', true))
                ->searchable()
                ->preload()
                // ->live() : nécessaire pour que les options du champ
                // "Semestre" se limitent au bon semestre selon le niveau
                // choisi (L1 = S1/S2, L2 = S3/S4... — voir App\Support\Semestres
                // et le champ `semestre` ci-dessous).
                ->live()
                ->helperText('Facultatif : niveau d\'étude concerné (ex. Licence 1, Master 2...).'),
            Select::make('annee_academique_id')
                ->label('Année académique')
                // Liste déroulante (référentiel AnneeAcademique) plutôt qu'une
                // saisie libre : évite les doublons proches ("2025-2026" vs
                // "2025 - 2026") — voir Administration > Référentiels > Années
                // académiques pour en ajouter.
                ->relationship('anneeAcademique', 'libelle', fn ($query) => $query->where('actif', true)->orderByDesc('libelle'))
                ->searchable()
                ->preload()
                ->helperText('Facultatif.'),
            Select::make('semestre')
                ->label('Semestre')
                // Les options dépendent du niveau choisi : L1 = Semestre 1/2,
                // L2 = Semestre 3/4, L3 = Semestre 5/6, M1 = Semestre 7/8,
                // M2 = Semestre 9/10 (numérotation continue sur les 5 années,
                // pas remise à 1/2 à chaque niveau) — voir App\Support\Semestres.
                ->options(fn (Get $get) => Semestres::optionsPourCodeNiveau(
                    Niveau::query()->find($get('niveau_id'))?->code
                ))
                // N'apparaît que pour les relevés de notes : c'est le seul type
                // de document pour lequel le semestre a été demandé. Si le type
                // "Relevé de notes" a été renommé/supprimé, le champ reste
                // masqué (pas d'erreur — voir $typeReleveNotesId ci-dessus).
                ->visible(fn (Get $get) => $typeReleveNotesId !== null && (int) $get('type_document_id') === $typeReleveNotesId)
                ->dehydrated(fn (Get $get) => $typeReleveNotesId !== null && (int) $get('type_document_id') === $typeReleveNotesId)
                // Désactivé (plutôt que cassé/vide) tant qu'aucun niveau
                // reconnu (L1/L2/L3/M1/M2) n'est sélectionné : impossible de
                // savoir quels semestres proposer sans ça.
                ->disabled(fn (Get $get) => empty(Semestres::pourCodeNiveau(Niveau::query()->find($get('niveau_id'))?->code)))
                ->helperText(fn (Get $get) => empty(Semestres::pourCodeNiveau(Niveau::query()->find($get('niveau_id'))?->code))
                    ? 'Sélectionnez d\'abord un niveau (Licence 1, Master 2...) pour voir les semestres disponibles.'
                    : 'Semestre concerné par ce relevé de notes.'),
            // Les champs suivants ne concernent que les diplômes/attestations
            // et/ou le PV de soutenance (voir $estDiplomeOuAttestation et
            // $estPvSoutenance ci-dessus) — sur demande explicite du client,
            // pour faciliter leur gestion et leur suivi. `nom_titulaire` et
            // `mention` sont partagés par les trois types (même colonne,
            // même sens : nom de l'étudiant/du titulaire, mention obtenue).
            TextInput::make('nom_titulaire')
                ->label('Nom du titulaire / de l\'étudiant')
                ->maxLength(255)
                ->visible($aTitulaireEtMention)
                ->dehydrated($aTitulaireEtMention)
                ->required($aTitulaireEtMention)
                ->helperText('Nom et prénom(s) de l\'étudiant concerné par ce document.'),
            TextInput::make('numero_diplome')
                ->label('Numéro de diplôme / d\'enregistrement')
                ->maxLength(100)
                ->visible($estDiplomeOuAttestation)
                ->dehydrated($estDiplomeOuAttestation)
                ->helperText('Facultatif.'),
            DatePicker::make('date_delivrance')
                ->label('Date de délivrance')
                ->visible($estDiplomeOuAttestation)
                ->dehydrated($estDiplomeOuAttestation)
                ->helperText('Facultatif : date réelle d\'obtention, si différente de la date d\'ajout dans la plateforme.'),
            // Les trois champs suivants ne concernent que le PV de
            // soutenance (numérisation du registre papier — voir
            // App\Support\TypesPvSoutenance) : thème du mémoire, date de
            // soutenance et membres du jury.
            TextInput::make('theme_memoire')
                ->label('Thème du mémoire')
                ->maxLength(255)
                ->visible($estPvSoutenance)
                ->dehydrated($estPvSoutenance)
                ->helperText('Facultatif.'),
            DatePicker::make('date_soutenance')
                ->label('Date de soutenance')
                ->visible($estPvSoutenance)
                ->dehydrated($estPvSoutenance)
                ->helperText('Facultatif : date réelle de la soutenance, si différente de la date d\'ajout dans la plateforme.'),
            Textarea::make('membres_jury')
                ->label('Membres du jury')
                ->maxLength(2000)
                ->visible($estPvSoutenance)
                ->dehydrated($estPvSoutenance)
                ->helperText('Facultatif : un nom par ligne, avec le rôle si besoin (ex. Président, Rapporteur, Membre).')
                ->columnSpanFull(),
            TextInput::make('mention')
                ->label('Mention')
                ->maxLength(100)
                ->visible($aTitulaireEtMention)
                ->dehydrated($aTitulaireEtMention)
                ->helperText('Facultatif : ex. Passable, Assez bien, Bien, Très bien.'),
            Select::make('statut_retrait')
                ->label('Statut de retrait')
                ->options([
                    Document::STATUT_RETRAIT_NON_RETIRE => 'Non retiré',
                    Document::STATUT_RETRAIT_RETIRE => 'Retiré',
                ])
                ->default(Document::STATUT_RETRAIT_NON_RETIRE)
                ->visible($estDiplomeOuAttestation)
                ->dehydrated($estDiplomeOuAttestation)
                ->live()
                ->afterStateUpdated(function (?string $state, Set $set) {
                    // Pré-remplit la date de retrait dès que le statut passe
                    // à "Retiré" (peut ensuite être corrigée manuellement) —
                    // le tableau des documents propose aussi un bouton
                    // "Marquer comme retiré" qui fait la même chose en un clic.
                    if ($state === Document::STATUT_RETRAIT_RETIRE) {
                        $set('date_retrait', now()->toDateString());
                    }
                })
                ->helperText('Le tableau des documents propose aussi un bouton "Marquer comme retiré" pour ce faire en un clic, sans passer par la modification.'),
            DatePicker::make('date_retrait')
                ->label('Date de retrait')
                ->visible(fn (Get $get) => $estDiplomeOuAttestation($get) && $get('statut_retrait') === Document::STATUT_RETRAIT_RETIRE)
                ->dehydrated(fn (Get $get) => $estDiplomeOuAttestation($get) && $get('statut_retrait') === Document::STATUT_RETRAIT_RETIRE),
            Textarea::make('description')
                ->label('Description')
                ->maxLength(2000)
                ->columnSpanFull(),
            FileUpload::make('fichier')
                ->label('Fichier')
                ->disk('documents')
                ->directory('uploads')
                ->visibility('private')
                // Liste blanche volontaire (plutôt qu'une liste noire, toujours
                // incomplète), excluant tout exécutable/script — voir
                // routes/web.php : le téléchargement force de toute façon
                // "Content-Disposition: attachment", donc même un fichier
                // HTML/SVG ne peut pas s'exécuter dans le navigateur — cette
                // liste est une protection supplémentaire, pas la seule.
                //
                // Les FAMILLES acceptées (documents, tableurs, images...) sont
                // modifiables depuis Administration > Paramètres > Types de
                // fichiers, sans toucher au code — voir App\Support\TypesFichiers.
                // Ajouter une famille entièrement nouvelle (ex. audio/vidéo)
                // reste, lui, un changement de code : la liste des familles
                // elle-même doit rester une liste blanche pré-vérifiée.
                ->acceptedFileTypes(TypesFichiers::mimesPour($parametres->types_fichiers_autorises))
                ->maxSize($tailleMaxMo * 1024)
                ->required(fn (string $operation) => $operation === 'create')
                ->live()
                ->afterStateUpdated(function ($state, Set $set) {
                    if ($state instanceof TemporaryUploadedFile) {
                        $set('nom_original', $state->getClientOriginalName());
                    }
                })
                ->helperText("{$tailleMaxMo} Mo maximum. Le nom d'origine du fichier est conservé pour l'affichage. Types acceptés modifiables dans Administration > Paramètres.")
                ->columnSpanFull(),
            Hidden::make('nom_original'),
        ]);
    }

    public static function table(Table $table): Table
    {
        // Voir form() pour le même calcul : id(s) des types "Diplôme"/
        // "Attestation", utilisés ci-dessous pour n'afficher les colonnes/
        // actions liées au retrait que sur les documents concernés.
        $typeDiplomeAttestationIds = TypesDiplome::ids();

        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('typeDocument.libelle')->label('Type')->sortable(),
                TextColumn::make('sousTypeDocument.libelle')->label('Sous-type')->sortable()->toggleable(),
                TextColumn::make('filiere.libelle')->label('Filière')->sortable()->toggleable(),
                TextColumn::make('niveau.libelle')->label('Niveau')->sortable()->toggleable(),
                TextColumn::make('anneeAcademique.libelle')->label('Année académique')->sortable()->toggleable(),
                TextColumn::make('semestre')
                    ->label('Semestre')
                    ->formatStateUsing(fn (?int $state) => $state ? "Semestre {$state}" : null)
                    ->toggleable(),
                TextColumn::make('nom_titulaire')->label('Titulaire / Étudiant')->searchable()->toggleable(),
                TextColumn::make('numero_diplome')->label('N° diplôme')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('date_delivrance')->label('Délivré le')->date('d/m/Y')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('theme_memoire')->label('Thème du mémoire')->limit(40)->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('date_soutenance')->label('Soutenu le')->date('d/m/Y')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('membres_jury')->label('Jury')->limit(40)->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('mention')->label('Mention')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('statut_retrait')
                    ->label('Retrait')
                    ->badge()
                    ->formatStateUsing(fn (?string $state, Document $record) => $record->statutRetraitLibelle())
                    ->color(fn (?string $state) => match ($state) {
                        Document::STATUT_RETRAIT_RETIRE => 'success',
                        Document::STATUT_RETRAIT_NON_RETIRE => 'warning',
                        default => 'gray',
                    })
                    ->toggleable(),
                TextColumn::make('date_retrait')->label('Retiré le')->date('d/m/Y')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('nom_original')->label('Fichier')->searchable(),
                TextColumn::make('taille')
                    ->label('Taille')
                    ->formatStateUsing(fn (?int $state, Document $record) => $record->tailleFormatee()),
                TextColumn::make('uploadePar.name')->label('Ajouté par')->sortable(),
                TextColumn::make('created_at')->label('Ajouté le')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('modifiePar.name')->label('Modifié par')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')->label('Modifié le')->dateTime('d/m/Y H:i')->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')->label('Mis à la corbeille le')->dateTime('d/m/Y H:i')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type_document_id')
                    ->label('Type')
                    ->options(fn () => TypeDocument::query()->orderBy('ordre')->pluck('libelle', 'id')),
                // Préfixé par le libellé du type parent (ex. "Attestation —
                // Vacation") : plusieurs types peuvent avoir un sous-type de
                // même libellé (ex. "Licence"/"Master" pourraient réapparaître
                // ailleurs), la liste à plat serait sinon ambiguë.
                SelectFilter::make('sous_type_document_id')
                    ->label('Sous-type')
                    ->options(fn () => SousTypeDocument::query()
                        ->with('typeDocument')
                        ->orderBy('ordre')
                        ->get()
                        ->mapWithKeys(fn (SousTypeDocument $sousType) => [
                            $sousType->id => trim($sousType->typeDocument?->libelle.' — '.$sousType->libelle),
                        ])),
                SelectFilter::make('filiere_id')
                    ->label('Filière')
                    ->options(fn () => Filiere::query()->orderBy('ordre')->pluck('libelle', 'id')),
                SelectFilter::make('niveau_id')
                    ->label('Niveau')
                    ->options(fn () => Niveau::query()->orderBy('ordre')->pluck('libelle', 'id')),
                SelectFilter::make('annee_academique_id')
                    ->label('Année académique')
                    ->options(fn () => AnneeAcademique::query()->orderByDesc('libelle')->pluck('libelle', 'id')),
                SelectFilter::make('semestre')
                    ->label('Semestre')
                    // Les 10 semestres du système LMD (voir App\Support\Semestres)
                    // — ce filtre ne dépend pas d'un niveau précis, contrairement
                    // au champ du formulaire d'ajout/modification.
                    ->options(Semestres::toutesLesOptions()),
                // Ne concerne que les diplômes/attestations (les autres
                // documents ont `statut_retrait` à null, donc n'apparaissent
                // dans aucune des deux options ci-dessous).
                SelectFilter::make('statut_retrait')
                    ->label('Statut de retrait')
                    ->options([
                        Document::STATUT_RETRAIT_NON_RETIRE => 'Non retiré',
                        Document::STATUT_RETRAIT_RETIRE => 'Retiré',
                    ]),
                // Date d'ajout : deux champs optionnels, combinables (l'un sans
                // l'autre fonctionne aussi) — un badge résume la sélection
                // active au-dessus du tableau (->indicateUsing).
                Filter::make('ajoute_entre')
                    ->label('Date d\'ajout')
                    ->form([
                        DatePicker::make('du')->label('Du'),
                        DatePicker::make('au')->label('Au'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['du'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['au'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicateurs = [];

                        if ($data['du'] ?? null) {
                            $indicateurs[] = 'Ajoutés depuis le '.Carbon::parse($data['du'])->format('d/m/Y');
                        }

                        if ($data['au'] ?? null) {
                            $indicateurs[] = 'Ajoutés jusqu\'au '.Carbon::parse($data['au'])->format('d/m/Y');
                        }

                        return $indicateurs;
                    }),
                // Sans corbeille (par défaut) / Avec la corbeille / Seulement
                // la corbeille — voir Document::class (SoftDeletes) et les
                // actions Restaurer/Supprimer définitivement ci-dessous.
                TrashedFilter::make(),
            ])
            ->actions([
                Action::make('telecharger')
                    ->label('Télécharger')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (Document $record) => route('documents.telecharger', $record))
                    ->openUrlInNewTab(),
                // Bouton rapide demandé par le client pour suivre le retrait
                // des diplômes/attestations sans passer par le formulaire de
                // modification — voir Document::STATUT_RETRAIT_*.
                Action::make('marquer_retire')
                    ->label('Marquer comme retiré')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Document $record) => ! $record->trashed()
                        && in_array($record->type_document_id, $typeDiplomeAttestationIds, true)
                        && ! $record->estRetire())
                    ->action(function (Document $record) {
                        $record->update([
                            'statut_retrait' => Document::STATUT_RETRAIT_RETIRE,
                            'date_retrait' => now(),
                            'retrait_enregistre_par_id' => Auth::id(),
                        ]);
                    }),
                Action::make('annuler_retrait')
                    ->label('Annuler le retrait')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn (Document $record) => ! $record->trashed()
                        && in_array($record->type_document_id, $typeDiplomeAttestationIds, true)
                        && $record->estRetire())
                    ->action(function (Document $record) {
                        $record->update([
                            'statut_retrait' => Document::STATUT_RETRAIT_NON_RETIRE,
                            'date_retrait' => null,
                            'retrait_enregistre_par_id' => null,
                        ]);
                    }),
                EditAction::make()
                    ->visible(fn (Document $record) => ! $record->trashed()),
                DeleteAction::make()
                    ->label('Mettre à la corbeille')
                    ->visible(fn (Document $record) => ! $record->trashed()),
                RestoreAction::make()
                    ->visible(fn (Document $record) => $record->trashed() && ResourceAccess::can('documents', 'delete')),
                ForceDeleteAction::make()
                    ->label('Supprimer définitivement')
                    ->visible(fn (Document $record) => $record->trashed() && ResourceAccess::can('documents', 'delete')),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Mettre à la corbeille')
                        ->visible(fn () => ResourceAccess::can('documents', 'delete')),
                    RestoreBulkAction::make()
                        ->visible(fn () => ResourceAccess::can('documents', 'delete')),
                    ForceDeleteBulkAction::make()
                        ->label('Supprimer définitivement')
                        ->visible(fn () => ResourceAccess::can('documents', 'delete')),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDocuments::route('/'),
            'create' => Pages\CreateDocument::route('/create'),
            'edit' => Pages\EditDocument::route('/{record}/edit'),
        ];
    }

    /**
     * Recherche globale (barre en haut du panneau) : permet de retrouver un
     * document depuis n'importe quel écran, par description ou nom de
     * fichier d'origine — c'est le moyen le plus rapide de "trouver
     * facilement les documents" sans passer par la liste et ses filtres.
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['description', 'nom_original', 'nom_titulaire', 'numero_diplome', 'theme_memoire'];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()
            ->with(['typeDocument', 'sousTypeDocument', 'filiere', 'niveau', 'anneeAcademique']);
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Document $record */
        $details = [
            'Type' => $record->typeDocument?->libelle,
        ];

        if ($record->sousTypeDocument) {
            $details['Sous-type'] = $record->sousTypeDocument->libelle;
        }

        if ($record->filiere) {
            $details['Filière'] = $record->filiere->libelle;
        }

        if ($record->niveau) {
            $details['Niveau'] = $record->niveau->libelle;
        }

        if ($record->anneeAcademique) {
            $details['Année académique'] = $record->anneeAcademique->libelle;
        }

        if ($record->semestreLibelle()) {
            $details['Semestre'] = $record->semestreLibelle();
        }

        if ($record->nom_titulaire) {
            $details['Titulaire'] = $record->nom_titulaire;
        }

        if ($record->numero_diplome) {
            $details['N° diplôme'] = $record->numero_diplome;
        }

        if ($record->theme_memoire) {
            $details['Thème du mémoire'] = $record->theme_memoire;
        }

        if ($record->statutRetraitLibelle()) {
            $details['Retrait'] = $record->statutRetraitLibelle();
        }

        return $details;
    }
}
