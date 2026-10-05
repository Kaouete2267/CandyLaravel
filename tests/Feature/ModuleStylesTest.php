<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Vite;
use Lunar\Admin\Models\Staff;
use Modules\Stock\Filament\Pages\StockGrid;
use Modules\Support\ModuleStyles;
use Modules\Types\Filament\Resources\CandyTypeResource;
use Tests\TestCase;

/**
 * Le CSS d'un module (modules/<Nom>/resources/css/module.css) n'est chargé que sur les pages qu'il déclare
 * (voir Modules\Support\ModuleStyles).
 */
class ModuleStylesTest extends TestCase
{
    use RefreshDatabase;

    private function stylesheetOf(string $module): string
    {
        return app(Vite::class)->asset(ModuleStyles::entryPoint($module));
    }

    public function test_module_styles_are_loaded_only_on_the_module_pages(): void
    {
        $this->actingAs(Staff::factory()->create(['admin' => true]), 'staff');

        $this->get(StockGrid::getUrl())->assertOk()->assertSee($this->stylesheetOf('Stock'), false);
        $this->get(CandyTypeResource::getUrl('index'))->assertOk()->assertDontSee($this->stylesheetOf('Stock'), false);
    }

    public function test_panel_wide_module_styles_are_loaded_on_every_page(): void
    {
        $this->actingAs(Staff::factory()->create(['admin' => true]), 'staff');

        $this->get(CandyTypeResource::getUrl('index'))
            ->assertOk()
            ->assertSee($this->stylesheetOf('Menu'), false)
            ->assertSee($this->stylesheetOf('BackendHeader'), false);
    }
}
