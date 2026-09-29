<?php

namespace App\Providers\Filament;

use App\Filament\Pages\DocumentsParType;
use App\Filament\Pages\ModifierProfil;
use App\Models\Parametre;
use App\Models\SousTypeDocument;
use App\Models\TypeDocument;
use App\Support\ResourceAccess;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Panel unique de la plateforme. Le nom, le logo et la couleur viennent de
 * App\Models\Parametre (voir App\Filament\Pages\Parametres) — entièrement
 * modifiables depuis Administration -> Paramètres après le déploiement,
 * sans jamais avoir besoin de retoucher ce fichier ou de redéployer.
 *
 * Parametre::actuel() se protège elle-même si la table `parametres`
 * n'existe pas encore (toute première installation, avant `artisan
 * migrate`) — voir Parametre::estDisponible().
 */
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $parametres = Parametre::actuel();

        $panel = $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName($parametres->nom_application)
            ->colors([
                'primary' => Color::hex($parametres->couleur_principale ?? '#6366f1'),
            ]);

        if (filled($parametres->logo)) {
            $panel = $panel->brandLogo(Storage::disk('public')->url($parametres->logo));
        }

        return $panel
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
            ])
            ->navigationGroups([
                NavigationGroup::make('Documents'),
                // Une entrée par type de document (et par sous-type pour
                // "Attestation") — sur demande du client, pour que chaque
                // type ait son tableau dissocié des autres plutôt qu'une
                // seule liste avec des onglets. Voir
                // elementsNavigationSecretariat() ci-dessous et
                // App\Filament\Pages\DocumentsParType.
                NavigationGroup::make('Secrétariat')->collapsed(),
                // Accès direct à la corbeille des documents depuis le menu
                // (plutôt qu'un filtre à activer sur l'écran Documents) —
                // voir App\Filament\Resources\CorbeilleResource.
                NavigationGroup::make('Corbeille'),
                NavigationGroup::make('Référentiels'),
                NavigationGroup::make('Administration'),
            ])
            ->navigationItems($this->elementsNavigationSecretariat())
            ->userMenuItems([
                MenuItem::make()
                    ->label('Mon profil')
                    ->icon('heroicon-o-user-circle')
                    ->url(fn () => ModifierProfil::getUrl()),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                \Filament\Http\Middleware\Authenticate::class,
            ]);
    }

    /**
     * Construit dynamiquement les entrées du menu "Secrétariat" : une par
     * type de document (voir App\Models\TypeDocument), y COMPRIS les types
     * désactivés (Liste des étudiants/Diplôme/Facture/Rapport — voir
     * TypeDocumentSeeder) pour que les documents déjà classés dedans restent
     * faciles à retrouver, sur demande explicite du client. Le type
     * "Attestation" est un cas particulier : plutôt qu'une seule entrée, il
     * en reçoit une par sous-type actif (Succès, Vacation, Inscription...) —
     * lui aussi sur demande explicite du client ("il y'a plusieurs types
     * d'attestation").
     *
     * Recalculé à chaque requête (pas de cache) : ce panel n'utilise pas
     * `php artisan filament:cache-components`, donc toute modification du
     * référentiel des types/sous-types depuis l'administration se reflète
     * immédiatement dans ce menu, sans redéploiement.
     *
     * Protégé par Schema::hasTable() : sur une toute première installation
     * (avant `artisan migrate`), ce panel peut être sollicité avant que les
     * tables `type_documents`/`sous_types_documents` n'existent — dans ce
     * cas, simplement aucune entrée (pas d'erreur), même principe défensif
     * que Parametre::estDisponible().
     *
     * La visibilité selon les droits de l'utilisateur connecté
     * (`ResourceAccess::can('documents', 'view')`) n'est PAS vérifiée ici,
     * volontairement : cette méthode s'exécute pendant l'amorçage du panel
     * (avant que le middleware d'authentification n'ait résolu
     * l'utilisateur de la requête en cours), donc `Auth::user()` y serait
     * toujours vide, même pour un administrateur connecté. Chaque élément
     * reçoit donc plutôt un ->visible(Closure), évalué plus tard (au rendu
     * réel du menu, une fois l'utilisateur connu) — voir
     * elementNavigationDocumentsParType().
     *
     * @return array<int, NavigationItem>
     */
    protected function elementsNavigationSecretariat(): array
    {
        if (! Schema::hasTable('type_documents')) {
            return [];
        }

        $items = [];
        $ordre = 1;
        $sousTypesTableExiste = Schema::hasTable('sous_types_documents');

        foreach (TypeDocument::query()->orderBy('ordre')->get() as $type) {
            if ($type->code === 'ATTESTATION' && $sousTypesTableExiste) {
                $sousTypes = SousTypeDocument::query()
                    ->where('type_document_id', $type->id)
                    ->where('actif', true)
                    ->orderBy('ordre')
                    ->get();

                if ($sousTypes->isNotEmpty()) {
                    foreach ($sousTypes as $sousType) {
                        $items[] = $this->elementNavigationDocumentsParType(
                            "{$type->libelle} — {$sousType->libelle}",
                            $ordre++,
                            ['code' => $type->code, 'sousType' => $sousType->id],
                        );
                    }

                    // Pas d'entrée générique "Attestation" en plus des
                    // sous-types : voir le docblock de la méthode.
                    continue;
                }
            }

            $items[] = $this->elementNavigationDocumentsParType($type->libelle, $ordre++, [
                'code' => $type->code,
                // Le segment "sousType" est obligatoire dans la route (voir
                // DocumentsParType::getSlug()) : on fournit toujours une
                // valeur, même pour un type sans sous-type.
                'sousType' => DocumentsParType::AUCUN_SOUS_TYPE,
            ]);
        }

        return $items;
    }

    protected function elementNavigationDocumentsParType(string $libelle, int $ordre, array $parametresRoute): NavigationItem
    {
        return NavigationItem::make($libelle)
            ->icon('heroicon-o-document-text')
            ->group('Secrétariat')
            ->sort($ordre)
            ->visible(fn () => ResourceAccess::can('documents', 'view'))
            ->url(fn () => DocumentsParType::getUrl($parametresRoute))
            ->isActiveWhen(fn () => request()->route('code') === ($parametresRoute['code'] ?? null)
                && (string) request()->route('sousType') === (string) ($parametresRoute['sousType'] ?? ''));
    }
}
