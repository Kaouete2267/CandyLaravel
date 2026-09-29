<?php

namespace Tests\Feature;

use Database\Seeders\BonbonBaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\Admin\Filament\Resources\ProductResource;
use Lunar\Admin\Models\Staff;
use Modules\Ia\Filament\Resources\IngredientTermResource;
use Modules\Support\ProductCreator;
use Tests\TestCase;

/**
 * L'en-tête de page (titre + actions) doit rester visible en défilant plutôt que de disparaître avec le
 * contenu — voir la surcharge de vue dans le module BackendHeader
 * (modules/BackendHeader/resources/views/vendor/filament-panels/components/header/index.blade.php).
 *
 * Les classes sont de vraies classes Tailwind, compilées via le thème Filament officiel
 * (resources/css/filament/lunar/theme.css, enregistré avec ->viteTheme() dans AppServiceProvider) plutôt que
 * via le bundle CSS pré-purgé du package filament/filament (qui ne contient que les classes utilitaires
 * littéralement présentes dans ses propres templates).
 */
class AdminPanelLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_page_header_is_sticky_on_admin_pages(): void
    {
        $staff = Staff::factory()->create(['admin' => true]);

        $response = $this->actingAs($staff, 'staff')->get(IngredientTermResource::getUrl('index'));

        $response->assertOk();
        $response->assertSee('class="fi-header', false);
        $response->assertSee('sticky', false);
        // `top-0`, pas `var(--topbar-height)` : ce dernier laissait un espace vide une fois l'en-tête épinglé
        // (voir le commentaire en tête de la vue surchargée).
        $response->assertSee('top-0', false);
        $response->assertSee('z-20', false);
        // Espace intérieur de la bande épinglée (celui du conteneur ne suit pas l'en-tête une fois épinglé).
        $response->assertSee('py-4', false);
    }

    public function test_multiple_header_actions_are_also_offered_in_a_mobile_dropdown(): void
    {
        $this->seed(BonbonBaseSeeder::class);
        $staff = Staff::factory()->create(['admin' => true]);
        $product = ProductCreator::create(['name' => ['fr' => 'Fraises Tagada']]);

        $response = $this->actingAs($staff, 'staff')->get(ProductResource::getUrl('edit', ['record' => $product]));

        $response->assertOk();
        $response->assertSee('fi-header-actions-dropdown sm:hidden', false);
        $response->assertSee('x-data="filamentDropdown"', false);
        $response->assertSee('hidden flex-wrap justify-end sm:flex', false);
        // Les clones du menu déroulant montent la même action (même nom) que les boutons au-dessus de sm.
        $this->assertSame(2, substr_count($response->getContent(), "wire:click=\"mountAction('update_status'"));
    }

    public function test_the_mobile_sub_navigation_dropdown_is_rendered_once_inside_the_sticky_header(): void
    {
        $this->seed(BonbonBaseSeeder::class);
        $staff = Staff::factory()->create(['admin' => true]);
        $product = ProductCreator::create(['name' => ['fr' => 'Fraises Tagada']]);

        $response = $this->actingAs($staff, 'staff')->get(ProductResource::getUrl('edit', ['record' => $product]));

        $response->assertOk();
        $this->assertSame(1, substr_count($response->getContent(), 'fi-page-sub-navigation-dropdown'));
        $this->assertMatchesRegularExpression('#<header[^>]*fi-header.*fi-page-sub-navigation-dropdown.*</header>#s', $response->getContent());
    }

    public function test_a_single_header_action_is_not_put_in_a_dropdown(): void
    {
        $staff = Staff::factory()->create(['admin' => true]);

        $response = $this->actingAs($staff, 'staff')->get(IngredientTermResource::getUrl('index'));

        $response->assertOk();
        $response->assertDontSee('fi-header-actions-dropdown', false);
    }
}
