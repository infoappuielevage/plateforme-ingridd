<?php

namespace Tests\Unit;

use App\Support\TypesFichiers;
use Tests\TestCase;

/**
 * Logique pure (pas de base de données) derrière le réglage "Types de
 * fichiers acceptés" (Administration > Paramètres) — voir
 * App\Filament\Pages\Parametres et App\Filament\Resources\DocumentResource.
 */
class TypesFichiersTest extends TestCase
{
    public function test_sans_selection_toutes_les_familles_sont_acceptees(): void
    {
        $toutesLesMimes = collect(TypesFichiers::CATEGORIES)
            ->flatMap(fn (array $categorie) => $categorie['mimes'])
            ->values()
            ->all();

        $this->assertSame($toutesLesMimes, TypesFichiers::mimesPour(null));
        $this->assertSame($toutesLesMimes, TypesFichiers::mimesPour([]));
    }

    public function test_une_selection_limite_aux_familles_choisies(): void
    {
        $mimes = TypesFichiers::mimesPour(['images']);

        $this->assertSame(['image/png', 'image/jpeg', 'image/webp'], $mimes);
        $this->assertNotContains('application/pdf', $mimes);
        $this->assertNotContains('application/zip', $mimes);
    }

    public function test_les_options_du_formulaire_couvrent_toutes_les_categories(): void
    {
        $options = TypesFichiers::optionsPourFormulaire();

        $this->assertSame(array_keys(TypesFichiers::CATEGORIES), array_keys($options));
        $this->assertSame(array_keys(TypesFichiers::CATEGORIES), TypesFichiers::toutesLesCles());
    }
}
