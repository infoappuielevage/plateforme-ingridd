<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Administration > Messagerie (App\Http\Controllers\MessagerieController) —
 * remplace l'ancien écran "Annonces" (demande explicite du client : "améliore
 * la gestion des annonces pour que ça soit comme une messagerie"). Voir le
 * docblock du contrôleur et de App\Models\Conversation pour le fonctionnement
 * complet (boîte de réception partagée entre administrateurs, une
 * conversation par utilisateur non-admin, lui seul peut y répondre).
 */
class MessagerieControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_non_admin_ne_peut_pas_acceder_a_lecran_nouveau_message(): void
    {
        $utilisateur = User::factory()->create();

        $this->actingAs($utilisateur)->get(route('gestion.messagerie.nouveau'))->assertForbidden();
    }

    public function test_un_non_admin_ne_peut_pas_envoyer_un_nouveau_message(): void
    {
        $utilisateur = User::factory()->create();

        $this->actingAs($utilisateur)
            ->post(route('gestion.messagerie.envoyer-nouveau'), [
                'destinataire_type' => 'tous',
                'titre' => 'Titre',
                'message' => 'Message',
            ])
            ->assertForbidden();
    }

    public function test_un_message_a_tout_le_monde_cree_une_conversation_par_utilisateur(): void
    {
        $admin = User::factory()->admin()->create();
        $autres = User::factory()->count(3)->create();

        $this->actingAs($admin)
            ->post(route('gestion.messagerie.envoyer-nouveau'), [
                'destinataire_type' => 'tous',
                'titre' => 'Maintenance prévue',
                'message' => 'La plateforme sera indisponible dimanche de 2h à 4h.',
            ])
            ->assertRedirect(route('gestion.messagerie.index'));

        $this->assertSame(4, Conversation::query()->count());

        // Admin inclus (4 comptes au total) : "tout le monde" ne doit exclure personne.
        foreach ($autres->push($admin) as $destinataire) {
            $conversation = Conversation::query()->where('utilisateur_id', $destinataire->id)->firstOrFail();

            $this->assertSame(1, $conversation->messages()->count());
            $this->assertSame('Maintenance prévue', $conversation->messages()->first()->titre);
            $this->assertSame(1, $destinataire->notifications()->count());
        }
    }

    public function test_un_message_a_un_role_ne_cree_une_conversation_que_pour_ce_role(): void
    {
        $admin = User::factory()->admin()->create();

        Role::query()->create([
            'slug' => 'secretariat_messagerie',
            'name' => 'Secrétariat',
            'permissions' => [],
        ]);
        $membreSecretariat = User::factory()->create(['role' => 'secretariat_messagerie']);
        $membreAutreRole = User::factory()->create();

        $this->actingAs($admin)
            ->post(route('gestion.messagerie.envoyer-nouveau'), [
                'destinataire_type' => 'role',
                'role' => 'secretariat_messagerie',
                'titre' => 'Pour le secrétariat',
                'message' => 'Message ciblé.',
            ])
            ->assertRedirect(route('gestion.messagerie.index'));

        $this->assertNotNull(Conversation::query()->where('utilisateur_id', $membreSecretariat->id)->first());
        $this->assertNull(Conversation::query()->where('utilisateur_id', $membreAutreRole->id)->first());
    }

    public function test_un_message_a_un_utilisateur_precis_ne_cree_une_conversation_que_pour_lui(): void
    {
        $admin = User::factory()->admin()->create();
        $destinataire = User::factory()->create();
        $autre = User::factory()->create();

        $this->actingAs($admin)
            ->post(route('gestion.messagerie.envoyer-nouveau'), [
                'destinataire_type' => 'utilisateur',
                'utilisateur_id' => $destinataire->id,
                'titre' => 'Message personnel',
                'message' => 'Juste pour vous.',
            ])
            ->assertRedirect(route('gestion.messagerie.index'));

        $this->assertNotNull(Conversation::query()->where('utilisateur_id', $destinataire->id)->first());
        $this->assertNull(Conversation::query()->where('utilisateur_id', $autre->id)->first());
    }

    public function test_le_role_est_obligatoire_quand_le_destinataire_est_un_role(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('gestion.messagerie.envoyer-nouveau'), [
                'destinataire_type' => 'role',
                'titre' => 'Titre',
                'message' => 'Message',
            ])
            ->assertSessionHasErrors('role');
    }

    public function test_lutilisateur_est_obligatoire_quand_le_destinataire_est_un_utilisateur_precis(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('gestion.messagerie.envoyer-nouveau'), [
                'destinataire_type' => 'utilisateur',
                'titre' => 'Titre',
                'message' => 'Message',
            ])
            ->assertSessionHasErrors('utilisateur_id');
    }

    public function test_un_second_message_au_meme_utilisateur_poursuit_la_meme_conversation(): void
    {
        $admin = User::factory()->admin()->create();
        $destinataire = User::factory()->create();

        $this->actingAs($admin)->post(route('gestion.messagerie.envoyer-nouveau'), [
            'destinataire_type' => 'utilisateur',
            'utilisateur_id' => $destinataire->id,
            'titre' => 'Premier message',
            'message' => 'Bonjour.',
        ]);

        $this->actingAs($admin)->post(route('gestion.messagerie.envoyer-nouveau'), [
            'destinataire_type' => 'utilisateur',
            'utilisateur_id' => $destinataire->id,
            'titre' => 'Second message',
            'message' => 'Un rappel.',
        ]);

        $this->assertSame(1, Conversation::query()->where('utilisateur_id', $destinataire->id)->count());
        $this->assertSame(2, Conversation::query()->where('utilisateur_id', $destinataire->id)->first()->messages()->count());
    }

    public function test_un_utilisateur_peut_consulter_et_repondre_a_sa_propre_conversation(): void
    {
        $admin = User::factory()->admin()->create();
        $utilisateur = User::factory()->create();

        $conversation = Conversation::query()->create(['utilisateur_id' => $utilisateur->id]);
        $conversation->messages()->create(['auteur_id' => $admin->id, 'titre' => 'Bienvenue', 'contenu' => 'Bonjour.']);
        $conversation->update(['dernier_message_a' => now()]);

        $this->actingAs($utilisateur)
            ->get(route('gestion.messagerie.afficher', $conversation))
            ->assertOk()
            ->assertSee('Bonjour.');

        $this->actingAs($utilisateur)
            ->post(route('gestion.messagerie.repondre', $conversation), ['contenu' => 'Merci, bien reçu.'])
            ->assertRedirect(route('gestion.messagerie.afficher', $conversation));

        $this->assertSame(2, $conversation->messages()->count());
        $this->assertSame('Merci, bien reçu.', $conversation->messages()->latest('id')->first()->contenu);

        // La réponse de l'utilisateur doit prévenir l'administrateur
        // (boîte de réception partagée entre TOUS les administrateurs, voir
        // le docblock du contrôleur).
        $this->assertSame(1, $admin->notifications()->count());
    }

    public function test_un_utilisateur_ne_peut_pas_consulter_la_conversation_dun_autre(): void
    {
        $admin = User::factory()->admin()->create();
        $utilisateur = User::factory()->create();
        $intrus = User::factory()->create();

        $conversation = Conversation::query()->create(['utilisateur_id' => $utilisateur->id]);
        $conversation->messages()->create(['auteur_id' => $admin->id, 'contenu' => 'Privé.']);

        $this->actingAs($intrus)->get(route('gestion.messagerie.afficher', $conversation))->assertForbidden();
        $this->actingAs($intrus)->post(route('gestion.messagerie.repondre', $conversation), ['contenu' => 'Intrusion.'])->assertForbidden();
    }

    public function test_un_administrateur_peut_consulter_et_repondre_a_nimporte_quelle_conversation(): void
    {
        $admin = User::factory()->admin()->create();
        $utilisateur = User::factory()->create();

        $conversation = Conversation::query()->create(['utilisateur_id' => $utilisateur->id]);
        $conversation->messages()->create(['auteur_id' => $utilisateur->id, 'contenu' => 'Question ?']);
        $conversation->update(['dernier_message_a' => now()]);

        $this->actingAs($admin)
            ->get(route('gestion.messagerie.afficher', $conversation))
            ->assertOk()
            ->assertSee('Question ?');

        $this->actingAs($admin)
            ->post(route('gestion.messagerie.repondre', $conversation), ['contenu' => 'Voici la réponse.'])
            ->assertRedirect(route('gestion.messagerie.afficher', $conversation));

        $this->assertSame(1, $utilisateur->notifications()->count());
    }

    public function test_un_utilisateur_sans_conversation_voit_lecran_vide(): void
    {
        $utilisateur = User::factory()->create();

        $this->actingAs($utilisateur)
            ->get(route('gestion.messagerie.index'))
            ->assertOk()
            ->assertSee('Aucune conversation');
    }

    public function test_messages_recents_ne_renvoie_que_les_messages_posterieurs(): void
    {
        $admin = User::factory()->admin()->create();
        $utilisateur = User::factory()->create();

        $conversation = Conversation::query()->create(['utilisateur_id' => $utilisateur->id]);
        $premier = $conversation->messages()->create(['auteur_id' => $admin->id, 'contenu' => 'Ancien message.']);
        $conversation->update(['dernier_message_a' => now()]);

        $reponseInitiale = $this->actingAs($utilisateur)
            ->getJson(route('gestion.messagerie.messages-recents', $conversation).'?apres_id='.$premier->id)
            ->assertOk();

        $this->assertStringNotContainsString('Ancien message.', $reponseInitiale->json('html'));

        $second = $conversation->messages()->create(['auteur_id' => $admin->id, 'contenu' => 'Nouveau message.']);

        $reponse = $this->actingAs($utilisateur)
            ->getJson(route('gestion.messagerie.messages-recents', $conversation).'?apres_id='.$premier->id)
            ->assertOk();

        $reponse->assertJson(['dernier_id' => $second->id]);
        $this->assertStringContainsString('Nouveau message.', $reponse->json('html'));
        $this->assertStringNotContainsString('Ancien message.', $reponse->json('html'));
    }

    public function test_le_nombre_de_non_lus_diminue_apres_consultation(): void
    {
        $admin = User::factory()->admin()->create();
        $utilisateur = User::factory()->create();

        $conversation = Conversation::query()->create(['utilisateur_id' => $utilisateur->id]);
        $conversation->messages()->create(['auteur_id' => $admin->id, 'contenu' => 'Bonjour.']);
        $conversation->update(['dernier_message_a' => now()]);

        $this->actingAs($utilisateur)
            ->getJson(route('gestion.messagerie.nombre-non-lus'))
            ->assertOk()
            ->assertJson(['nombre' => 1]);

        $this->actingAs($utilisateur)->get(route('gestion.messagerie.afficher', $conversation));

        $this->actingAs($utilisateur)
            ->getJson(route('gestion.messagerie.nombre-non-lus'))
            ->assertOk()
            ->assertJson(['nombre' => 0]);
    }
}
