<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Modules\Ia\Models\IaSetting;
use Modules\Ia\Services\IngredientsAiWriter;
use Tests\TestCase;

class IngredientsAiWriterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['bonbon.gemini.key' => 'test-key']);
    }

    /** @param  array<string, mixed>  $response */
    private function fakeResponse(array $response): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => [
            ['content' => ['parts' => [['text' => json_encode($response)]]]],
        ]])]);
    }

    public function test_the_ai_formatted_text_and_html_notes_are_returned_as_is(): void
    {
        $this->fakeResponse([
            'ingredients' => [
                'fr' => 'sucre, acides (citrique, malique), colorant : E133',
                'nl' => 'suiker, zuren (appelzuur, citroenzuur), kleurstof: E133',
                'en' => 'sugar, acids (citric, malic), colour: E133',
            ],
            'sourceFr' => 'sucre, acides (citrique, malique), colorant : E133',
            'notes' => [
                ['type' => 'Remplacé par un code E', 'source' => 'Brillantblau', 'result' => 'E133', 'detail' => 'Colorant remplac&eacute; par son code E.'],
                ['type' => 'type_inconnu', 'source' => 'Zucker', 'detail' => 'Les arômes ont été replacés en avant-dernière position.'],
                ['type' => 'Doublon fusionné', 'source' => '<b>x</b>', 'detail' => ''],
            ],
        ]);

        $result = app(IngredientsAiWriter::class)->rewrite('Zucker, Citronensäure, Äpfelsäure, Brillantblau');

        $this->assertSame('sucre, acides (citrique, malique), colorant : E133', $result['ingredients']['fr']);
        $this->assertSame('sugar, acids (citric, malic), colour: E133', $result['ingredients']['en']);
        // Notes typées rendues en liste HTML, texte échappé ; un type hors liste fermée est ignoré.
        $this->assertSame(
            '<p><strong>Différence de caractères</strong> : 0</p><ul><li><strong>Remplacé par un code E</strong> : « Brillantblau » → « E133 » — Colorant remplacé par son code E.</li>'
            .'<li><strong>Doublon fusionné</strong> : « &lt;b&gt;x&lt;/b&gt; »</li></ul>',
            $result['notes']
        );
        $this->assertStringContainsString('« Zucker, Citronensäure, Äpfelsäure, Brillantblau »', $result['debug']['prompt']);
    }

    public function test_the_character_delta_is_counted_from_the_french_source_not_trusted_from_the_ai(): void
    {
        // Real-world case: the AI itself announced "+11" for this rewrite, which is really 25 characters shorter.
        $this->fakeResponse([
            'sourceFr' => 'Sirop de glucose-fructose, sucre, farine de blé, acides alimentaires (acide malique, acide citrique), dextrose, huile de palme, arômes, colorants (E100, E131, E133, E163), antioxydants (acide ascorbique, extraits riches en tocophérol)',
            'ingredients' => [
                'fr' => 'Sirop de glucose-fructose, sucre, farine de blé, acides (citrique, malique), dextrose, huile de palme, antioxydants (acide ascorbique, extraits riches en tocophérol), arômes, colorants (E100, E131, E133, E163)',
                'nl' => 'x',
                'en' => 'x',
            ],
            'notes' => [],
        ]);

        $result = app(IngredientsAiWriter::class)->rewrite('texte source non pertinent pour ce test');

        $this->assertSame('<p><strong>Différence de caractères</strong> : -25</p>', $result['notes']);
    }

    public function test_rules_are_sent_as_system_instruction_with_a_zero_temperature(): void
    {
        $this->fakeResponse(['ingredients' => ['fr' => 'sucre', 'nl' => 'suiker', 'en' => 'sugar'], 'notes' => []]);

        app(IngredientsAiWriter::class)->rewrite('Zucker');

        Http::assertSent(function (Request $request) {
            $userText = $request['contents'][0]['parts'][0]['text'];

            return $request['generationConfig']['temperature'] === 0.0
                && str_contains($request['systemInstruction']['parts'][0]['text'], 'Normes de présentation')
                && str_contains($userText, '« Zucker »')
                && ! str_contains($userText, 'Normes de présentation');
        });
    }

    public function test_settings_overrides_and_imposed_replacements_are_sent_to_the_ai(): void
    {
        IaSetting::current()->update(['features' => [
            IngredientsAiWriter::FEATURE => [
                'temperature' => 0.4,
                'sections' => [
                    'remplacements' => "E330 => acide citrique\n# ignoré => jamais envoyé",
                    'role' => 'Rôle surchargé.',
                ],
            ],
        ]]);
        $this->fakeResponse(['ingredients' => ['fr' => 'sucre', 'nl' => 'suiker', 'en' => 'sugar'], 'notes' => []]);

        app(IngredientsAiWriter::class)->rewrite('sucre, E330');

        Http::assertSent(function (Request $request) {
            $system = $request['systemInstruction']['parts'][0]['text'];

            return $request['generationConfig']['temperature'] === 0.4
                && str_starts_with($system, 'Rôle surchargé.')
                && str_contains($system, '- « E330 » → « acide citrique »')
                && ! str_contains($system, 'ignoré')
                && ! str_contains($system, '{{');
        });
    }

    public function test_notes_are_null_when_the_ai_has_nothing_to_report(): void
    {
        $this->fakeResponse(['ingredients' => ['fr' => 'sucre', 'nl' => 'suiker', 'en' => 'sugar'], 'notes' => []]);

        $result = app(IngredientsAiWriter::class)->rewrite('sucre');

        $this->assertNull($result['notes']);
    }
}
