<?php

namespace Tests\Feature;

use Database\Seeders\BonbonBaseSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Lunar\Admin\Filament\Resources\ProductResource\Pages\EditProduct;
use Lunar\Admin\Models\Staff;
use Modules\Allergenes\Models\Allergen;
use Modules\Ia\Services\IngredientsAiWriter;
use Modules\Support\ProductCreator;
use Tests\TestCase;

class ProductIngredientsAiExtensionTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): Staff
    {
        return tap(Staff::factory()->create(['admin' => true]), fn () => config(['bonbon.gemini.key' => 'test-key']));
    }

    /**
     * Simule la réponse de {@see IngredientsAiWriter} : chaque langue reçoit ses ingrédients joints par une virgule.
     *
     * @param  array<int, array{fr: string, nl: string, en: string}>  $items
     * @param  array<int, array{type: string, source: string, detail: string}>  $notes
     */
    private function fakeRewrite(array $items, array $notes = [], ?string $sourceFr = null): void
    {
        $ingredients = [];
        foreach (['fr', 'nl', 'en'] as $locale) {
            $ingredients[$locale] = implode(', ', array_column($items, $locale));
        }

        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => [
            ['content' => ['parts' => [['text' => json_encode(array_filter(['sourceFr' => $sourceFr, 'ingredients' => $ingredients, 'notes' => $notes], fn ($value) => $value !== null))]]]],
        ]])]);
    }

    public function test_scanning_a_foreign_language_photo_translates_and_applies_ingredients_in_one_click(): void
    {
        $this->seed(BonbonBaseSeeder::class);
        $staff = $this->actingAsAdmin();

        $product = ProductCreator::create([
            'name' => ['fr' => 'Fraises Tagada'],
            'ingredients' => ['fr' => 'ancien texte'],
        ]);

        Http::fake(['generativelanguage.googleapis.com/*' => Http::sequence()
            ->push(['candidates' => [['content' => ['parts' => [['text' => json_encode([
                'raw' => 'zucker, gelatine, aroma erdbeere',
                'allergen_mention' => 'Kann Milch und Haselnüsse enthalten.',
                'highlighted_allergens' => ['Milch', 'Haselnüsse'],
                'notes' => null,
            ])]]]]]])
            ->push(['candidates' => [['content' => ['parts' => [['text' => json_encode([
                'ingredients' => [
                    'fr' => 'sucre, gélatine, arôme fraise',
                    'nl' => 'suiker, gelatine, aroma aardbei',
                    'en' => 'sugar, gelatin, flavour strawberry',
                ],
                'notes' => [],
            ])]]]]]]),
        ]);

        $livewire = Livewire::actingAs($staff, 'staff')
            ->test(EditProduct::class, ['record' => $product->getKey()])
            ->callAction(
                TestAction::make('updateIngredientsWithAi')->schemaComponent('ingredientsAiActions'),
                data: ['photo' => UploadedFile::fake()->image('label.jpg', 400, 300), 'raw' => ''],
            )
            ->assertHasNoFormErrors(form: 'form')
            ->assertFormSet([
                'ia_before_fr' => 'ancien texte',
                'attribute_data.ingredients_scan' => 'zucker, gelatine, aroma erdbeere',
                // The trace mention read on the photo is kept verbatim, with the words printed in bold listed
                // apart, so a human can justify every "may contain" box.
                'attribute_data.allergens_scan' => "Kann Milch und Haselnüsse enthalten.\nMis en évidence sur l'emballage : Milch, Haselnüsse",
                'attribute_data.ingredients.fr' => 'sucre, gélatine, arôme fraise',
                'attribute_data.ingredients.nl' => 'suiker, gelatine, aroma aardbei',
                'attribute_data.ingredients.en' => 'sugar, gelatin, flavour strawberry',
            ]);

        Http::assertSentCount(2);

        // No second click needed: the comparison modal opens by itself, prefilled from the just-computed state.
        $livewire->assertActionMounted(TestAction::make('reviewIngredientsDiff')->schemaComponent('ingredientsAiActions'))
            ->assertActionDataSet([
                'raw' => 'zucker, gelatine, aroma erdbeere',
                'after_fr' => 'sucre, gélatine, arôme fraise',
                'allergens_raw' => "Kann Milch und Haselnüsse enthalten.\nMis en évidence sur l'emballage : Milch, Haselnüsse",
            ]);

        // The modal is organized as an input → AI suggestion → user-validated pipeline, each section clearly
        // labelled and visually set apart (Filament Section components).
        foreach ([
            'Résultats de l\'analyse par IA',
            'Données utilisées par l\'IA',
            'Suggestions de l\'IA',
            'Données validées par l\'utilisateur',
        ] as $heading) {
            $livewire->assertMountedActionModalSee($heading);
        }

        // Regression test: the "Suggestions de l'IA" diff and the allergens block are computed in
        // ->fillForm() and mirrored into fields local to this action's own schema (ia_before_*,
        // attribute_data.* etc. cannot be read reliably via Get() from inside a nested Html component) — so
        // the actual before/after text, not just the section headings, must render.
        $livewire->assertMountedActionModalSee('ancien texte')
            ->assertMountedActionModalSee('sucre');
    }

    public function test_the_ocr_text_can_be_corrected_and_relaunched_without_closing_the_modal(): void
    {
        $this->seed(BonbonBaseSeeder::class);
        $staff = $this->actingAsAdmin();

        $product = ProductCreator::create(['name' => ['fr' => 'Fraises Tagada']]);

        // Simulates state left behind by a previous run whose OCR read the packaging wrong.
        $livewire = Livewire::actingAs($staff, 'staff')
            ->test(EditProduct::class, ['record' => $product->getKey()])
            ->fillForm([
                'attribute_data.ingredients_scan' => 'sucrre, gelatlne',
                'ia_has_run' => true,
                'ia_before_fr' => 'texte avant tout run IA',
                'attribute_data.ingredients.fr' => 'sucre, gélatine (mauvais OCR)',
            ]);

        // The user fixes the raw text by hand and relaunches: only the rewrite step re-runs, no new photo.
        $this->fakeRewrite([
            ['fr' => 'sucre', 'nl' => 'suiker', 'en' => 'sugar'],
            ['fr' => 'gélatine', 'nl' => 'gelatine', 'en' => 'gelatin'],
        ]);

        $livewire->callAction(
            TestAction::make('reviewIngredientsDiff')->schemaComponent('ingredientsAiActions')->arguments(['relaunch' => true]),
            data: ['raw' => 'sucre, gelatine'],
        )
            ->assertHasNoFormErrors(form: 'form')
            // The modal is still open, now showing the corrected result — the user never had to close it.
            ->assertActionMounted(TestAction::make('reviewIngredientsDiff')->schemaComponent('ingredientsAiActions'))
            ->assertActionDataSet(['raw' => 'sucre, gelatine', 'after_fr' => 'sucre, gélatine'])
            ->assertFormSet(['attribute_data.ingredients.fr' => 'sucre, gélatine']);

        // The re-rendered "Suggestions de l'IA" reflects the fresh (post-relaunch) content: the original
        // "avant" snapshot (untouched by relaunch) diffed against the newly-applied "sucre, gélatine".
        $livewire->assertMountedActionModalSee('texte avant tout run IA')
            ->assertMountedActionModalSee('gélatine');

        Http::assertSentCount(1);
    }

    public function test_the_final_result_can_be_hand_edited_before_validating(): void
    {
        $this->seed(BonbonBaseSeeder::class);
        $staff = $this->actingAsAdmin();

        $product = ProductCreator::create(['name' => ['fr' => 'Fraises Tagada']]);

        // Simulates state left behind by a previous (successful) run.
        $livewire = Livewire::actingAs($staff, 'staff')
            ->test(EditProduct::class, ['record' => $product->getKey()])
            ->fillForm([
                'attribute_data.ingredients_scan' => 'sucre, gelatine',
                'ia_has_run' => true,
                'attribute_data.ingredients.fr' => 'sucre, gélatine',
            ]);

        // The AI's phrasing isn't quite right: the user tweaks it by hand before validating, no AI call needed.
        $livewire->callAction(
            TestAction::make('reviewIngredientsDiff')->schemaComponent('ingredientsAiActions'),
            data: ['after_fr' => 'sucre, gélatine (origine porcine)', 'allergens_raw' => 'Peut contenir du lait.'],
        )
            ->assertHasNoFormErrors(form: 'form')
            ->assertFormSet([
                'attribute_data.ingredients.fr' => 'sucre, gélatine (origine porcine)',
                'attribute_data.allergens_scan' => 'Peut contenir du lait.',
            ]);

        Http::assertNothingSent();
    }

    public function test_pasting_raw_text_without_a_photo_still_translates_and_applies(): void
    {
        $this->seed(BonbonBaseSeeder::class);
        $staff = $this->actingAsAdmin();

        $product = ProductCreator::create(['name' => ['fr' => 'Fraises Tagada']]);

        $this->fakeRewrite([
            ['fr' => 'sucre', 'nl' => 'suiker', 'en' => 'sugar'],
            ['fr' => 'gélatine', 'nl' => 'gelatine', 'en' => 'gelatin'],
        ]);

        Livewire::actingAs($staff, 'staff')
            ->test(EditProduct::class, ['record' => $product->getKey()])
            ->callAction(
                TestAction::make('updateIngredientsWithAi')->schemaComponent('ingredientsAiActions'),
                data: ['raw' => 'Sucre, gelatine'],
            )
            ->assertHasNoFormErrors(form: 'form')
            ->assertFormSet([
                'attribute_data.ingredients.fr' => 'sucre, gélatine',
                'attribute_data.ingredients.nl' => 'suiker, gelatine',
                'attribute_data.ingredients.en' => 'sugar, gelatin',
            ]);

        Http::assertSentCount(1);
    }

    public function test_detected_allergens_are_added_without_removing_manually_checked_ones(): void
    {
        $this->seed(BonbonBaseSeeder::class);
        $staff = $this->actingAsAdmin();

        $gluten = Allergen::create(['code' => 'gluten', 'name' => ['fr' => 'Gluten'], 'position' => 1]);
        $milk = Allergen::create(['code' => 'milk', 'name' => ['fr' => 'Lait'], 'position' => 2]);
        $manuallyChecked = Allergen::create(['code' => 'soja', 'name' => ['fr' => 'Soja'], 'position' => 3]);

        $product = ProductCreator::create(['name' => ['fr' => 'Fraises Tagada']]);

        $this->fakeRewrite([
            ['fr' => 'farine de blé', 'nl' => 'x', 'en' => 'x'],
            ['fr' => 'lait entier en poudre', 'nl' => 'x', 'en' => 'x'],
        ]);

        $livewire = Livewire::actingAs($staff, 'staff')
            ->test(EditProduct::class, ['record' => $product->getKey()])
            ->fillForm(['allergens_contains' => [(string) $manuallyChecked->id]])
            ->callAction(
                TestAction::make('updateIngredientsWithAi')->schemaComponent('ingredientsAiActions'),
                data: ['raw' => 'wheat flour, milk powder'],
            )
            ->assertHasNoFormErrors(form: 'form');

        $this->assertEqualsCanonicalizing(
            [$gluten->id, $milk->id, $manuallyChecked->id],
            array_map('intval', $livewire->get('data.allergens_contains')),
        );
    }

    public function test_it_refuses_to_run_without_a_photo_or_pasted_text(): void
    {
        $this->seed(BonbonBaseSeeder::class);
        $staff = $this->actingAsAdmin();

        $product = ProductCreator::create([
            'name' => ['fr' => 'Fraises Tagada'],
            'ingredients' => ['fr' => 'texte original'],
        ]);

        // Errors are shown inline inside the still-open modal (no toast), so the modal must stay mounted.
        $livewire = Livewire::actingAs($staff, 'staff')
            ->test(EditProduct::class, ['record' => $product->getKey()])
            ->callAction(
                TestAction::make('updateIngredientsWithAi')->schemaComponent('ingredientsAiActions'),
                data: ['raw' => ''],
            )
            ->assertActionMounted(TestAction::make('updateIngredientsWithAi')->schemaComponent('ingredientsAiActions'))
            ->assertFormSet([
                'ia_message_type' => 'danger',
                'ia_message' => 'Rien à traiter : ajoutez une photo ou saisissez une liste d\'ingrédients.',
                'attribute_data.ingredients.fr' => 'texte original',
            ]);

        $livewire->assertMountedActionModalSee('Rien à traiter : ajoutez une photo ou saisissez une liste d\'ingrédients.');

        Http::assertNothingSent();
    }

    public function test_the_raw_scanned_text_is_kept_as_is_when_the_product_is_saved(): void
    {
        $this->seed(BonbonBaseSeeder::class);
        $staff = $this->actingAsAdmin();

        $product = ProductCreator::create([
            'name' => ['fr' => 'Fraises Tagada'],
            'allergens_scan' => 'Peut contenir du lait.',
        ]);

        $this->fakeRewrite([
            ['fr' => 'sucre', 'nl' => 'suiker', 'en' => 'sugar'],
            ['fr' => 'gélatine', 'nl' => 'gelatine', 'en' => 'gelatin'],
        ]);

        $livewire = Livewire::actingAs($staff, 'staff')
            ->test(EditProduct::class, ['record' => $product->getKey()]);

        // Rendered once, by the AI section only — not a second time among Lunar's own attribute fields.
        $this->assertSame(1, substr_count($livewire->html(), 'Ingrédients bruts (scan)'));
        $this->assertSame(1, substr_count($livewire->html(), 'Mentions allergènes (scan)'));

        // Pasted text only: nothing was read from the packaging, so the previous allergen scan is kept.
        $livewire
            ->callAction(
                TestAction::make('updateIngredientsWithAi')->schemaComponent('ingredientsAiActions'),
                data: ['raw' => 'Zucker,  Gelatine (Rind)'],
            )
            ->assertFormSet([
                'attribute_data.ingredients_scan' => 'Zucker,  Gelatine (Rind)',
                'attribute_data.allergens_scan' => 'Peut contenir du lait.',
            ])
            ->call('save')
            ->assertHasNoFormErrors(form: 'form');

        $this->assertSame('Zucker,  Gelatine (Rind)', $product->fresh()->translateAttribute('ingredients_scan'));
        $this->assertSame('Peut contenir du lait.', $product->fresh()->translateAttribute('allergens_scan'));
    }

    public function test_allergen_statements_can_be_typed_in_the_update_modal_without_a_photo(): void
    {
        $this->seed(BonbonBaseSeeder::class);
        $staff = $this->actingAsAdmin();

        $product = ProductCreator::create(['name' => ['fr' => 'Fraises Tagada']]);

        $this->fakeRewrite([['fr' => 'sucre', 'nl' => 'suiker', 'en' => 'sugar']]);

        Livewire::actingAs($staff, 'staff')
            ->test(EditProduct::class, ['record' => $product->getKey()])
            ->mountAction(TestAction::make('updateIngredientsWithAi')->schemaComponent('ingredientsAiActions'))
            ->assertMountedActionModalSee('Mentions allergènes (si pas de photo, ou à corriger)')
            ->fillForm(['raw' => 'Sucre', 'allergens_raw' => 'Peut contenir des traces de soja.'])
            ->callMountedAction()
            ->assertFormSet(['attribute_data.allergens_scan' => 'Peut contenir des traces de soja.']);
    }

    public function test_the_shortcut_next_to_the_ingredients_field_opens_the_ai_update_modal(): void
    {
        $this->seed(BonbonBaseSeeder::class);
        $staff = $this->actingAsAdmin();

        $product = ProductCreator::create(['name' => ['fr' => 'Fraises Tagada']]);

        Livewire::actingAs($staff, 'staff')
            ->test(EditProduct::class, ['record' => $product->getKey()])
            ->callAction(TestAction::make('updateIngredientsWithAiShortcut')->schemaComponent('attributeData.ingredients'))
            ->assertActionMounted(TestAction::make('updateIngredientsWithAi')->schemaComponent('ingredientsAiActions'));
    }

    public function test_debug_mode_shows_the_prompt_and_the_raw_ai_response_in_the_results_modal(): void
    {
        $this->seed(BonbonBaseSeeder::class);
        $staff = $this->actingAsAdmin();

        $product = ProductCreator::create(['name' => ['fr' => 'Fraises Tagada']]);

        $this->fakeRewrite([['fr' => 'sucre', 'nl' => 'suiker', 'en' => 'sugar']]);

        $livewire = Livewire::actingAs($staff, 'staff')
            ->test(EditProduct::class, ['record' => $product->getKey()])
            ->callAction(
                TestAction::make('updateIngredientsWithAi')->schemaComponent('ingredientsAiActions'),
                data: ['raw' => 'Sucre', 'debug' => true],
            );

        $livewire->assertMountedActionModalSee('Prompt envoyé')
            ->assertMountedActionModalSee('« Sucre »')
            ->assertMountedActionModalSee('"suiker"');
    }

    public function test_ai_notes_are_shown_escaped_in_the_suggestions_section(): void
    {
        $this->seed(BonbonBaseSeeder::class);
        $staff = $this->actingAsAdmin();

        $product = ProductCreator::create(['name' => ['fr' => 'Fraises Tagada']]);

        $this->fakeRewrite(
            [['fr' => 'sucre, colorant : E133', 'nl' => 'suiker, kleurstof: E133', 'en' => 'sugar, colour: E133']],
            notes: [['type' => 'Remplacé par un code E', 'source' => 'Brillantblau', 'detail' => 'Bleu brillant remplacé par E133<script>alert(2)</script>']],
        );

        $livewire = Livewire::actingAs($staff, 'staff')
            ->test(EditProduct::class, ['record' => $product->getKey()])
            ->callAction(
                TestAction::make('updateIngredientsWithAi')->schemaComponent('ingredientsAiActions'),
                data: ['raw' => 'Zucker, Brillantblau'],
            )
            ->assertFormSet(['ia_message' => null]);

        $livewire->assertMountedActionModalSee('<strong>Remplacé par un code E</strong>', escape: false)
            ->assertMountedActionModalSee('Bleu brillant remplacé par E133')
            ->assertMountedActionModalDontSee('<script>alert(2)', escape: false);
    }

    public function test_debug_output_is_hidden_when_debug_mode_is_unchecked(): void
    {
        $this->seed(BonbonBaseSeeder::class);
        $staff = $this->actingAsAdmin();

        $product = ProductCreator::create(['name' => ['fr' => 'Fraises Tagada']]);

        $this->fakeRewrite([['fr' => 'sucre', 'nl' => 'suiker', 'en' => 'sugar']]);

        Livewire::actingAs($staff, 'staff')
            ->test(EditProduct::class, ['record' => $product->getKey()])
            ->callAction(
                TestAction::make('updateIngredientsWithAi')->schemaComponent('ingredientsAiActions'),
                data: ['raw' => 'Sucre'],
            )
            ->assertMountedActionModalDontSee('Prompt envoyé');
    }

    public function test_comparison_modal_is_hidden_until_something_has_been_scanned(): void
    {
        $this->seed(BonbonBaseSeeder::class);
        $staff = $this->actingAsAdmin();

        $product = ProductCreator::create(['name' => ['fr' => 'Fraises Tagada']]);

        // A raw scan saved on a previous visit doesn't count: there is no "before" left to compare against.
        Livewire::actingAs($staff, 'staff')
            ->test(EditProduct::class, ['record' => $product->getKey()])
            ->fillForm(['attribute_data.ingredients_scan' => 'sucre, gelatine'])
            ->assertDontSee('Comparer avant / après');
    }

    public function test_the_user_picks_the_starting_text_when_the_last_scan_and_the_product_list_differ(): void
    {
        $this->seed(BonbonBaseSeeder::class);
        $staff = $this->actingAsAdmin();

        $product = ProductCreator::create([
            'name' => ['fr' => 'Fraises Tagada'],
            'ingredients' => ['fr' => 'sucre, acides (citrique, malique)'],
        ]);

        $livewire = Livewire::actingAs($staff, 'staff')
            ->test(EditProduct::class, ['record' => $product->getKey()])
            ->fillForm(['attribute_data.ingredients_scan' => 'sucre, régulateur d\'acidité E330, E296'])
            ->mountAction(TestAction::make('updateIngredientsWithAi')->schemaComponent('ingredientsAiActions'))
            ->assertMountedActionModalSee('Texte de départ')
            // Neither text is picked for the user: running without choosing fails instead of guessing.
            ->assertActionDataSet(['source' => null, 'raw' => '']);

        $livewire->fillForm(['source' => 'scan'])
            ->assertActionDataSet(['raw' => 'sucre, régulateur d\'acidité E330, E296'])
            ->fillForm(['source' => 'product'])
            ->assertActionDataSet(['raw' => 'sucre, acides (citrique, malique)']);
    }

    public function test_the_starting_text_is_not_asked_when_the_scan_matches_the_product_list(): void
    {
        $this->seed(BonbonBaseSeeder::class);
        $staff = $this->actingAsAdmin();

        $product = ProductCreator::create([
            'name' => ['fr' => 'Fraises Tagada'],
            'ingredients' => ['fr' => 'sucre, gélatine'],
        ]);

        Livewire::actingAs($staff, 'staff')
            ->test(EditProduct::class, ['record' => $product->getKey()])
            ->fillForm(['attribute_data.ingredients_scan' => 'sucre, gélatine'])
            ->mountAction(TestAction::make('updateIngredientsWithAi')->schemaComponent('ingredientsAiActions'))
            ->assertMountedActionModalDontSee('Texte de départ')
            ->assertActionDataSet(['raw' => 'sucre, gélatine']);
    }

    public function test_the_french_suggestion_is_compared_with_the_translated_source_not_the_old_product_text(): void
    {
        $this->seed(BonbonBaseSeeder::class);
        $staff = $this->actingAsAdmin();

        $product = ProductCreator::create([
            'name' => ['fr' => 'Fraises Tagada'],
            'ingredients' => ['fr' => 'ancienne liste de la fiche'],
        ]);

        $this->fakeRewrite(
            [['fr' => 'sucre, régulateur d\'acidité (acide citrique)', 'nl' => 'suiker', 'en' => 'sugar']],
            sourceFr: 'sucre, régulateur d\'acidité E330',
        );

        Livewire::actingAs($staff, 'staff')
            ->test(EditProduct::class, ['record' => $product->getKey()])
            ->callAction(
                TestAction::make('updateIngredientsWithAi')->schemaComponent('ingredientsAiActions'),
                data: ['raw' => 'Zucker, Säureregulator E330'],
            )
            ->assertMountedActionModalSee('E330')
            ->assertMountedActionModalDontSee('ancienne liste de la fiche');
    }
}
