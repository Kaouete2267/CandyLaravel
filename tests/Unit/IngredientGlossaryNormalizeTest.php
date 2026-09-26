<?php

namespace Tests\Unit;

use Modules\Ia\Services\IngredientGlossary;
use PHPUnit\Framework\TestCase;

class IngredientGlossaryNormalizeTest extends TestCase
{
    public function test_case_and_accents_are_ignored(): void
    {
        $this->assertSame(
            IngredientGlossary::normalize('Acide Citrique'),
            IngredientGlossary::normalize('acide citrique')
        );
    }

    public function test_a_leading_de_preposition_is_ignored(): void
    {
        // The AI is inconsistent about keeping "de"/"d'" from one extraction to the next for the same
        // real-world ingredient ("de palme" vs "palme") — without this, they'd be treated as two different
        // ingredients and both end up in the final list.
        $this->assertSame(
            IngredientGlossary::normalize('de palme'),
            IngredientGlossary::normalize('palme')
        );
        $this->assertSame(
            IngredientGlossary::normalize('de glucose-fructose'),
            IngredientGlossary::normalize('glucose-fructose')
        );
        $this->assertSame(
            IngredientGlossary::normalize("d'abeille"),
            IngredientGlossary::normalize('abeille')
        );
    }

    public function test_a_word_that_merely_starts_with_de_is_not_mistaken_for_the_preposition(): void
    {
        // "dextrose" must not be stripped down to "xtrose": only "de " (with the space) or "d'" is a
        // preposition, not any word that happens to start with those letters.
        $this->assertSame('dextrose', IngredientGlossary::normalize('dextrose'));
    }
}
