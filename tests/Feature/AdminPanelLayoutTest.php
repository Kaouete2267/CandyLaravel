<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\Admin\Models\Staff;
use Modules\Ia\Filament\Resources\IngredientTermResource;
use Tests\TestCase;

/**
 * L'en-tête de page (titre + actions) doit rester visible en défilant plutôt que de disparaître avec le
 * contenu — voir la surcharge de vue dans le module Admin
 * (modules/Admin/resources/views/vendor/filament-panels/components/header/index.blade.php).
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
        // Restaure l'espacement visuel que le padding du parent (.fi-page-header-main-ctn, py-8) fournissait
        // avant que l'en-tête ne devienne sticky (ce padding appartient au parent, pas à .fi-header).
        $response->assertSee('pt-8', false);
    }
}
