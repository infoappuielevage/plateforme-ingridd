<?php

namespace Tests\Feature;

use App\Http\Controllers\ParametresController;
use App\Models\AnneeAcademique;
use App\Models\Document;
use App\Models\Parametre;
use App\Models\User;
use App\Support\TypesFichiers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * App\Http\Controllers\ParametresController — remplace App\Filament\Pages\
 * Parametres sur demande du client (plus de Filament).
 *
 * Demande explicite du client ("je ne veux pas que tout soit sur la même
 * page") : l'écran est découpé en sections indépendantes (voir
 * ParametresController::SECTIONS), chacune avec sa propre URL
 * (/gestion/parametres/{section}) et son propre enregistrement — chaque
 * test ci-dessous n'envoie donc QUE les champs de la section testée,
 * jamais l'ensemble du formulaire d'origine.
 */
class ParametresControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_non_admin_ne_peut_pas_acceder_aux_parametres(): void
    {
        $utilisateur = User::factory()->gestionnaire()->create();

        $this->actingAs($utilisateur)
            ->get(route('gestion.parametres.index'))
            ->assertForbidden();

        $this->actingAs($utilisateur)
            ->put(route('gestion.parametres.update', ['section' => 'identite']), ['nom_application' => 'Tentative'])
            ->assertForbidden();
    }

    /** Le sous-menu liste bien les six sections, et chaque section affiche son propre contenu. */
    public function test_le_sous_menu_liste_toutes_les_sections(): void
    {
        $admin = User::factory()->admin()->create();

        $reponse = $this->actingAs($admin)->get(route('gestion.parametres.index'));

        $reponse->assertOk();
        foreach (ParametresController::SECTIONS as $libelle) {
            $reponse->assertSee($libelle);
        }
    }

    public function test_une_section_inconnue_dans_lurl_retombe_sur_la_premiere_section(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('gestion.parametres.index', ['section' => 'section-inexistante']))
            ->assertOk()
            ->assertSee("Identité de l'application");
    }

    // --- Section "identite" ---------------------------------------------

    public function test_un_admin_peut_modifier_lidentite(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('gestion.parametres.update', ['section' => 'identite']), [
                'nom_application' => 'Archives Test',
                'couleur_principale' => '#22c55e',
            ])
            ->assertRedirect(route('gestion.parametres.index', ['section' => 'identite']));

        $this->assertSame('Archives Test', Parametre::actuel()->nom_application);
    }

    /** Un SVG est un format texte pouvant contenir du JavaScript — risque de XSS stockée (revue de sécurité), même raisonnement que ProfilControllerTest pour l'avatar. */
    public function test_un_fichier_svg_est_refuse_comme_logo(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('gestion.parametres.update', ['section' => 'identite']), [
                'nom_application' => 'Archives Test',
                'couleur_principale' => '#6366f1',
                'logo' => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml'),
            ])
            ->assertSessionHasErrors('logo');
    }

    public function test_le_logo_peut_etre_televerse_et_remplace_lancien(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        Parametre::actuel()->fill(['logo' => 'logo/ancien.png'])->save();
        Storage::disk('public')->put('logo/ancien.png', 'contenu');

        $this->actingAs($admin)
            ->put(route('gestion.parametres.update', ['section' => 'identite']), [
                'nom_application' => 'INGRIDD',
                'couleur_principale' => '#6366f1',
                'logo' => UploadedFile::fake()->image('nouveau-logo.png'),
            ])
            ->assertRedirect(route('gestion.parametres.index', ['section' => 'identite']));

        $parametres = Parametre::actuel();
        $this->assertNotSame('logo/ancien.png', $parametres->logo);
        Storage::disk('public')->assertExists($parametres->logo);
        Storage::disk('public')->assertMissing('logo/ancien.png');
    }

    // --- Section "fichiers" ----------------------------------------------

    /**
     * Même piège que côté Filament (voir tests/Feature/ParametresTest.php) :
     * une ligne "parametres" créée avant la colonne types_fichiers_autorises
     * doit précocher toutes les familles, pas afficher un formulaire vide.
     */
    public function test_le_formulaire_precoche_toutes_les_familles_si_le_reglage_nest_pas_encore_enregistre(): void
    {
        $admin = User::factory()->admin()->create();
        Parametre::actuel();
        Parametre::query()->update(['types_fichiers_autorises' => null]);

        $reponse = $this->actingAs($admin)->get(route('gestion.parametres.index', ['section' => 'fichiers']));

        $reponse->assertOk();
        foreach (TypesFichiers::toutesLesCles() as $cle) {
            $reponse->assertSee('id="famille_'.$cle.'"', false);
        }
    }

    public function test_un_admin_peut_modifier_les_limites_de_fichiers(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('gestion.parametres.update', ['section' => 'fichiers']), [
                'types_fichiers_autorises' => ['documents', 'images'],
                'taille_max_document_mo' => 50,
                'taille_max_avatar_mo' => 5,
            ])
            ->assertRedirect(route('gestion.parametres.index', ['section' => 'fichiers']));

        $parametres = Parametre::actuel();
        $this->assertSame(50, $parametres->taille_max_document_mo);
        $this->assertSame(['documents', 'images'], $parametres->types_fichiers_autorises);
    }

    /** Au moins une famille de fichiers doit rester cochée — sans quoi plus aucun document ne pourrait jamais être ajouté. */
    public function test_au_moins_une_famille_de_fichiers_doit_etre_cochee(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('gestion.parametres.update', ['section' => 'fichiers']), [
                'types_fichiers_autorises' => [],
                'taille_max_document_mo' => 20,
                'taille_max_avatar_mo' => 2,
            ])
            ->assertSessionHasErrors('types_fichiers_autorises');
    }

    // --- Section "doctorat" ----------------------------------------------

    public function test_un_admin_peut_modifier_le_nombre_dannees_de_these(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('gestion.parametres.update', ['section' => 'doctorat']), [
                'nombre_annees_these_doctorat' => 4,
            ])
            ->assertRedirect(route('gestion.parametres.index', ['section' => 'doctorat']));

        $this->assertSame(4, Parametre::actuel()->nombre_annees_these_doctorat);
    }

    // --- Section "historique" ---------------------------------------------

    public function test_un_admin_peut_modifier_la_retention_de_lhistorique(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('gestion.parametres.update', ['section' => 'historique']), [
                'retention_audit_jours' => 365,
            ])
            ->assertRedirect(route('gestion.parametres.index', ['section' => 'historique']));

        $this->assertSame(365, Parametre::actuel()->retention_audit_jours);
    }

    /** Une rétention trop courte (moins de 30 jours) reste refusée. */
    public function test_une_retention_trop_courte_est_refusee(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('gestion.parametres.update', ['section' => 'historique']), [
                'retention_audit_jours' => 10,
            ])
            ->assertSessionHasErrors('retention_audit_jours');
    }

    // --- Section "verrouillage" ---------------------------------------------

    /**
     * ParametresController::statistiquesVerrouillage() — reproduit en SQL la
     * même logique que App\Support\Verrouillage::estVerrouille() (manuel >
     * seuils automatiques > dérogation explicite). Un document qui remplit
     * PLUSIEURS conditions (verrouillage manuel ET seuil, ou les deux
     * seuils à la fois) ne doit être compté qu'une seule fois, dans la bonne
     * catégorie — c'est ce que ce test vérifie en couvrant chaque cas.
     */
    public function test_la_section_verrouillage_affiche_les_statistiques(): void
    {
        $admin = User::factory()->admin()->create();

        $ancienne = AnneeAcademique::factory()->create(['libelle' => '2023-2024']);
        $recente = AnneeAcademique::factory()->create(['libelle' => '2025-2026']);

        Parametre::actuel()->update([
            'verrouillage_annee_academique_id' => $ancienne->id,
            'verrouillage_date' => '2024-01-01',
        ]);

        // Verrouillé manuellement (compte dans "manuel", pas dans
        // "automatique" même si son année est aussi sous le seuil).
        Document::factory()->create(['verrouille_le' => now(), 'annee_academique_id' => $ancienne->id]);

        // Verrouillé automatiquement par le seuil "année académique".
        Document::factory()->create(['annee_academique_id' => $ancienne->id]);

        // Verrouillé automatiquement par le seuil "date", et par le seuil
        // "année académique" en même temps : ne doit être compté qu'UNE
        // fois dans "automatique".
        Document::factory()->create(['annee_academique_id' => $ancienne->id, 'created_at' => '2023-06-01']);

        // Déverrouillé explicitement (dérogation) : ne doit plus compter
        // comme verrouillé, même si son année est sous le seuil.
        Document::factory()->create(['annee_academique_id' => $ancienne->id, 'deverrouille_le' => now()]);

        // Récent, au-dessus des deux seuils : pas verrouillé du tout.
        Document::factory()->create(['annee_academique_id' => $recente->id]);

        $reponse = $this->actingAs($admin)->get(route('gestion.parametres.index', ['section' => 'verrouillage']));

        $reponse->assertOk();
        $reponse->assertViewHas('statistiquesVerrouillage', [
            'total' => 5,
            'manuel' => 1,
            'automatique' => 2,
            'derogations' => 1,
        ]);
    }

    /** Pas de seuil actif : "automatique" doit rester à 0 (et surtout ne jamais compter TOUS les documents). */
    public function test_les_statistiques_de_verrouillage_sont_a_zero_sans_seuil_actif(): void
    {
        $admin = User::factory()->admin()->create();

        Document::factory()->count(3)->create();

        $reponse = $this->actingAs($admin)->get(route('gestion.parametres.index', ['section' => 'verrouillage']));

        $reponse->assertViewHas('statistiquesVerrouillage', [
            'total' => 3,
            'manuel' => 0,
            'automatique' => 0,
            'derogations' => 0,
        ]);
    }

    /** Les statistiques ne sont calculées (et transmises à la vue) que pour la section "verrouillage" — sans coût sur les autres. */
    public function test_les_statistiques_de_verrouillage_ne_sont_pas_calculees_sur_les_autres_sections(): void
    {
        $admin = User::factory()->admin()->create();

        $reponse = $this->actingAs($admin)->get(route('gestion.parametres.index', ['section' => 'identite']));

        $reponse->assertOk();
        $reponse->assertViewHas('statistiquesVerrouillage', null);
    }

    // --- Section "sauvegardes" ---------------------------------------------

    public function test_un_admin_peut_modifier_les_sauvegardes(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('gestion.parametres.update', ['section' => 'sauvegardes']), [
                'frequence_sauvegarde_auto' => 'hebdomadaire',
                'retention_sauvegardes_nombre' => 5,
                'mysqldump_chemin' => 'C:\\laragon\\bin\\mysql\\mysql-8.0\\bin\\mysqldump.exe',
            ])
            ->assertRedirect(route('gestion.parametres.index', ['section' => 'sauvegardes']));

        $this->assertSame('hebdomadaire', Parametre::actuel()->frequence_sauvegarde_auto);
    }

    /** "annuelle" ajoutée sur demande explicite du client, en plus de quotidienne/hebdomadaire/mensuelle — voir Parametre::doitSauvegarderAujourdhui(). */
    public function test_la_frequence_annuelle_est_acceptee(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('gestion.parametres.update', ['section' => 'sauvegardes']), [
                'frequence_sauvegarde_auto' => 'annuelle',
                'retention_sauvegardes_nombre' => 5,
            ])
            ->assertRedirect(route('gestion.parametres.index', ['section' => 'sauvegardes']))
            ->assertSessionHasNoErrors();

        $this->assertSame('annuelle', Parametre::actuel()->frequence_sauvegarde_auto);
    }

    /** Une valeur hors de la liste connue (quotidienne/hebdomadaire/mensuelle/annuelle) doit rester refusée. */
    public function test_une_frequence_inconnue_est_refusee(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('gestion.parametres.update', ['section' => 'sauvegardes']), [
                'frequence_sauvegarde_auto' => 'horaire',
                'retention_sauvegardes_nombre' => 5,
            ])
            ->assertSessionHasErrors('frequence_sauvegarde_auto');
    }

    // --- Section "mail" ----------------------------------------------------

    /**
     * Configuration mail (SMTP) éditable depuis cet écran — demande
     * explicite du client, voir le docblock de la migration
     * 2026_10_10_000001_add_configuration_mail_to_parametres_table.php et de
     * Parametre::appliquerConfigurationMail().
     */
    public function test_la_configuration_mail_peut_etre_enregistree(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('gestion.parametres.update', ['section' => 'mail']), [
                'mail_host' => 'smtp.gmail.com',
                'mail_port' => 587,
                'mail_username' => 'contact@ingridd.bf',
                'mail_password' => 'un-mot-de-passe-dapplication',
                'mail_encryption' => 'tls',
                'mail_from_address' => 'contact@ingridd.bf',
                'mail_from_name' => 'INGRIDD Gestion Archives',
            ])
            ->assertRedirect(route('gestion.parametres.index', ['section' => 'mail']))
            ->assertSessionHasNoErrors();

        $parametres = Parametre::actuel();
        $this->assertSame('smtp.gmail.com', $parametres->mail_host);
        $this->assertSame(587, $parametres->mail_port);
        $this->assertSame('contact@ingridd.bf', $parametres->mail_username);
        // Le cast 'encrypted' (voir App\Models\Parametre) déchiffre
        // automatiquement à la lecture : on retrouve le mot de passe en
        // clair ici, seule la colonne SQL elle-même est chiffrée (vérifié
        // séparément ci-dessous).
        $this->assertSame('un-mot-de-passe-dapplication', $parametres->mail_password);
        $this->assertSame('tls', $parametres->mail_encryption);
        $this->assertSame('contact@ingridd.bf', $parametres->mail_from_address);
        $this->assertSame('INGRIDD Gestion Archives', $parametres->mail_from_name);

        // La colonne SQL elle-même ne contient jamais le mot de passe en
        // clair — on la relit brute, sans passer par le cast Eloquent.
        $brut = DB::table('parametres')->where('id', $parametres->id)->value('mail_password');
        $this->assertStringNotContainsString('un-mot-de-passe-dapplication', (string) $brut);
    }

    /** Même principe que UserController::update() pour le mot de passe d'un compte : un champ laissé vide ne doit jamais effacer le mot de passe SMTP déjà enregistré. */
    public function test_laisser_le_mot_de_passe_mail_vide_en_modification_le_conserve(): void
    {
        $admin = User::factory()->admin()->create();
        Parametre::actuel()->fill([
            'mail_host' => 'smtp.gmail.com',
            'mail_password' => 'ancien-mot-de-passe',
        ])->save();

        $this->actingAs($admin)
            ->put(route('gestion.parametres.update', ['section' => 'mail']), [
                'mail_host' => 'smtp.gmail.com',
                'mail_port' => 587,
                // 'mail_password' volontairement absent.
            ])
            ->assertRedirect(route('gestion.parametres.index', ['section' => 'mail']));

        $this->assertSame('ancien-mot-de-passe', Parametre::actuel()->mail_password);
    }

    /** Un chiffrement mail inconnu (hors tls/ssl) doit rester refusé, comme les autres listes fermées de cet écran. */
    public function test_un_chiffrement_mail_inconnu_est_refuse(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('gestion.parametres.update', ['section' => 'mail']), [
                'mail_encryption' => 'starttls-exotique',
            ])
            ->assertSessionHasErrors('mail_encryption');
    }
}
