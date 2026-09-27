<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\Admin\Filament\Pages\Dashboard;
use Lunar\Admin\Filament\Resources\TaxClassResource;
use Lunar\Admin\Models\Staff;
use Modules\Allergenes\Filament\Resources\AllergenResource;
use Modules\Ia\Filament\Pages\CreateProductWithAi;
use Modules\Ia\Filament\Pages\IaSettings;
use Modules\Ia\Filament\Resources\IngredientTermResource;
use Modules\Invitations\Filament\Resources\InvitationResource;
use Modules\Panneaux\Filament\Resources\PanelResource;
use Modules\Stock\Filament\Pages\StockGrid;
use Modules\Stock\Filament\Resources\StockMovementResource;
use Modules\Types\Filament\Resources\CandyTypeResource;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Permissions du personnel créées par les modules (Modules\Support\StaffPermissions), à cocher dans
 * Paramètres › Personnel › Contrôle d'accès.
 */
class AdminAccessControlTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  list<string>  $permissions
     */
    private function staffWith(array $permissions = []): Staff
    {
        return Staff::factory()->create(['admin' => false])->givePermissionTo($permissions);
    }

    public function test_the_dashboard_opens_for_staff_with_the_dashboard_permission(): void
    {
        $this->actingAs($this->staffWith(['dashboard']), 'staff')
            ->get(Dashboard::getUrl())
            ->assertOk();
    }

    public function test_the_dashboard_redirects_staff_without_permission_to_their_first_accessible_page(): void
    {
        $this->actingAs($this->staffWith(['confiserie', 'confiserie:manage-ingredient-terms']), 'staff')
            ->get(Dashboard::getUrl())
            ->assertRedirect(IngredientTermResource::getUrl('index'));
    }

    public function test_the_dashboard_forbids_staff_who_can_access_no_page_at_all(): void
    {
        $this->actingAs($this->staffWith(), 'staff')
            ->get(Dashboard::getUrl())
            ->assertForbidden();
    }

    public function test_taxes_forbid_staff_with_core_settings_only(): void
    {
        $this->actingAs($this->staffWith(['settings', 'settings:core']), 'staff')
            ->get(TaxClassResource::getUrl('index'))
            ->assertForbidden();
    }

    public function test_taxes_open_for_staff_with_the_taxes_permission(): void
    {
        $this->actingAs($this->staffWith(['settings', 'settings:manage-taxes']), 'staff')
            ->get(TaxClassResource::getUrl('index'))
            ->assertOk();
    }

    /**
     * @param  class-string  $component  Page ou resource Filament
     */
    #[DataProvider('restrictedComponents')]
    public function test_restricted_pages_forbid_staff_without_their_permission(string $component, string $permission): void
    {
        $this->actingAs($this->staffWith([$this->sectionOf($permission)]), 'staff')
            ->get($this->urlOf($component))
            ->assertForbidden();
    }

    /**
     * @param  class-string  $component  Page ou resource Filament
     */
    #[DataProvider('restrictedComponents')]
    public function test_restricted_pages_open_for_staff_with_their_permission(string $component, string $permission): void
    {
        $this->actingAs($this->staffWith([$this->sectionOf($permission), $permission]), 'staff')
            ->get($this->urlOf($component))
            ->assertOk();
    }

    /**
     * @param  class-string  $component
     */
    private function urlOf(string $component): string
    {
        return is_subclass_of($component, Resource::class) ? $component::getUrl('index') : $component::getUrl();
    }

    private function sectionOf(string $permission): string
    {
        return explode(':', $permission)[0];
    }

    /**
     * @return array<string, array{class-string, string}>
     */
    public static function restrictedComponents(): array
    {
        return [
            'réglages IA' => [IaSettings::class, 'settings:manage-ai'],
            'créer avec l\'IA' => [CreateProductWithAi::class, 'confiserie:create-product-with-ai'],
            'panneaux' => [PanelResource::class, 'confiserie:manage-panels'],
            'types de bonbon' => [CandyTypeResource::class, 'confiserie:manage-candy-types'],
            'stock' => [StockGrid::class, 'confiserie:manage-stock'],
            'historique du stock' => [StockMovementResource::class, 'confiserie:view-stock-history'],
            'allergènes' => [AllergenResource::class, 'confiserie:manage-allergens'],
            'glossaire d\'ingrédients' => [IngredientTermResource::class, 'confiserie:manage-ingredient-terms'],
            'invitations' => [InvitationResource::class, 'confiserie:manage-invitations'],
        ];
    }
}
