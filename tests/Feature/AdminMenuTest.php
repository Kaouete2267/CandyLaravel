<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\Admin\Models\Staff;
use Modules\Ia\Filament\Resources\IngredientTermResource;
use Tests\TestCase;

/**
 * La barre latérale du panneau est remplacée par le menu à deux colonnes du module Menu
 * (modules/Menu/resources/views/vendor/filament-panels/livewire/sidebar.blade.php).
 */
class AdminMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_sidebar_lists_groups_with_their_pages_in_an_overlay_panel(): void
    {
        $staff = Staff::factory()->create(['admin' => true]);

        $response = $this->actingAs($staff, 'staff')->get(IngredientTermResource::getUrl('index'));

        $response->assertOk();

        $groupKey = 'menu-group-'.md5('Confiserie');

        // Bouton du groupe dans la colonne étroite, relié à son panneau.
        $response->assertSee('aria-controls="'.$groupKey.'"', false);
        // Le panneau est superposé au contenu (position absolue) pour ne pas redimensionner la page…
        $response->assertSeeInOrder(['id="'.$groupKey.'"', 'absolute', 'Confiserie', IngredientTermResource::getUrl('index')], false);
        // … et la page courante y est marquée comme active.
        $response->assertSee('aria-current="page"', false);
    }
}
