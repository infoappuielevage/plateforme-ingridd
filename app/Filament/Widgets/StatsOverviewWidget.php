<?php

namespace App\Filament\Widgets;

use App\Models\Document;
use App\Models\TypeDocument;
use App\Models\User;
use App\Support\Formatage;
use App\Support\ResourceAccess;
use App\Support\TypesDiplome;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;

/**
 * Première rangée du tableau de bord : les chiffres clés en un coup d'œil.
 * Les cartes "En corbeille" et "Dernière sauvegarde" répondent directement
 * aux deux points demandés (visibilité sur la corbeille, sur l'état des
 * sauvegardes) sans avoir à naviguer ailleurs dans l'administration.
 */
class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return ResourceAccess::can('documents', 'view');
    }

    protected function getStats(): array
    {
        $documentsCeMois = Document::query()
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();

        $stats = [
            Stat::make('Documents', Document::query()->count())
                ->description("{$documentsCeMois} ajouté(s) ce mois-ci")
                ->descriptionIcon($documentsCeMois > 0 ? 'heroicon-m-arrow-trending-up' : null)
                ->color($documentsCeMois > 0 ? 'success' : 'gray')
                ->icon('heroicon-o-document-text'),
            Stat::make('Types actifs', TypeDocument::query()->where('actif', true)->count())
                ->icon('heroicon-o-tag'),
            Stat::make('Espace de stockage', Formatage::octets((int) Document::query()->sum('taille')))
                ->description('Total des fichiers archivés')
                ->icon('heroicon-o-server-stack'),
        ];

        // Masquée si le référentiel des types ne contient ni "Diplôme" ni
        // "Attestation" (renommés/supprimés) — répond au besoin de repérer
        // en un coup d'œil les diplômes/attestations pas encore récupérés
        // par l'étudiant (voir aussi DocumentsParTypeChartWidget et
        // DiplomesRetraitChartWidget, et le bouton "Marquer comme retiré"
        // sur DocumentResource::table()).
        $typeDiplomeAttestationIds = TypesDiplome::ids();

        if (count($typeDiplomeAttestationIds) > 0) {
            $nonRetires = Document::query()
                ->whereIn('type_document_id', $typeDiplomeAttestationIds)
                ->where('statut_retrait', Document::STATUT_RETRAIT_NON_RETIRE)
                ->count();

            $stats[] = Stat::make('Diplômes/attestations non retirés', $nonRetires)
                ->description($nonRetires > 0 ? 'À faire retirer par les étudiants concernés' : 'Tous retirés')
                ->color($nonRetires > 0 ? 'warning' : 'success')
                ->icon('heroicon-o-clipboard-document-check');
        }

        // La corbeille et les sauvegardes ne concernent que ceux qui ont le
        // droit de supprimer des documents / d'administrer la plateforme —
        // pas la peine d'afficher ces cartes à un simple consultant.
        if (ResourceAccess::can('documents', 'delete')) {
            $enCorbeille = Document::onlyTrashed()->count();

            $stats[] = Stat::make('En corbeille', $enCorbeille)
                ->description($enCorbeille > 0 ? 'À restaurer ou supprimer définitivement' : 'Corbeille vide')
                ->color($enCorbeille > 0 ? 'warning' : 'gray')
                ->icon('heroicon-o-trash');
        }

        if (Auth::user()?->role === User::ROLE_ADMIN) {
            $stats[] = $this->statDerniereSauvegarde();
        }

        return $stats;
    }

    protected function statDerniereSauvegarde(): Stat
    {
        $derniere = collect(Storage::disk('sauvegardes')->files())
            ->filter(fn (string $fichier) => str_ends_with($fichier, '.zip'))
            ->map(fn (string $fichier) => Storage::disk('sauvegardes')->lastModified($fichier))
            ->sort()
            ->last();

        if (! $derniere) {
            return Stat::make('Dernière sauvegarde', 'Aucune')
                ->description('Créez-en une depuis Administration > Sauvegardes')
                ->color('danger')
                ->icon('heroicon-o-archive-box-x-mark');
        }

        $date = Carbon::createFromTimestamp($derniere);

        return Stat::make('Dernière sauvegarde', $date->diffForHumans())
            ->description($date->format('d/m/Y H:i'))
            // Alerte visuelle si la dernière sauvegarde a plus d'une semaine.
            ->color($date->lt(now()->subWeek()) ? 'warning' : 'success')
            ->icon('heroicon-o-archive-box');
    }
}
