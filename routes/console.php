<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Purge de l'historique
|--------------------------------------------------------------------------
|
| Pour éviter une table `audit_logs` qui grossit indéfiniment — durée de
| rétention réglable dans Administration > Paramètres (voir
| App\Models\Parametre::retention_audit_jours).
*/
Schedule::command('model:prune', ['--model' => [\App\Models\AuditLog::class]])->daily();

/*
|--------------------------------------------------------------------------
| Sauvegarde automatique
|--------------------------------------------------------------------------
|
| Voir App\Console\Commands\CreerSauvegarde et App\Filament\Pages\Sauvegardes
| (Administration > Sauvegardes, pour créer/télécharger/supprimer
| manuellement). Le nombre de sauvegardes conservées ET la fréquence
| (quotidienne/hebdomadaire/mensuelle/annuelle) sont réglables dans
| Administration > Paramètres — voir Parametre::doitSauvegarderAujourdhui() : la planification
| Laravel elle-même reste quotidienne (elle ne peut pas changer de fréquence
| dynamiquement sans redéployer), c'est ce ->when() qui décide si le jour
| convient à la fréquence choisie.
|
| ⚠️ Cette planification ne s'exécute que si le planificateur Laravel
| tourne réellement sur le serveur (`php artisan schedule:work` en
| développement, ou une tâche cron/planificateur de tâches appelant
| `php artisan schedule:run` chaque minute en production — voir la
| documentation Laravel "Task Scheduling").
*/
Schedule::command('ingridd:sauvegarde')
    ->daily()
    ->when(fn () => \App\Models\Parametre::actuel()->doitSauvegarderAujourdhui());

/*
|--------------------------------------------------------------------------
| Alertes sauvegarde / espace disque
|--------------------------------------------------------------------------
|
| Voir App\Console\Commands\VerifierAlertes — pousse en notification (cloche,
| voir layouts/gestion.blade.php) les mêmes alertes déjà visibles sur le
| tableau de bord de l'administrateur, pour ne pas dépendre d'y penser.
*/
Schedule::command('ingridd:verifier-alertes')->daily();
