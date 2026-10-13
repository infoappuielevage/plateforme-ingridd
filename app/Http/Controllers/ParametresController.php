<?php

namespace App\Http\Controllers;

use App\Models\AnneeAcademique;
use App\Models\Document;
use App\Models\Parametre;
use App\Models\User;
use App\Support\TypesFichiers;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Réglages de la plateforme (une seule ligne en base, voir App\Models\Parametre)
 * — remplace App\Filament\Pages\Parametres. Réservé en dur au rôle admin, comme
 * la page Filament d'origine (pas de clé ResourceAccess ici : certains de ces
 * réglages, en particulier la rétention de l'historique, ne doivent jamais
 * pouvoir être accordés à un rôle par une simple mauvaise configuration des
 * permissions).
 *
 * Demande explicite du client ("je ne veux pas que tout soit sur la même
 * page") : cet écran, auparavant un unique formulaire géant, est désormais
 * découpé en sections indépendantes (voir self::SECTIONS), chacune avec son
 * propre sous-menu, son propre <form> et son propre enregistrement — plutôt
 * que de valider/réenregistrer TOUS les réglages à chaque sauvegarde, y
 * compris ceux qu'on ne regardait pas. Toujours une seule ligne/un seul
 * modèle Parametre en base (aucun changement de schéma) : seules la
 * présentation et la validation sont scindées par section.
 */
class ParametresController extends Controller
{
    /**
     * Clé de section => libellé affiché dans le sous-menu (voir
     * resources/views/gestion/parametres/index.blade.php). L'ORDRE de ce
     * tableau est celui du sous-menu.
     */
    public const SECTIONS = [
        'identite' => "Identité de l'application",
        'fichiers' => 'Fichiers & uploads',
        'doctorat' => 'Doctorat',
        'historique' => 'Historique (audit)',
        'verrouillage' => 'Verrouillage des documents',
        'sauvegardes' => 'Sauvegardes',
        'mail' => 'Configuration mail (SMTP)',
    ];

    public function index(?string $section = null): View
    {
        abort_unless(Auth::user()?->role === User::ROLE_ADMIN, 403);

        $section = $this->sectionValide($section);

        return view('gestion.parametres.index', [
            'parametres' => Parametre::actuel(),
            'familles' => TypesFichiers::optionsPourFormulaire(),
            // Uniquement utile à la section "verrouillage" (voir le @case
            // correspondant dans la vue), mais sans coût réel à charger à
            // chaque affichage — même principe que "familles" ci-dessus,
            // utilisée uniquement par la section "fichiers".
            'anneesAcademiques' => AnneeAcademique::query()->orderByDesc('libelle')->get(),
            // Idem : uniquement calculé pour la section "verrouillage" (4
            // requêtes COUNT supplémentaires, inutiles sur les 6 autres
            // sections) — voir statistiquesVerrouillage() ci-dessous.
            'statistiquesVerrouillage' => $section === 'verrouillage' ? $this->statistiquesVerrouillage() : null,
            'section' => $section,
        ]);
    }

    /**
     * Compteurs affichés en haut de la section "Verrouillage des documents"
     * — demande explicite du client ("améliore-la, de part ses
     * fonctionnalités et son design") : l'administration voit d'un coup
     * d'œil combien de documents sont concernés par chaque mécanisme, avant
     * de modifier les seuils. Reproduit en SQL la même logique que
     * App\Support\Verrouillage::estVerrouille() (manuel > seuils
     * automatiques > déverrouillage explicite), sans jamais charger les
     * documents eux-mêmes.
     *
     * Le seuil "par année académique" (comparaison lexicographique sur
     * `libelle`, voir Verrouillage::bloqueParAnnee()) est résolu via une
     * liste d'identifiants pré-calculée plutôt qu'une jointure SQL brute —
     * plus simple à lire et le référentiel des années académiques est de
     * toute façon minuscule.
     */
    protected function statistiquesVerrouillage(): array
    {
        $parametres = Parametre::actuel();

        $anneesConcernees = [];
        if ($parametres->verrouillage_annee_academique_id) {
            $seuil = AnneeAcademique::find($parametres->verrouillage_annee_academique_id);
            if ($seuil) {
                $anneesConcernees = AnneeAcademique::query()
                    ->where('libelle', '<=', $seuil->libelle)
                    ->pluck('id')
                    ->all();
            }
        }

        $seuilDate = $parametres->verrouillage_date;

        // Aucun des deux seuils actif : pas la peine d'interroger la base,
        // la réponse est 0 (et surtout, un where() sans condition à
        // l'intérieur matcherait TOUT — à éviter).
        $verrouillesAutomatiquement = 0;
        if ($anneesConcernees || $seuilDate) {
            $verrouillesAutomatiquement = Document::query()
                ->whereNull('verrouille_le')
                ->whereNull('deverrouille_le')
                ->where(function ($requete) use ($anneesConcernees, $seuilDate) {
                    if ($anneesConcernees) {
                        $requete->orWhereIn('annee_academique_id', $anneesConcernees);
                    }
                    if ($seuilDate) {
                        $requete->orWhere('created_at', '<', $seuilDate);
                    }
                })
                ->count();
        }

        return [
            'total' => Document::query()->count(),
            'manuel' => Document::query()->whereNotNull('verrouille_le')->count(),
            'automatique' => $verrouillesAutomatiquement,
            'derogations' => Document::query()->whereNotNull('deverrouille_le')->count(),
        ];
    }

    public function update(Request $request, string $section): RedirectResponse
    {
        abort_unless(Auth::user()?->role === User::ROLE_ADMIN, 403);

        $section = $this->sectionValide($section);
        $parametres = Parametre::actuel();

        match ($section) {
            'identite' => $this->enregistrerIdentite($request, $parametres),
            'fichiers' => $this->enregistrerFichiers($request, $parametres),
            'doctorat' => $this->enregistrerDoctorat($request, $parametres),
            'historique' => $this->enregistrerHistorique($request, $parametres),
            'verrouillage' => $this->enregistrerVerrouillage($request, $parametres),
            'sauvegardes' => $this->enregistrerSauvegardes($request, $parametres),
            'mail' => $this->enregistrerMail($request, $parametres),
        };

        Parametre::oublierCache();

        return redirect()->route('gestion.parametres.index', ['section' => $section])
            ->with('succes', 'Paramètres enregistrés.');
    }

    /**
     * Une clé de section inconnue (URL modifiée à la main, lien gardé en
     * favori d'une version antérieure...) retombe silencieusement sur la
     * première section plutôt que de lever une 404 — même tolérance que
     * Parametre::actuel() ailleurs sur cet écran.
     */
    protected function sectionValide(?string $section): string
    {
        return array_key_exists($section, self::SECTIONS) ? $section : array_key_first(self::SECTIONS);
    }

    protected function enregistrerIdentite(Request $request, Parametre $parametres): void
    {
        $donnees = $request->validate([
            'nom_application' => ['required', 'string', 'max:150'],
            // "svg" volontairement RETIRÉ de la liste (revue de sécurité
            // demandée par le client) : un SVG peut contenir du JavaScript
            // inline, exécuté par le navigateur si le fichier est ouvert
            // directement via son URL publique (voir config/filesystems.php
            // — le logo est stocké sur le disque "public", donc accessible
            // par lien direct) — risque faible ici (upload réservé à
            // l'admin) mais autant l'éliminer simplement.
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
            'couleur_principale' => ['required', 'string', 'max:20'],
        ]);

        $ancienLogo = null;

        if ($request->hasFile('logo')) {
            $ancienLogo = $parametres->logo;
            $donnees['logo'] = $request->file('logo')->store('logo', 'public');
        } else {
            // Pas de nouveau logo : on garde celui déjà enregistré, jamais
            // touché par ce formulaire (le champ `logo` n'est pas envoyé du
            // tout quand aucun fichier n'est choisi — voir gestion/
            // parametres/index.blade.php).
            unset($donnees['logo']);
        }

        $parametres->fill($donnees)->save();

        if ($ancienLogo) {
            Storage::disk('public')->delete($ancienLogo);
        }
    }

    protected function enregistrerFichiers(Request $request, Parametre $parametres): void
    {
        $donnees = $request->validate([
            'types_fichiers_autorises' => ['required', 'array', 'min:1'],
            'types_fichiers_autorises.*' => ['string', 'in:'.implode(',', TypesFichiers::toutesLesCles())],
            'taille_max_document_mo' => ['required', 'integer', 'min:1', 'max:500'],
            'taille_max_avatar_mo' => ['required', 'integer', 'min:1', 'max:20'],
        ]);

        $parametres->fill($donnees)->save();
    }

    protected function enregistrerDoctorat(Request $request, Parametre $parametres): void
    {
        $donnees = $request->validate([
            // Nullable : SessionDeliberation::nombreAnneesTheseDoctorat()
            // retombe de toute façon sur 3 si la valeur est vide.
            'nombre_annees_these_doctorat' => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);

        $parametres->fill($donnees)->save();
    }

    protected function enregistrerHistorique(Request $request, Parametre $parametres): void
    {
        $donnees = $request->validate([
            'retention_audit_jours' => ['required', 'integer', 'min:30'],
        ]);

        $parametres->fill($donnees)->save();
    }

    /** Voir App\Support\Verrouillage — les deux seuils sont nullable : laisser un champ vide revient à désactiver cette règle-là, l'autre pouvant rester active indépendamment. */
    protected function enregistrerVerrouillage(Request $request, Parametre $parametres): void
    {
        $donnees = $request->validate([
            'verrouillage_annee_academique_id' => ['nullable', 'integer', Rule::exists('annees_academiques', 'id')],
            'verrouillage_date' => ['nullable', 'date'],
        ]);

        $parametres->fill($donnees)->save();
    }

    protected function enregistrerSauvegardes(Request $request, Parametre $parametres): void
    {
        $donnees = $request->validate([
            'frequence_sauvegarde_auto' => ['required', 'string', 'in:quotidienne,hebdomadaire,mensuelle,annuelle'],
            'retention_sauvegardes_nombre' => ['required', 'integer', 'min:1'],
            'mysqldump_chemin' => ['nullable', 'string', 'max:255'],
        ]);

        $parametres->fill($donnees)->save();
    }

    protected function enregistrerMail(Request $request, Parametre $parametres): void
    {
        $donnees = $request->validate([
            // Configuration mail (SMTP), voir le docblock de
            // Parametre::appliquerConfigurationMail() — tous nullable :
            // laisser "Hôte SMTP" vide revient à ne pas utiliser ce
            // réglage et continuer avec le fichier .env, exactement comme
            // avant l'ajout de cette section.
            'mail_host' => ['nullable', 'string', 'max:255'],
            'mail_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'mail_username' => ['nullable', 'string', 'max:255'],
            // "nullable" ici aussi (pas "required_with:mail_host") : un mot
            // de passe déjà enregistré doit pouvoir être conservé SANS le
            // ressaisir à chaque modification des autres réglages mail — le
            // champ laissé vide est ignoré plus bas (unset), même principe
            // que UserController::update() pour le mot de passe d'un compte.
            'mail_password' => ['nullable', 'string', 'max:255'],
            'mail_encryption' => ['nullable', 'string', 'in:tls,ssl'],
            'mail_from_address' => ['nullable', 'email', 'max:255'],
            'mail_from_name' => ['nullable', 'string', 'max:150'],
        ]);

        // Laissé vide : on ne touche pas au mot de passe SMTP déjà
        // enregistré, comme UserController::update() pour le mot de passe
        // d'un compte (voir le commentaire de la règle de validation
        // ci-dessus).
        if (blank($donnees['mail_password'] ?? null)) {
            unset($donnees['mail_password']);
        }

        $parametres->fill($donnees)->save();
    }
}
