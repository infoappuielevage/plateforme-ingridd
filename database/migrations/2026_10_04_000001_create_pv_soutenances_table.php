<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PV de soutenance — nouvelle structure dédiée, demande explicite du client
 * ("PV DE SOUTENANCE, a cet niveau on aura besoin de..."), en remplacement du
 * simple "un document = un fichier" utilisé jusqu'ici pour le type
 * PV_SOUTENANCE (voir App\Support\TypesPvSoutenance et les champs
 * theme_memoire/date_soutenance/membres_jury sur App\Models\Document,
 * ajoutés par 2026_09_21_000016_add_champs_pv_soutenance_to_documents_table.php) —
 * MÊME PRINCIPE que sessions_deliberation/sessions_releves (voir leurs
 * migrations respectives) : un vrai enregistrement dédié avec ses propres
 * champs, plutôt que de continuer à surcharger `documents`.
 *
 * Contrairement au PV de délibération/Relevé de notes (un enregistrement =
 * une FILIÈRE entière pour une session donnée), un PV de soutenance ici
 * concerne UN SEUL étudiant — un enregistrement par soutenance, d'où les
 * champs d'identité (nom, prénoms, naissance) directement sur cette table.
 *
 * Les anciens documents PV_SOUTENANCE déjà en base (avant cet écran)
 * restent inchangés, même choix explicite du client que pour
 * PV_DELIBERATION/RELEVE_NOTES : pas de migration de données, les deux
 * cohabitent (voir App\Support\TypesPvSoutenance, toujours utilisé par
 * l'ancien écran Documents pour ces enregistrements historiques).
 *
 * `niveau` (BTS/Licence/Master/Doctorat, voir App\Models\PvSoutenance::NIVEAUX)
 * est un référentiel VOLONTAIREMENT séparé du référentiel Niveau existant
 * (App\Models\Niveau, L1...M2 — voir NiveauSeeder) : deux notions
 * différentes qui partagent juste le mot "niveau" en français (cycle
 * d'études complet ici, semestre/année précis là-bas).
 *
 * `numero_ordre_registre` : unique PAR NIVEAU (chaque registre papier BTS/
 * Licence/Master/Doctorat a sa propre numérotation) — renseigné à la main
 * par l'utilisateur (demande explicite du client : "dois etre renseigné et
 * non automatique"), jamais généré par l'application.
 *
 * `numero_pv` : unique pour TOUTE la plateforme (un même numéro de PV ne
 * désigne qu'un seul étudiant), également renseigné à la main.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pv_soutenances', function (Blueprint $table) {
            $table->id();
            // 'bts' | 'licence' | 'master' | 'doctorat' — voir App\Models\PvSoutenance::NIVEAU_*.
            $table->string('niveau', 20);
            $table->date('date_soutenance');
            $table->string('numero_ordre_registre', 50);
            $table->string('nom', 150);
            $table->string('prenoms', 150);
            $table->date('date_naissance');
            $table->string('lieu_naissance', 150);
            $table->string('numero_pv', 50);
            $table->foreignId('filiere_id')->constrained('filieres')->restrictOnDelete();
            $table->text('theme');
            // 'admis' | 'ajourne' — voir App\Models\PvSoutenance::DECISION_*.
            $table->string('decision_jury', 20)->default('admis');
            $table->decimal('moyenne', 4, 2);
            // 'passable' | 'assez_bien' | 'bien' | 'tres_bien' — voir App\Models\PvSoutenance::MENTION_*.
            $table->string('mention', 20);
            $table->string('president_jury', 150);
            $table->string('directeur_memoire', 150)->nullable();
            $table->string('rapporteur', 150)->nullable();
            $table->string('maitre_stage', 150)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['niveau', 'numero_ordre_registre'], 'pv_soutenances_registre_unique');
            $table->unique('numero_pv', 'pv_soutenances_numero_pv_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pv_soutenances');
    }
};
