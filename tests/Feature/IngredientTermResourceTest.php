<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Lunar\Admin\Models\Staff;
use Modules\Ia\Filament\Resources\IngredientTermResource\Pages\ManageIngredientTerms;
use Modules\Ia\Models\IngredientTerm;
use Tests\TestCase;

class IngredientTermResourceTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): Staff
    {
        return Staff::factory()->create(['admin' => true]);
    }

    public function test_the_glossary_page_renders_the_entries_and_the_output(): void
    {
        IngredientTerm::create([
            'category' => 'acides',
            'aliases' => ['citrique', 'acide citrique'],
            'name' => ['fr' => 'citrique', 'nl' => 'citroenzuur', 'en' => 'citric'],
            'reviewed_at' => now(),
        ]);

        // Regression test: TextColumn renders an array state as one call per item, joined automatically —
        // a formatStateUsing() that tried to json_decode() each individual string silently emptied the cell.
        Livewire::actingAs($this->actingAsAdmin(), 'staff')
            ->test(ManageIngredientTerms::class)
            ->assertSuccessful()
            ->assertSee('citrique')
            ->assertSee('acide citrique');
    }

    public function test_creating_an_entry_marked_reviewed_stores_a_timestamp_and_normalizes_entries(): void
    {
        Livewire::actingAs($this->actingAsAdmin(), 'staff')
            ->test(ManageIngredientTerms::class)
            ->callTableAction('create', data: [
                'category' => 'acides',
                'aliases' => ['Tartrique', 'Acide Tartrique'],
                'name' => ['fr' => 'tartrique'],
                'reviewed' => true,
            ])
            ->assertHasNoTableActionErrors();

        $term = IngredientTerm::sole();
        $this->assertNotNull($term->reviewed_at);
        // Stored without case/accents, so the glossary lookup (also case/accent-insensitive) matches later.
        $this->assertSame(['tartrique', 'acide tartrique'], $term->aliases);
        $this->assertSame('tartrique', $term->getTranslation('name', 'fr'));
    }

    public function test_editing_an_entry_only_touches_french_and_preserves_existing_translations(): void
    {
        $term = IngredientTerm::create([
            'category' => 'acides',
            'aliases' => ['citrique'],
            'name' => ['fr' => 'citrique', 'nl' => 'citroenzuur', 'en' => 'citric'],
            'reviewed_at' => now(),
        ]);

        Livewire::actingAs($this->actingAsAdmin(), 'staff')
            ->test(ManageIngredientTerms::class)
            ->mountTableAction('edit', record: $term)
            ->assertTableActionDataSet(['reviewed' => true])
            ->fillForm([
                'category' => 'acides',
                'aliases' => ['citrique'],
                'name' => ['fr' => 'citrique corrigé'],
                'reviewed' => false,
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $term->refresh();
        $this->assertNull($term->reviewed_at);
        $this->assertSame('citrique corrigé', $term->getTranslation('name', 'fr'));
        // The form never exposed NL/EN: editing the French text must not wipe them.
        $this->assertSame('citroenzuur', $term->getTranslation('name', 'nl'));
        $this->assertSame('citric', $term->getTranslation('name', 'en'));
    }
}
