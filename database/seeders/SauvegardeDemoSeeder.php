<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Fichiers de sauvegarde factices — pour que l'écran Administration >
 * Sauvegardes (voir App\Http\Controllers\SauvegardeController::lister(),
 * qui liste simplement les fichiers ".zip" du disque `sauvegardes`) affiche
 * déjà un historique à l'installation, sans attendre une vraie sauvegarde.
 *
 * Pas un modèle Eloquent (les sauvegardes ne sont QUE des fichiers sur
 * disque, voir le docblock de SauvegardeController) : ce seeder ne touche
 * donc à aucune table, juste au disque `sauvegardes` (storage/app/sauvegardes,
 * voir config/filesystems.php).
 *
 * CONTENU FACTICE, PAS une vraie archive ZIP valide (contrairement aux
 * documents de démonstration de DocumentSeeder, qui ont un vrai fichier
 * même si son contenu est un texte de remplacement) : ces fichiers servent
 * uniquement à peupler la LISTE (nom/taille/date) — "Télécharger" renverra
 * bien ce contenu factice, mais l'archive ne s'ouvrira pas comme un vrai
 * ZIP. Les horodatages sont échelonnés sur les derniers jours (via
 * touch()) pour que le tri par date le plus récent d'abord montre un ordre
 * réaliste.
 *
 * Même esprit de nommage que App\Console\Commands\CreerSauvegarde
 * ("sauvegarde-AAAA-MM-JJ-HHMMSS.zip") pour ressembler à de vraies
 * sauvegardes — mais SANS le suffixe aléatoire de la vraie commande : nom
 * déterministe (un jour simulé = un nom fixe) pour que rejouer ce seeder le
 * même jour ne crée pas de doublon (voir exists() ci-dessous). Le rejouer un
 * autre jour calendaire ajoutera malgré tout 4 nouveaux fichiers factices
 * (les dates simulées, relatives à "aujourd'hui", changent) — sans gravité,
 * juste à nettoyer avec les autres avant une mise en production réelle.
 *
 * VOLONTAIREMENT PAS appelé depuis DatabaseSeeder, comme DocumentSeeder (voir
 * son docblock) : à lancer à la demande, et à supprimer avant une mise en
 * production réelle (Administration > Sauvegardes, bouton Supprimer sur
 * chacune, ou directement storage/app/sauvegardes sur le serveur) :
 *
 *     php artisan db:seed --class=SauvegardeDemoSeeder
 */
class SauvegardeDemoSeeder extends Seeder
{
    protected const CONTENU_FACTICE = "Ceci est un fichier de sauvegarde FACTICE généré par SauvegardeDemoSeeder.\nCe n'est pas une vraie archive ZIP — à supprimer avant la mise en production réelle.";

    public function run(): void
    {
        $disque = Storage::disk('sauvegardes');
        $cheminRacine = $disque->path('');
        $creees = 0;

        // 4 sauvegardes factices, échelonnées sur les 10 derniers jours
        // (pas une par jour : une vraie fréquence "quotidienne" en
        // produirait beaucoup plus, mais 4 suffisent largement pour montrer
        // à quoi ressemble la liste).
        foreach ([0, 3, 6, 9] as $joursEcoules) {
            $date = now()->subDays($joursEcoules);
            // Nom déterministe (sans suffixe aléatoire) — voir le docblock
            // de la classe pour pourquoi.
            $nomFichier = 'sauvegarde-demo-'.$date->format('Y-m-d-His').'.zip';

            if ($disque->exists($nomFichier)) {
                continue;
            }

            $disque->put($nomFichier, self::CONTENU_FACTICE);

            // touch() pour que "lastModified()" (donc la colonne Date de
            // l'écran Sauvegardes) reflète bien la date simulée ci-dessus,
            // pas le moment réel d'exécution de ce seeder.
            @touch($cheminRacine.$nomFichier, $date->timestamp);

            $creees++;
        }

        $this->command?->info($creees.' fichier(s) de sauvegarde factice(s) créé(s) sur le disque "sauvegardes".');
    }
}
