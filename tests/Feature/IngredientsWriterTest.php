<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Ia\Models\IngredientTerm;
use Modules\Ia\Services\IngredientsWriter;
use Tests\TestCase;

class IngredientsWriterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['bonbon.gemini.key' => 'test-key']);
    }

    /** @param  array<int, array{category?: ?string, fr: string, nl: string, en: string}>  $items */
    private function fakeItems(array $items, ?string $notes = null): void
    {
        $items = array_map(fn (array $item) => ['category' => null, ...$item], $items);

        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => [
            ['content' => ['parts' => [['text' => json_encode(['items' => $items, 'notes' => $notes])]]]],
        ]])]);
    }

    public function test_it_never_invents_a_functional_category_like_antioxydants(): void
    {
        // The exact real-world case that kept slipping through prompt-only instructions: the same
        // packaging separately lists "acides alimentaires (acide malique, acide citrique)" and, elsewhere,
        // "antioxydants (acide ascorbique, extraits riches en tocophérol)". Both acids must end up in ONE
        // "acides" group, and the extract must not be wrapped in an invented "antioxydants" category.
        $this->fakeItems([
            ['category' => null, 'fr' => 'sucre', 'nl' => 'suiker', 'en' => 'sugar'],
            ['category' => 'acides', 'fr' => 'malique', 'nl' => 'appelzuur', 'en' => 'malic'],
            ['category' => 'acides', 'fr' => 'citrique', 'nl' => 'citroenzuur', 'en' => 'citric'],
            ['category' => 'acides', 'fr' => 'ascorbique', 'nl' => 'ascorbinezuur', 'en' => 'ascorbic'],
            ['category' => 'extraits', 'fr' => 'riche en tocophérol', 'nl' => 'rijk aan tocoferol', 'en' => 'rich in tocopherol'],
        ]);

        $result = app(IngredientsWriter::class)->rewrite('texte source non pertinent pour ce test');

        $this->assertStringNotContainsString('antioxydant', mb_strtolower($result['ingredients']['fr']));
        $this->assertSame(
            'sucre, acides (ascorbique, citrique, malique), extrait riche en tocophérol',
            $result['ingredients']['fr']
        );
    }

    public function test_categories_are_sorted_grouped_and_formatted_per_presentation_rules(): void
    {
        $this->fakeItems([
            ['category' => null, 'fr' => 'sucre', 'nl' => 'suiker', 'en' => 'sugar'],
            ['category' => 'colorants', 'fr' => 'E133', 'nl' => 'E133', 'en' => 'E133'],
            ['category' => 'aromes', 'fr' => 'fraise', 'nl' => 'aardbei', 'en' => 'strawberry'],
            ['category' => 'acides', 'fr' => 'malique', 'nl' => 'appelzuur', 'en' => 'malic'],
            ['category' => 'colorants', 'fr' => 'E100', 'nl' => 'E100', 'en' => 'E100'],
            ['category' => 'acides', 'fr' => 'citrique', 'nl' => 'citroenzuur', 'en' => 'citric'],
        ]);

        $result = app(IngredientsWriter::class)->rewrite('texte source non pertinent pour ce test');

        // Alphabetical within a group ("citrique" before "malique"), arômes second-to-last, colorants last
        // even though they were not last in the source order, and a single flavour has no group wrapper.
        $this->assertSame(
            'sucre, acides (citrique, malique), arôme fraise, colorants (E100, E133)',
            $result['ingredients']['fr']
        );
    }

    public function test_a_single_colorant_uses_the_singular_colon_form_not_a_group(): void
    {
        $this->fakeItems([
            ['category' => null, 'fr' => 'sucre', 'nl' => 'suiker', 'en' => 'sugar'],
            ['category' => 'colorants', 'fr' => 'E133', 'nl' => 'E133', 'en' => 'E133'],
        ]);

        $result = app(IngredientsWriter::class)->rewrite('texte source non pertinent pour ce test');

        $this->assertSame('sucre, colorant : E133', $result['ingredients']['fr']);
    }

    public function test_the_same_ingredient_mentioned_twice_is_only_listed_once(): void
    {
        $this->fakeItems([
            ['category' => 'acides', 'fr' => 'citrique', 'nl' => 'citroenzuur', 'en' => 'citric'],
            ['category' => 'acides', 'fr' => 'Citrique', 'nl' => 'citroenzuur', 'en' => 'citric'],
        ]);

        $result = app(IngredientsWriter::class)->rewrite('texte source non pertinent pour ce test');

        $this->assertSame('acide citrique', $result['ingredients']['fr']);
    }

    public function test_the_same_ingredient_extracted_with_and_without_its_preposition_is_only_listed_once(): void
    {
        // Real-world regression: the AI is inconsistent about keeping "de" ("de palme" vs "palme") across
        // extractions of the same real ingredient, which used to slip past the dedupe check entirely.
        $this->fakeItems([
            ['category' => 'huiles_graisses', 'fr' => 'de palme', 'nl' => 'palmolie', 'en' => 'palm'],
            ['category' => 'huiles_graisses', 'fr' => 'palme', 'nl' => 'palmolie', 'en' => 'palm'],
        ]);

        $result = app(IngredientsWriter::class)->rewrite('texte source non pertinent pour ce test');

        $this->assertSame('huile/graisse de palme', $result['ingredients']['fr']);
        $this->assertSame(1, IngredientTerm::count());
    }

    public function test_an_unrecognized_ingredient_is_added_to_the_glossary_unreviewed(): void
    {
        $this->assertSame(0, IngredientTerm::count());

        $this->fakeItems([
            ['category' => 'acides', 'fr' => 'tartrique', 'nl' => 'wijnsteenzuur', 'en' => 'tartaric'],
        ]);

        app(IngredientsWriter::class)->rewrite('texte source non pertinent pour ce test');

        $term = IngredientTerm::sole();
        $this->assertSame('acides', $term->category);
        $this->assertSame(['tartrique'], $term->aliases);
        $this->assertSame('tartrique', $term->getTranslation('name', 'fr'));
        $this->assertNull($term->reviewed_at);
    }

    public function test_a_known_glossary_entry_overrides_the_ais_own_classification(): void
    {
        // Staff reviewed this once and decided it should NOT be grouped under "acides" (e.g. a house style
        // choice) — that decision must stick even if the AI guesses differently on a later product.
        IngredientTerm::create([
            'category' => null,
            'aliases' => ['ascorbique'],
            'name' => ['fr' => 'vitamine C', 'nl' => 'vitamine C', 'en' => 'vitamin C'],
            'reviewed_at' => now(),
        ]);

        $this->fakeItems([
            ['category' => 'acides', 'fr' => 'ascorbique', 'nl' => 'ascorbinezuur', 'en' => 'ascorbic'],
        ]);

        $result = app(IngredientsWriter::class)->rewrite('texte source non pertinent pour ce test');

        $this->assertSame('vitamine C', $result['ingredients']['fr']);
        $this->assertSame(1, IngredientTerm::count());
    }
}
