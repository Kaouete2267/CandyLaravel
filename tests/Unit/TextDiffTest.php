<?php

namespace Tests\Unit;

use Modules\Ia\Support\TextDiff;
use PHPUnit\Framework\TestCase;

class TextDiffTest extends TestCase
{
    public function test_unchanged_ingredients_are_rendered_plain(): void
    {
        $html = (string) TextDiff::html('sucre, gélatine', 'sucre, gélatine');

        $this->assertSame('sucre<span class="ia-diff-sep">, </span>gélatine', $html);
    }

    public function test_a_genuinely_new_ingredient_is_marked_as_an_insertion(): void
    {
        $html = (string) TextDiff::html('sucre', 'sucre, gélatine');

        $this->assertStringContainsString('<ins class="ia-diff-ins">gélatine</ins>', $html);
        $this->assertStringNotContainsString('ia-diff-del', $html);
        $this->assertStringNotContainsString('ia-diff-move', $html);
    }

    public function test_a_genuinely_removed_ingredient_is_marked_as_a_deletion(): void
    {
        $html = (string) TextDiff::html('sucre, gélatine', 'sucre');

        $this->assertStringContainsString('<del class="ia-diff-del">gélatine</del>', $html);
        $this->assertStringNotContainsString('ia-diff-ins', $html);
        $this->assertStringNotContainsString('ia-diff-move', $html);
    }

    public function test_reordering_ingredients_is_marked_as_a_move_not_a_change(): void
    {
        // Alphabetical reordering: identical content, only the position changes.
        $html = (string) TextDiff::html('arôme fraise, sucre', 'sucre, arôme fraise');

        $this->assertStringNotContainsString('ia-diff-del', $html);
        $this->assertStringNotContainsString('ia-diff-ins', $html);
        // Shown once, at its new position — not once where it was AND once where it went.
        $this->assertSame(1, substr_count($html, 'ia-diff-move'));
        $this->assertSame('sucre<span class="ia-diff-sep">, </span><span class="ia-diff-move">arôme fraise</span>', $html);
    }

    public function test_a_real_edit_is_not_confused_with_a_move(): void
    {
        // "gelatine de porc" -> "gélatine (bovine)" is a real content change, not a reorder.
        $html = (string) TextDiff::html(
            'sucre, gelatine de porc, arome fraise',
            'sucre, gélatine (bovine), arôme : fraise'
        );

        $this->assertStringContainsString('<del class="ia-diff-del">gelatine de porc</del>', $html);
        $this->assertStringContainsString('<ins class="ia-diff-ins">gélatine (bovine)</ins>', $html);
        $this->assertStringNotContainsString('ia-diff-move', $html);
    }

    public function test_case_only_differences_are_not_highlighted_as_changes(): void
    {
        // Gemini always normalizes to lowercase: a case-only difference from the old value is not a real
        // content change, and flagging it as one would bury genuine changes under noise.
        $html = (string) TextDiff::html('Sucre, Gélatine', 'sucre, gélatine');

        $this->assertStringNotContainsString('ia-diff-del', $html);
        $this->assertStringNotContainsString('ia-diff-ins', $html);
        $this->assertStringNotContainsString('ia-diff-move', $html);
        // The rendered text reflects what the field now contains (the "after" casing).
        $this->assertSame('sucre<span class="ia-diff-sep">, </span>gélatine', $html);
    }

    public function test_accent_only_differences_are_not_highlighted_as_changes(): void
    {
        $html = (string) TextDiff::html('arome fraise', 'arôme fraise');

        $this->assertStringNotContainsString('ia-diff-del', $html);
        $this->assertStringNotContainsString('ia-diff-ins', $html);
    }

    public function test_a_comma_inside_parentheses_does_not_split_the_group_into_separate_tokens(): void
    {
        // "acides (citrique, malique)" is ONE ingredient group: the inner comma must not be treated as a
        // separator, or the group gets shattered into fragments that no longer align between before/after.
        $html = (string) TextDiff::html('acides (citrique, malique)', 'acides (citrique, malique)');

        $this->assertSame('acides (citrique, malique)', $html);
    }

    public function test_renaming_a_group_is_one_deletion_and_one_insertion_not_a_scrambled_mess(): void
    {
        // Regression test: before the tokenizer respected parentheses, splitting inside both groups produced
        // fragments that partially matched each other, rendering as a garbled, duplicated-looking diff.
        $html = (string) TextDiff::html(
            'sucre, acides alimentaires (acide malique, acide citrique), arôme',
            'sucre, acides (citrique, malique), arôme'
        );

        $this->assertSame(1, substr_count($html, 'ia-diff-del'));
        $this->assertSame(1, substr_count($html, 'ia-diff-ins'));
        $this->assertStringContainsString('<del class="ia-diff-del">acides alimentaires (acide malique, acide citrique)</del>', $html);
        $this->assertStringContainsString('<ins class="ia-diff-ins">acides (citrique, malique)</ins>', $html);
    }

    public function test_a_multi_code_colorant_group_stays_a_single_token(): void
    {
        $html = (string) TextDiff::html(
            'colorants (E100, E131, E133)',
            'colorants (E100, E131, E133, E163)'
        );

        $this->assertSame(1, substr_count($html, 'ia-diff-del'));
        $this->assertSame(1, substr_count($html, 'ia-diff-ins'));
    }

    public function test_both_blank_renders_a_placeholder(): void
    {
        $html = (string) TextDiff::html(null, '');

        $this->assertSame('<span class="ia-diff-empty">(vide)</span>', $html);
    }

    public function test_html_is_escaped(): void
    {
        $html = (string) TextDiff::html('', '<script>alert(1)</script>');

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }
}
