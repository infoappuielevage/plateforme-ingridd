# Plateforme INGRIDD — Archives & Documents

Outil **indépendant** de gestion documentaire (classement, recherche,
téléchargement contrôlé de documents), construit avec Laravel 12 + Filament
v3. Ce projet est séparé d'`ingridd-platform` (gestion des étudiants et
soutenances) : il ne le remplace pas et ne couvre pas son périmètre.

## Installation

> ⚠️ Comme pour `ingridd-platform`, ce code a été écrit à la main sans accès
> réseau à packagist.org (bloqué dans l'environnement où il a été généré) :
> `vendor/` n'existe pas encore. Les commandes ci-dessous doivent être
> exécutées sur votre machine, où l'accès réseau fonctionne normalement.

```bash
composer install
cp .env.example .env
php artisan key:generate
```

> **Base de données : MySQL par défaut** (voir "Choix techniques" ci-dessous
> pour pourquoi). Créez d'abord la base, par exemple depuis phpMyAdmin/HeidiSQL
> (fourni avec Laragon/XAMPP/WampServer) ou en ligne de commande :
> `CREATE DATABASE plateforme_ingridd CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`
> — puis vérifiez que `DB_DATABASE`/`DB_USERNAME`/`DB_PASSWORD` dans `.env`
> correspondent à votre installation (les valeurs par défaut de `.env.example`
> conviennent à une installation Laragon/XAMPP standard : utilisateur `root`
> sans mot de passe). Si vous préférez SQLite (pas de serveur à installer),
> voir les instructions dans `.env.example` — attention toutefois à ne pas
> placer `database/database.sqlite` dans un dossier synchronisé par
> OneDrive/Google Drive/Dropbox (voir `config/database.php`).

```bash
php artisan migrate --seed
php artisan storage:link
php artisan filament:assets      # publie le CSS/JS de Filament — sans cette étape, le panneau s'affiche sans style
npm install
npm run build
php artisan serve
```

> `php artisan filament:assets` est maintenant relancé automatiquement à chaque `composer update` (voir `composer.json`), mais doit être exécuté manuellement après la toute première installation.

Un compte administrateur de démarrage est créé par le seeder :
- Email : `admin@ingridd.local`
- Mot de passe : `password`

**Changez ce mot de passe immédiatement** (menu utilisateur → Mon profil).

### Données de démonstration (optionnel)

Pour avoir tout de suite un aperçu de la plateforme (tableau de bord,
listes, onglets par famille de type, statistiques, écran "Corbeille")
plutôt qu'un panneau vide, générez une centaine de documents de
démonstration :

```bash
php artisan db:seed --class=DocumentSeeder
```

Ce sont de fausses données (fichiers de remplacement, pas de vrais
documents) — voir le docblock de `database/seeders/DocumentSeeder.php`.
C'est pour ça que cette commande n'est **pas** incluse dans `--seed` ni
rejouée par un `migrate:fresh --seed` : à lancer uniquement quand vous en
avez besoin. Chaque document généré porte la mention "Document de
démonstration" dans sa description — cherchez ce mot dans la recherche
globale du panneau (ou le champ de recherche de la liste Documents) pour
tous les retrouver et les supprimer d'un coup avant une mise en production
réelle. Relancer la commande une seconde fois ne duplique rien (elle
s'arrête d'elle-même si des documents de démonstration existent déjà).

## Périmètre fonctionnel (v1)

- **Documents** : ajout (fichier + type + sous-type + description),
  modification, suppression, téléchargement contrôlé (jamais d'URL
  publique — voir `routes/web.php`). Un document peut en plus être rattaché
  à une **filière**, un **niveau** et une **année académique** — trois listes
  déroulantes (pas de saisie libre, pour éviter les doublons proches comme
  "2025-2026" vs "2025 - 2026") — utile pour les listes d'étudiants, relevés
  de notes, PV de délibération, etc., mais facultatif pour les autres
  documents (administratifs, financiers...). Pour un **relevé de notes**
  précisément, un champ **Semestre** apparaît automatiquement dans le
  formulaire, avec des options qui dépendent du **niveau** choisi (numérotation
  LMD continue sur les 5 années : Licence 1 = Semestre 1/2, Licence 2 =
  Semestre 3/4, Licence 3 = Semestre 5/6, Master 1 = Semestre 7/8, Master 2 =
  Semestre 9/10 — voir `App\Support\Semestres`). Pour un **diplôme** ou une
  **attestation** (deux types de document distincts, pour un suivi et des
  statistiques séparés), des champs dédiés apparaissent de la même façon :
  nom du titulaire (obligatoire), numéro de diplôme/d'enregistrement, date
  de délivrance, mention, et un **statut de retrait** (Non retiré/Retiré)
  avec un bouton "Marquer comme retiré" directement dans la liste — pour
  suivre en un clic quels diplômes/attestations restent à récupérer par les
  étudiants (voir `App\Support\TypesDiplome`). Pour un **PV de soutenance**
  (numérisation du registre papier des soutenances de mémoire), des champs
  dédiés apparaissent de la même façon : nom de l'étudiant (obligatoire),
  thème du mémoire, date de soutenance, membres du jury et mention — les
  deux derniers champs (nom de l'étudiant, mention) sont les mêmes colonnes
  que pour les diplômes/attestations, réutilisées telles quelles ; pas de
  suivi de retrait pour ce type (voir `App\Support\TypesPvSoutenance`).
  Le référentiel des **types de documents** a été entièrement refondu à
  partir de la liste réelle recensée par le client (26 types : Attestation,
  Autorisation de congé, Bilan des cours en ligne, Bordereau, Candidat BTS,
  Cahier pédagogique, Cahier des charges (Ministère), Dossier extension de
  filière, Conseil d'établissement, Conseil scientifique, Contrats,
  Correspondance et lettre de partenariat, Délibération, Notice de
  renseignement, Ordre de mission, Relevés de notes, PV réunions, Syllabus
  des filières, UNUPEL, Canevas de rapport de rentrée, Liste du personnel,
  Listes des diplômés, Note d'information, Organigramme INGRIDD, Spot télé
  et radio, PV de soutenance — plus "Autre" en filet de sécurité). Certains
  types ont en plus un **sous-type** (ex. Attestation → Succès / Vacation /
  Inscription / Niveau / Recommandation / Suspension de scolarité / Travail
  / Correction ; Bordereau → BNSP / CNSS / Coris Banque / FONER /
  Ministère / UNB...) : liste gérée par type depuis Administration >
  Référentiels > Types de documents > (ouvrir un type) > onglet
  "Sous-types" — extensible sans toucher au code, y compris pour une liste
  volontairement ouverte comme Bordereau — voir `App\Models\SousTypeDocument`.
  Le champ "Sous-type" du formulaire d'un document n'apparaît que si le type
  choisi en a au moins un actif. La liste des documents (menu "Documents")
  est en plus organisée en **onglets par famille de type** (Tous / Académique /
  Diplômes-Attestations / Gouvernance / RH-Personnel / Administratif) pour
  faciliter la gestion sur une liste aussi longue — voir
  `ListDocuments::getTabs()`. Recherche et filtres par type/sous-type/filière/niveau/
  année académique/semestre/statut de retrait/**date d'ajout**, plus une recherche globale
  (barre en haut du panneau, sur description/nom de fichier/nom du
  titulaire/numéro de diplôme/thème du mémoire) pour retrouver un document depuis n'importe
  quel écran. La date et l'auteur de l'ajout, ainsi que la date et l'auteur
  de la dernière modification, sont affichés directement dans la liste
  (colonnes "Ajouté par/le" et "Modifié par/le" — l'historique complet,
  plus bas, détaille en plus chaque changement individuel).
- **Menu "Secrétariat"** : sur demande explicite du client, une page dédiée
  par type de document (26+ entrées, y compris les types désactivés —
  Liste des étudiants/Diplôme/Facture/Rapport — pour retrouver facilement
  les documents déjà classés dedans) — chacune un tableau **dissocié** des
  autres types, plutôt qu'une seule liste avec des onglets. Le type
  "Attestation", qui a plusieurs sous-types (Succès, Vacation,
  Inscription...), reçoit en plus une page par sous-type plutôt qu'une
  seule page "Attestation". Le formulaire d'ajout/modification reste
  entièrement partagé avec le menu "Documents" habituel (même formulaire,
  mêmes colonnes/filtres/actions) — voir `App\Filament\Pages\DocumentsParType`
  et `AdminPanelProvider::elementsNavigationSecretariat()`.
- **Tableau de bord** : chiffres clés (documents actifs, ajoutés ce mois-ci,
  espace de stockage utilisé, types actifs), plus — pour qui a le
  droit de supprimer des documents — le nombre de documents en corbeille, et
  — pour l'admin — l'état de la dernière sauvegarde (alerte si aucune, ou si
  elle date de plus d'une semaine), et — si le référentiel des types
  contient "Diplôme"/"Attestation" — le nombre de diplômes/attestations pas
  encore retirés. En dessous : tendance des ajouts sur les 12 derniers mois,
  répartition par type/filière, la répartition retiré/non retiré
  des diplômes et attestations, et les dernières actions de l'historique
  (qui a ajouté/modifié/supprimé quoi, et quand) — voir `app/Filament/Widgets/`.
- **Sélection multiple et actions groupées** : chaque écran de liste
  (Documents et les quatre référentiels) permet de cocher plusieurs lignes
  puis d'agir sur toutes en une fois — mettre à la corbeille/restaurer/
  supprimer définitivement pour les documents, activer/désactiver/mettre à
  la corbeille/restaurer/supprimer définitivement pour les référentiels.
  Une ligne de référentiel encore utilisée par un document n'est jamais
  mise à la corbeille, même via une action groupée (les autres lignes
  sélectionnées le sont quand même, avec un résumé en notification).
- **Corbeille (documents et référentiels)** : supprimer un document ou une
  ligne de référentiel (type, filière, niveau, année académique)
  la met à la corbeille plutôt que de l'effacer tout de suite (filtre
  "Corbeille" en haut de chaque liste, comme un filtre normal). Depuis la
  corbeille : restaurer, ou supprimer définitivement (c'est uniquement à ce
  moment-là, pour un document, que le fichier physique est réellement
  effacé). Un référentiel encore utilisé par au moins un document (même mis
  à la corbeille) ne peut jamais être mis à la corbeille lui-même — ce
  garde-fou, déjà en place avant l'ajout de la corbeille aux référentiels,
  est inchangé.
  Pour les **documents** spécifiquement, un accès direct existe aussi dans
  le menu (groupe de navigation "Corbeille", réservé à qui peut supprimer
  des documents), plutôt que de devoir activer le filtre depuis l'écran
  Documents : sélection multiple, restauration/suppression groupées, et un
  bouton "Vider la corbeille" qui supprime définitivement tout son contenu
  en un clic (avec confirmation, action irréversible) — voir
  `App\Filament\Resources\CorbeilleResource`.
- **Types de documents**, **Filières**, **Niveaux** et
  **Années académiques** : quatre référentiels simples, modifiables sans
  toucher au code (Administration → Référentiels). Les années académiques
  ont un format imposé (`AAAA-AAAA`, ex. `2025-2026`) validé à la création.
- **Rôles & permissions** : trois rôles de départ (`admin`, `gestionnaire`,
  `consultation`), droits modifiables finement par ressource/action depuis
  l'interface (Administration → Rôles & permissions). Le rôle `admin` a
  toujours tous les droits, même si sa ligne en base est mal configurée.
- **Utilisateurs** et **Rôles & permissions** sont volontairement réservés
  en dur au rôle `admin` (voir le docblock d'`App\Support\ResourceAccess`) :
  un rôle mal configuré ne peut jamais s'accorder lui-même les pleins
  pouvoirs.
- **Historique (audit)** : trace automatique de toute création/modification/
  suppression de document ou de référentiel, ainsi que des connexions —
  lecture seule, purge automatique après une durée réglable (voir
  "Paramètres" ci-dessous, 2 ans par défaut).
- **Mon profil** : nom, email, mot de passe, photo de profil (PNG/JPEG/WebP
  uniquement — les SVG sont explicitement refusés, un format SVG pouvant
  contenir du code exécutable : voir le docblock d'`App\Filament\Pages\ModifierProfil`).
- **Paramètres** (Administration → Paramètres, réservé à l'admin) : nom de
  l'application, logo, couleur principale, **types de fichiers acceptés**
  (familles activables/désactivables — documents, tableurs, présentations,
  texte/CSV, images, archives), taille maximale des documents et des photos
  de profil, durée avant purge de l'historique, **fréquence de la
  sauvegarde automatique** (quotidienne/hebdomadaire/mensuelle), nombre de
  sauvegardes conservées, chemin de l'exécutable `mysqldump` (à renseigner
  seulement s'il n'est pas dans le PATH système) — tout est modifiable
  après le déploiement, sans jamais retoucher au code ni redéployer. Voir
  `App\Models\Parametre`, `App\Filament\Pages\Parametres` et
  `App\Support\TypesFichiers`.
- **Sauvegardes** (Administration → Sauvegardes, réservé à l'admin) : une
  sauvegarde = une archive .zip contenant la base de données (export
  `mysqldump` sur MySQL, copie du fichier sur SQLite) et tous les
  documents. Bouton "Créer une sauvegarde maintenant", téléchargement,
  suppression manuelle, et une sauvegarde automatique planifiée selon la
  fréquence choisie dans Paramètres (voir `routes/console.php` —
  nécessite que le planificateur Laravel tourne réellement sur le
  serveur, voir "Choix techniques" ci-dessous). La commande échoue
  clairement (au lieu de produire une archive incomplète) si la base n'a
  pas pu être incluse — voir `App\Console\Commands\CreerSauvegarde`. Les
  plus anciennes sont purgées automatiquement au-delà du nombre à conserver
  (réglable dans Paramètres). Voir aussi `App\Filament\Pages\Sauvegardes`.

## Choix techniques

- **Base de données : MySQL** par défaut. Le choix initial (SQLite, comme
  `ingridd-platform` et comme le comportement par défaut de Laravel 11+) a été
  abandonné après un incident constaté en usage réel : le mode WAL de SQLite
  (nécessaire pour de bonnes performances en écriture) est incompatible avec
  les dossiers synchronisés par OneDrive — très fréquent pour un projet placé
  sous `Bureau/Desktop` sur Windows — et provoque une erreur "disk I/O error"
  dès la connexion à la base. MySQL (serveur séparé, pas de fichier à
  synchroniser) évite ce problème et gère mieux les accès concurrents entre
  plusieurs utilisateurs du panneau Filament. SQLite reste utilisable (voir
  `.env.example`) pour une installation sans serveur MySQL disponible, à
  condition que `database/database.sqlite` ne soit pas dans un dossier
  synchronisé par un client cloud (OneDrive/Google Drive/Dropbox).
- **Stockage des fichiers** : disque `documents` **privé** (jamais servi
  directement par le serveur web) — voir `config/filesystems.php` et la route
  authentifiée de `routes/web.php`, qui vérifie les droits via
  `ResourceAccess` avant de transmettre le fichier.
- **Historique/permissions** : mêmes classes de base qu'`ingridd-platform`
  (`ResourceAccess`, `HasRoleBasedAccess`, `Auditable`), adaptées au
  périmètre plus restreint de cet outil.
- **Paramètres modifiables sans redéploiement** : tout ce qui a une bonne
  chance de devoir changer après la mise en production (nom affiché, logo,
  couleur, limites d'upload, rétention de l'historique) est stocké en base
  (`App\Models\Parametre`, une seule ligne) plutôt que codé en dur, avec un
  écran dédié réservé à l'admin. `Parametre::actuel()` relit toujours la
  base (pas de mise en cache — un essai de cache avait causé plus de
  problèmes qu'il n'en résolvait, voir l'historique du projet) et se protège
  si la table n'existe pas encore (première installation, avant la première
  migration).
- **Fichiers orphelins** : le fichier physique d'un document (ou d'une
  ancienne photo de profil) est supprimé du disque dès que la ligne
  correspondante est remplacée, ou supprimée **définitivement** — pas lors
  d'une simple mise à la corbeille, sans quoi restaurer un document ne
  servirait à rien (voir `Document::booted()`, `EditDocument::afterSave()`,
  `ModifierProfil::enregistrer()`) — sans cette purge différée,
  `storage/app/documents` grossirait indéfiniment.
- **Corbeille (documents et référentiels)** : `Document` et les quatre
  référentiels (Type de document, Filière, Niveau, Année
  académique) utilisent tous `SoftDeletes` (colonne `deleted_at`) — sur
  demande du client, pour le même filet de sécurité partout. Pour les
  référentiels, le garde-fou déjà en place avant cet ajout (suppression
  bloquée tant qu'un document les utilise encore, y compris un document
  corbeillé — voir `->documents()->withTrashed()->doesntExist()` dans
  chaque Resource et `HasReferentielBulkActions`) n'a pas changé de nature :
  il protège maintenant la mise à la corbeille elle-même plutôt qu'une
  suppression définitive. Conséquence utile : un référentiel ne peut donc
  jamais être corbeillé tant qu'il est utilisé, ce qui évite tout cas où un
  document existant se retrouverait à pointer vers un référentiel
  corbeillé (aucune relation `Document → référentiel` n'a donc besoin d'un
  `->withTrashed()` particulier pour continuer à s'afficher correctement).
- **Corbeille des documents dans le menu** : `App\Filament\Resources\CorbeilleResource`
  est une seconde Resource Filament sur le même modèle `Document` que
  `DocumentResource`, dans son propre groupe de navigation "Corbeille" —
  uniquement un écran de liste (`getEloquentQuery()` en `onlyTrashed()`),
  sans création ni modification. Réservée à qui peut supprimer des
  documents (`ResourceAccess::can('documents', 'delete')`), pas seulement
  les voir. "Vider la corbeille" (`Pages\ListCorbeille::getHeaderActions()`)
  parcourt chaque document et appelle `forceDelete()` individuellement
  plutôt qu'une suppression en masse en une seule requête SQL : c'est ce qui
  déclenche, pour chacun, l'effacement du fichier physique
  (`Document::booted()`) et la trace dans l'historique (`Auditable`) — une
  requête de masse ne déclenche pas les événements Eloquent nécessaires. Le
  téléchargement (`routes/web.php`) résout désormais le document avec
  `Document::withTrashed()->findOrFail(...)` plutôt que par liaison de route
  implicite (qui exclut les documents corbeillés par défaut) : sans ce
  correctif, le bouton "Télécharger" échouait (404) sur un document
  corbeillé, y compris déjà depuis le filtre "Corbeille" de `DocumentResource`.
- **Traçabilité** : `App\Models\Concerns\Auditable` (posé sur tous les
  modèles métier) journalise automatiquement chaque création/modification/
  suppression dans `audit_logs`, avec l'auteur et le détail des champs
  changés (Administration → Historique). Pour `Document`, la mise à la
  corbeille et la suppression définitive sont distinguées dans
  l'historique, de même que la restauration. `uploaded_by`/`modifie_par`
  sur `documents` affichent en plus, directement sur chaque document, qui
  l'a ajouté / modifié en dernier — sans avoir à aller consulter
  l'historique pour cette information de base.
- **Types de fichiers acceptés pour les documents** : liste blanche
  volontaire (PDF, Office, texte, CSV, image, ZIP) plutôt qu'une liste noire
  toujours incomplète — voir `DocumentResource::form()`. Le téléchargement
  force de toute façon `Content-Disposition: attachment` (voir
  `routes/web.php`), donc même un fichier HTML/SVG ne peut pas s'exécuter
  dans le navigateur ; la liste blanche est une protection supplémentaire.
- **Année académique en liste déroulante** : à l'origine une simple chaîne
  de caractères sur `documents`, remplacée par une clé étrangère vers un
  référentiel `AnneeAcademique` (voir la migration
  `2026_09_20_000010_...`, qui reprend automatiquement les valeurs déjà
  utilisées par des documents existants avant de supprimer l'ancienne
  colonne — aucune donnée perdue en cas de ré-exécution sur une base déjà
  en service).
- **Semestre des relevés de notes** : `documents.semestre` (facultatif,
  numéroté de 1 à 10). Le champ n'apparaît dans le formulaire que lorsque le
  type de document sélectionné est "Relevé de notes" (recherché par son
  `code`, voir `DocumentResource::form()`) — les autres types de documents
  ne sont pas concernés. Les options proposées dépendent en plus du
  **niveau** choisi sur le même document (L1 = S1/S2 ... M2 = S9/S10, voir
  `App\Support\Semestres`) : le champ reste désactivé tant qu'aucun niveau
  reconnu n'est sélectionné. Numérotation LMD standard, volontairement pas
  un réglage d'Administration (contrairement aux familles de fichiers
  acceptées, par exemple) : ce n'est pas un choix propre à l'établissement.
- **Diplômes et attestations séparés en deux types** : auparavant un seul
  type "Diplôme / attestation" (code `DIPLOME`) ; sur demande du client,
  `database/seeders/TypeDocumentSeeder.php` seede désormais deux types
  distincts (`DIPLOME` et `ATTESTATION`), pour pouvoir les compter/filtrer
  séparément. Sur une installation déjà en service, les documents déjà
  classés en "Diplôme" avant ce changement restent tels quels (le
  `updateOrCreate` par `code` ne fait que renommer le libellé existant) ; à
  reclasser manuellement en "Attestation" au cas par cas si besoin. Les
  champs complémentaires (`nom_titulaire`, `numero_diplome`,
  `date_delivrance`, `mention`, `statut_retrait`, `date_retrait`,
  `retrait_enregistre_par_id`) sur `documents` ne sont affichés/enregistrés
  que pour ces deux types précis, recherchés par leur `code` — voir
  `App\Support\TypesDiplome` et `DocumentResource::form()`/`table()` (même
  principe défensif que pour le semestre des relevés de notes ci-dessus :
  si les deux types sont renommés/supprimés, ces champs et les widgets
  associés restent simplement masqués, jamais d'erreur). Le statut de
  retrait peut être changé soit depuis le formulaire de modification, soit
  en un clic via le bouton "Marquer comme retiré"/"Annuler le retrait" dans
  la liste des documents ; les deux gardent `date_retrait` et
  `retrait_enregistre_par_id` cohérents avec le statut (nettoyés si on
  repasse à "Non retiré").
- **PV de soutenance** : nouveau type de document (code `PV_SOUTENANCE`),
  pour numériser le registre papier des soutenances de mémoire (demande du
  client). Trois colonnes propres à ce type (`theme_memoire`,
  `date_soutenance`, `membres_jury`, migration `2026_09_21_000016_...`) ;
  réutilise `nom_titulaire` et `mention`, déjà présents sur `documents` pour
  les diplômes/attestations, plutôt que d'ajouter deux colonnes
  redondantes — voir `App\Support\TypesPvSoutenance` et
  `DocumentResource::form()` (même principe défensif que pour le semestre
  ou les champs diplôme/attestation : type renommé/supprimé → champs
  simplement masqués, jamais d'erreur). Volontairement pas de suivi de
  retrait/archivage pour ce type (contrairement aux diplômes/attestations) :
  non demandé par le client pour l'instant, facile à ajouter plus tard sur
  le même modèle si besoin. Un même étudiant peut avoir plusieurs PV de
  soutenance enregistrés (ex. rattrapage) : aucune contrainte d'unicité
  n'a été posée, ce point restant ouvert à préciser avec le client.
- **Onglets par famille de type sur la liste des documents** :
  `ListDocuments::getTabs()` regroupe les types en cinq onglets (Académique,
  Diplômes/Attestations, Gouvernance, RH/Personnel, Administratif, plus
  "Tous") plutôt qu'un onglet par type exact (26+, bien trop chargé pour un
  gain limité vu que le filtre "Type" existe déjà). Regroupement basé sur le
  `code` des types, en dur dans le code plutôt qu'un réglage d'administration
  (disproportionné pour ce besoin) : un type nouvellement créé dont le
  `code` n'est dans aucune des listes n'apparaît que dans l'onglet "Tous" —
  limite connue et acceptée de ce classement fixe, à ajuster si un usage
  réel en montre le besoin.
- **Menu "Secrétariat" : une page par type de document** : sur demande
  explicite du client ("que chaque type de document ait son tableau
  dissocié des autres"), en complément des onglets ci-dessus — voir
  `App\Filament\Pages\DocumentsParType`. UNE SEULE classe de page pour
  toutes ces entrées (pas une classe par type) : le type — et, pour
  Attestation, le sous-type — vient de paramètres de route (`{code}` et
  `{sousType}` sur `secretariat/{code}/{sousType}`), lus dans `mount()`.
  `{sousType}` est un segment OBLIGATOIRE (jamais `{sousType?}`), même pour
  les types qui n'en ont pas besoin — voir `DocumentsParType::AUCUN_SOUS_TYPE`
  et le docblock de `getSlug()` : un paramètre de route optionnel absent de
  l'URL fait planter Livewire ("Cannot use positional argument after named
  argument"), un bug connu de l'écosystème Livewire/Filament sur ce type de
  route, pas une erreur spécifique à ce projet — contourné en rendant le
  segment obligatoire et en fournissant toujours une valeur, réelle ou
  `AUCUN_SOUS_TYPE`. Les entrées du menu elles-mêmes sont construites dynamiquement à partir du
  référentiel `TypeDocument` (et `SousTypeDocument` pour Attestation) dans
  `AdminPanelProvider::elementsNavigationSecretariat()`, recalculées à
  chaque requête (pas de cache) : toute modification du référentiel depuis
  l'administration se reflète immédiatement dans ce menu. Le tableau de
  chaque page réutilise intégralement `DocumentResource::table()` (mêmes
  colonnes/filtres/actions, y compris "Modifier" qui ouvre le formulaire
  habituel de `DocumentResource`) avec juste une requête de base restreinte
  au type/sous-type de la page — le formulaire d'ajout/modification reste
  donc un seul et même formulaire partagé, jamais dupliqué. Les types
  désactivés (Liste des étudiants/Diplôme/Facture/Rapport) gardent eux
  aussi leur page, sur demande explicite du client, pour retrouver
  facilement les documents déjà classés dedans même si on ne peut plus en
  créer de nouveaux avec ce type.
- **Suppression du champ "Titre" et du référentiel "Catégorie"** : sur
  demande explicite du client (un titre libre faisait doublon avec le nom du
  fichier d'origine déjà conservé dans `nom_original`, et la catégorie
  n'était pas utilisée dans la pratique — le classement se fait désormais
  uniquement par type/sous-type). Retirés par deux nouvelles migrations
  (`2026_09_21_000019_...` puis `2026_09_21_000020_...`, jamais en modifiant
  les migrations déjà existantes) plutôt que codés en dur : le référentiel
  `Categorie` (modèle, Resource Filament, seeder) a été entièrement retiré
  du projet.
- **Refonte complète du référentiel des types de documents** :
  `TypeDocumentSeeder` a été entièrement réécrit à partir de la liste réelle
  recensée par le client (voir son docblock pour le détail). Toujours
  `updateOrCreate(['code' => ...], [...])`, jamais de `delete()` : les codes
  déjà en base et réutilisés (`ATTESTATION`, `PV_SOUTENANCE`,
  `PV_DELIBERATION` → "Délibération", `CONTRAT` → "Contrats", `COURRIER` →
  "Correspondance et lettre de partenariat", `RELEVE_NOTES` → "Relevés de
  notes") sont simplement renommés, aucun document déjà classé ne perd son
  type. Les codes sans équivalent évident dans la nouvelle liste
  (`LISTE_ETUDIANTS`, `DIPLOME`, `FACTURE`, `RAPPORT`) sont désactivés
  (`actif = false`), jamais supprimés — les documents déjà classés dedans
  restent consultables/modifiables tels quels, seuls les nouveaux documents
  ne peuvent plus choisir ce type précis. `ATTESTATION` et `PV_SOUTENANCE`
  gardent leurs champs dédiés existants inchangés (voir plus haut) ; sur
  demande explicite du client, ces champs dédiés n'ont PAS été étendus aux
  nouveaux types équivalents (`LISTE_DIPLOME`, "Délibération") — ces
  derniers n'ont que les champs communs plus un sous-type.
- **Sous-types de document** : certains types ont une sous-catégorie (ex.
  Attestation → Succès/Vacation/Inscription..., Bordereau →
  BNSP/CNSS/Coris Banque...) — sur demande explicite du client, gérée comme
  un vrai référentiel (table `sous_types_documents`, migration
  `2026_09_21_000017_...`), pas une liste figée dans le code : certaines
  listes sont explicitement ouvertes (ex. Bordereau, "etc." dans la demande
  du client), donc un admin doit pouvoir en ajouter une sans déploiement.
  Géré depuis la fiche d'un type de document (Administration > Référentiels
  > Types de documents > ouvrir un type > onglet "Sous-types" — voir
  `App\Models\SousTypeDocument` et
  `TypeDocumentResource\RelationManagers\SousTypesRelationManager`), pas
  depuis un écran séparé : un sous-type n'a de sens que rattaché à son type.
  `documents.sous_type_document_id` (migration `2026_09_21_000018_...`) est
  facultatif et `->nullOnDelete()` plutôt que protégé par un garde-fou "encore
  utilisé" comme les référentiels de premier niveau : le champ étant déjà
  optionnel, supprimer un sous-type ne peut jamais laisser un document dans
  un état invalide, au pire son sous-type redevient vide — pas de corbeille
  ici non plus, pour la même raison. Sur le formulaire d'un document, le
  champ "Sous-type" (`DocumentResource::form()`) n'apparaît que si le type
  choisi a au moins un sous-type actif, et se réinitialise automatiquement
  si l'utilisateur change ensuite de type (sinon il garderait silencieusement
  un id appartenant à l'ancien type).
- **Sauvegardes sans dépendance externe** : `App\Console\Commands\CreerSauvegarde`
  construit l'archive .zip avec `ZipArchive` (extension PHP standard,
  généralement déjà installée) plutôt qu'un package comme
  spatie/laravel-backup. La base de données est incluse via `mysqldump`
  (MySQL — cherché dans le PATH système, ou au chemin renseigné dans
  Administration → Paramètres si besoin) ou une copie directe du fichier
  (SQLite) selon la connexion active (voir `config/database.php`) ; si la
  base n'a pas pu être incluse (ex. `mysqldump` introuvable), la commande
  échoue clairement plutôt que de produire une archive incomplète en
  silence — une sauvegarde sans base de données ne permettrait pas de
  restaurer grand-chose. Écrite sur un disque privé dédié (`sauvegardes`,
  voir `config/filesystems.php`), jamais dans un dossier public.
  ⚠️ La sauvegarde **automatique** (fréquence réglable dans Paramètres :
  quotidienne/hebdomadaire/mensuelle) ne se déclenche que si le
  planificateur Laravel tourne réellement sur le serveur — en
  développement : `php artisan schedule:work` ; en production : une tâche
  cron ou le Planificateur de tâches Windows qui appelle
  `php artisan schedule:run` chaque minute (voir la documentation Laravel
  "Task Scheduling"). Sans ça, seul le bouton "Créer une sauvegarde
  maintenant" (Administration → Sauvegardes) fonctionne. Pour un très gros
  volume de documents, la création peut devenir lente (elle s'exécute
  pendant la requête web quand elle est déclenchée depuis l'écran) —
  passer par une file d'attente (queue) serait alors préférable, hors
  périmètre de cette v1.

## Tests automatisés

```bash
php artisan test
```

- `ModifierProfilTest` — un SVG est refusé comme photo de profil, un PNG est accepté.
- `AccesRessourcesTest` — un rôle sans permission n'accède pas aux écrans concernés ; un rôle avec la permission "Voir" y accède.
- `ReferentielResourcesTest` — création d'un type de document, d'une filière, d'un niveau, d'une année académique, d'un rôle avec permissions ; une année académique mal formatée est rejetée ; les actions groupées activent/désactivent des types de document (référentiel représentatif des quatre) ; la mise à la corbeille groupée ignore les types encore utilisés ; un type inutilisé peut être mis à la corbeille puis restauré, ou supprimé définitivement une fois corbeillé ; le bouton "Mettre à la corbeille" est masqué pour un type encore utilisé ; les permissions d'un rôle se rechargent correctement en édition ; tous les écrans de liste des référentiels s'affichent sans erreur, y compris avec une ligne corbeillée.
- `CorbeilleTest` — l'écran "Corbeille" du menu n'affiche que les documents supprimés ; un document peut y être restauré ou supprimé définitivement (fichier physique compris) ; le bouton "Vider la corbeille" supprime définitivement tout son contenu sans toucher aux documents actifs, et reste masqué quand la corbeille est vide ; l'écran n'est accessible qu'à qui peut supprimer des documents, pas seulement les voir.
- `DocumentSeederTest` — sans administrateur ni référentiels, `DocumentSeeder` ne génère rien ; avec les deux, il crée des documents variés (dont une attestation avec titulaire/numéro/statut de retrait/sous-type renseignés, un PV de soutenance avec titulaire/thème/date de soutenance/jury renseignés et sans statut de retrait, un bordereau avec sous-type renseigné, un document classé dans un ancien type retiré du référentiel comme Diplôme, un fichier physique réel, les deux statuts de retrait représentés, et quelques documents dans la corbeille) ; le rejouer une seconde fois ne duplique rien.
- `TypeDocumentSeederTest` — après la refonte complète du référentiel des types (voir son docblock), les codes réutilisés (Attestation, PV de soutenance, Délibération, Contrats, Correspondance et lettre de partenariat, Relevés de notes) sont bien renommés et restent actifs ; les nouveaux types sont créés et actifs ; les types sans équivalent (Liste des étudiants, Diplôme, Facture, Rapport) sont désactivés, jamais supprimés ; le rejouer ne duplique rien.
- `SousTypeDocumentSeederTest` — les sous-types recensés (Attestation, Autorisation de congé, Bilan des cours en ligne, Bordereau, Délibération) sont bien rattachés à leur type ; ignoré silencieusement si le type parent n'existe pas ; le rejouer ne duplique rien.
- `SousTypeDocumentTest` — un sous-type appartient bien à son type ; supprimer un sous-type encore utilisé par un document vide simplement sa référence (pas de blocage, contrairement aux référentiels de premier niveau) ; supprimer définitivement un type supprime ses sous-types.
- `SousTypesRelationManagerTest` — seuls les sous-types du type de document ouvert sont affichés (pas ceux d'un autre type) ; un admin peut ajouter ou supprimer un sous-type depuis la fiche d'un type.
- `ParametresTest` — un admin peut modifier tous les paramètres (y compris fréquence de sauvegarde, chemin mysqldump, types de fichiers acceptés) ; un non-admin n'a pas accès à l'écran ; le formulaire précoche toutes les familles de fichiers si ce réglage n'a encore jamais été enregistré (régression testée explicitement, voir plus bas).
- `DocumentResourceTest` — ajout d'un document (avec ou sans filière/niveau/année académique) ; le semestre est enregistré selon le niveau pour un relevé de notes (ex. Licence 2 → Semestre 3) ; les champs complémentaires (titulaire, numéro, date de délivrance, mention, statut de retrait) sont enregistrés pour un diplôme, le nom du titulaire est obligatoire, et ces champs sont bien ignorés pour un autre type de document ; les champs complémentaires du PV de soutenance (titulaire, thème du mémoire, date de soutenance, membres du jury, mention) sont enregistrés, sans statut de retrait même forcé, et le nom du titulaire est obligatoire ; le sous-type est enregistré quand le type sélectionné en a, et ignoré (même forcé) pour un type qui n'en a pas ; le bouton "Marquer comme retiré"/"Annuler le retrait" met à jour le statut, la date et l'auteur du retrait ; repasser à "Non retiré" depuis le formulaire de modification nettoie bien la date et l'auteur ; le filtre par année académique et par date d'ajout fonctionne ; le modificateur est enregistré à la modification ; un document supprimé va à la corbeille sans perdre son fichier, peut être restauré, ou supprimé définitivement (le fichier est alors bien effacé) ; la mise à la corbeille groupée fonctionne ; téléchargement refusé sans droit de vue.
- `DocumentListTabsTest` — l'onglet "Académique" ne montre que les types académiques ; l'onglet "Diplômes / Attestations" ne montre que ces types ; l'onglet "Gouvernance" ne montre que les instances/dossiers institutionnels ; l'onglet "RH / Personnel" ne montre que les types RH ; l'onglet "Administratif" ne montre que le reste ; l'onglet "Tous" montre tout.
- `DocumentsParTypeTest` — la page dédiée d'un type ne montre que les documents de ce type ; la page dédiée d'un sous-type d'Attestation ne montre que ce sous-type précis ; la page d'un type désactivé (ex. Diplôme) reste accessible et montre les documents déjà classés dedans ; un code de type inconnu renvoie une erreur ; un rôle sans droit de vue sur les documents ne peut pas accéder à la page.
- `Unit\SemestresTest` — chaque niveau LMD (L1 à M2) a bien ses deux semestres ; un code de niveau inconnu ne propose aucun semestre ; les 10 semestres sont couverts.
- `TypesDiplomeTest` — `TypesDiplome::ids()` retourne un tableau vide sans référentiel "Diplôme"/"Attestation", les deux id quand les deux existent, et fonctionne même si un seul des deux existe.
- `TypesPvSoutenanceTest` — `TypesPvSoutenance::ids()` retourne un tableau vide sans référentiel "PV de soutenance", et son id quand il existe.
- `SauvegardesTest` — la commande crée une archive .zip et purge les plus anciennes au-delà du nombre à conserver ; un admin peut créer une sauvegarde depuis l'écran ; un non-admin n'y a pas accès ; **la commande échoue clairement (plutôt que de produire une archive sans base de données) si `mysqldump` est introuvable** — régression du bug découvert lors du passage à MySQL.
- `WidgetsTest` — tous les widgets du tableau de bord s'affichent sans erreur ; les chiffres (documents actifs, en corbeille, diplômes/attestations non retirés) sont corrects ; la carte "En corbeille" est masquée sans droit de suppression ; les widgets liés au retrait sont masqués sans type "Diplôme"/"Attestation" ; le graphique par filière et celui des retraits reflètent le nombre réel de documents ; l'activité récente affiche les dernières actions de l'historique.
- `Unit\TypesFichiersTest` — sans sélection, toutes les familles de fichiers sont acceptées ; une sélection limite bien aux familles choisies.
- `Unit\ParametreTest` — `doitSauvegarderAujourdhui()` respecte la fréquence choisie (quotidienne/hebdomadaire/mensuelle).

## Prochaines étapes possibles (hors périmètre de cette v1)

- Restauration d'une sauvegarde depuis l'interface (aujourd'hui : créer/télécharger/supprimer une sauvegarde, mais la restaurer se fait manuellement — remplacer `database/database.sqlite` et le contenu de `storage/app/documents` par ce que contient l'archive, application arrêtée).
- Sauvegarde par file d'attente (queue) pour un très gros volume de documents, plutôt qu'une exécution synchrone pendant la requête web — voir "Sauvegardes sans dépendance externe" ci-dessus.
- Export statistique des documents (nombre par type/filière/niveau/période) — voir `RapportStatistiques` sur `ingridd-platform` comme modèle possible.
- Recherche plein texte dans le contenu des documents (actuellement : recherche sur description/nom de fichier uniquement, pas sur le contenu des fichiers eux-mêmes).
