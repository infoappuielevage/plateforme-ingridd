<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gestion des contrats — demande explicite du client ("intègre à présent la
 * gestion des contrat, on aurait les partenariat... les contrat de
 * prestation de Service, et tout autre contrat dont tu pense que c'est
 * necessaire"), confirmée via la question de clarification posée avant
 * cette migration : un SEUL écran "Contrats" avec un onglet par catégorie
 * (voir App\Models\TypeContrat, `type_contrat_id` ci-dessous), structure
 * MÊME PRINCIPE que App\Models\RecetteDossier — un enregistrement créé À LA
 * DEMANDE (formulaire "Nouveau contrat"), contrairement à App\Models\Depense
 * (une ligne par année académique × catégorie, créée automatiquement).
 *
 * Champs confirmés avec le client (question de clarification, "Pour chaque
 * contrat, je propose de saisir...") :
 * - `partenaire_prestataire` : le nom de la contrepartie — un partenaire
 *   pour un Partenariat, un prestataire pour une Prestation de service,
 *   un bailleur pour un Bail, etc. Un seul champ texte plutôt qu'un
 *   référentiel dédié : contrairement aux étudiants/filières, ces tiers ne
 *   sont pas réutilisés ailleurs dans l'application et n'ont pas besoin
 *   d'être normalisés.
 * - `objet` : résumé de ce que couvre le contrat (ex. "Entretien des
 *   locaux", "Partenariat pédagogique"), affiché dans la liste.
 * - `montant` NULLABLE : un partenariat n'a pas forcément de valeur
 *   monétaire (demande explicite du client) — contrairement à
 *   App\Models\Depense/Recette, cette plateforme ne fait ici AUCUN rapprochement
 *   comptable avec Gestion des dépenses/recettes, simple valeur informative.
 * - `date_signature`/`date_debut`/`date_fin` toutes NULLABLES : un contrat
 *   "en négociation" (voir `statut`) n'a souvent pas encore de date de
 *   signature ; `date_fin` vide = durée indéterminée.
 * - `statut` : champ texte simple (pas de table séparée, liste fermée de 4
 *   valeurs non appelée à grandir, voir App\Models\Contrat::STATUTS) saisi
 *   MANUELLEMENT par l'utilisateur plutôt que recalculé automatiquement à
 *   partir des dates — un contrat "résilié" par anticipation, par exemple,
 *   n'est détectable par aucune date.
 * - `responsable_id` : l'agent INGRIDD référent sur ce contrat (nullable,
 *   `nullOnDelete()` — un compte utilisateur supprimé ne doit pas emporter
 *   l'historique du contrat).
 * - `contact_nom`/`contact_email`/`contact_telephone` : le contact chez la
 *   contrepartie, tous nullables (facultatifs à la saisie).
 * - `observations` : notes libres.
 *
 * PAS de `annee_academique_id` ici, contrairement à la plupart des autres
 * écrans dédiés de ce projet : un contrat (bail, partenariat...) se raisonne
 * en dates calendaires, pas en années académiques, et court fréquemment sur
 * plusieurs années académiques à la fois — l'y rattacher de force n'aurait
 * aucun sens pour l'utilisateur.
 *
 * `type_contrat_id` en `restrictOnDelete()` (champ obligatoire
 * d'identification du contrat, pas un rattachement facultatif) — même
 * raisonnement que `annee_academique_id` sur `reunions`.
 *
 * SoftDeletes : même convention que tous les autres écrans dédiés —
 * "corbeillé, jamais supprimé" par défaut.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contrats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('type_contrat_id')->constrained('types_contrats')->restrictOnDelete();
            $table->string('partenaire_prestataire', 200);
            $table->string('objet', 255);
            $table->decimal('montant', 14, 2)->nullable();
            $table->date('date_signature')->nullable();
            $table->date('date_debut')->nullable();
            $table->date('date_fin')->nullable();
            // 'actif' | 'expire' | 'resilie' | 'en_negociation' — voir App\Models\Contrat::STATUTS.
            $table->string('statut', 20)->default('actif');
            $table->foreignId('responsable_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('contact_nom', 150)->nullable();
            $table->string('contact_email', 255)->nullable();
            $table->string('contact_telephone', 30)->nullable();
            $table->text('observations')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contrats');
    }
};
