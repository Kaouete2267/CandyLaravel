<?php

namespace Modules\Ia\Services;

use Illuminate\Support\Str;
use Lunar\Models\Product;

/**
 * Recherche par photo : Gemini regarde la photo et désigne, parmi le catalogue publié,
 * les produits qui correspondent (le catalogue lui est envoyé sous forme de liste compacte).
 */
class PhotoMatcher
{
    private const MIN_CONFIDENCE = 0.35;

    public function __construct(private GeminiClient $gemini) {}

    /**
     * @param  array{mime: string, data: string}  $image
     * @return array{description: string, matches: array<int, float>} matches : id produit => confiance (0-1), meilleur d'abord
     */
    public function match(array $image): array
    {
        $products = Product::where('status', 'published')->with('brand')->get();

        if ($products->isEmpty()) {
            return ['description' => '', 'matches' => []];
        }

        $default = array_key_first(config('bonbon.locales'));

        $catalogue = $products->map(fn (Product $p) => sprintf(
            '%d | %s | %s | %s',
            $p->id,
            $p->brand?->name ?? '-',
            $p->translateAttribute('name', $default),
            Str::limit((string) $p->translateAttribute('description', $default), 90),
        ))->implode("\n");

        $prompt = <<<PROMPT
Un client photographie un bonbon, un sachet ou un emballage. Identifie ce produit parmi le catalogue ci-dessous
(format : id | marque | nom | description courte).

Règles :
- Décris d'abord en une phrase ce que tu vois (`description`, en français).
- Renvoie dans `matches` les produits du catalogue qui correspondent (3 maximum), du plus probable au moins probable,
  avec une confiance entre 0 et 1. Base-toi sur la marque, le nom imprimé, la forme, les couleurs et la texture.
- Si rien ne correspond vraiment, renvoie une liste vide : n'invente pas d'id, n'utilise que des ids du catalogue.

Catalogue :
{$catalogue}
PROMPT;

        $schema = [
            'type' => 'OBJECT',
            'properties' => [
                'description' => ['type' => 'STRING'],
                'matches' => [
                    'type' => 'ARRAY',
                    'items' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'id' => ['type' => 'INTEGER'],
                            'confidence' => ['type' => 'NUMBER'],
                        ],
                        'required' => ['id', 'confidence'],
                    ],
                ],
            ],
            'required' => ['description', 'matches'],
        ];

        $result = $this->gemini->generateJson($prompt, $schema, [$image], temperature: 0.1);

        $validIds = $products->pluck('id')->all();

        $matches = collect($result['matches'] ?? [])
            ->filter(fn ($m) => in_array($m['id'] ?? null, $validIds, true) && ($m['confidence'] ?? 0) >= self::MIN_CONFIDENCE)
            ->sortByDesc('confidence')
            ->mapWithKeys(fn ($m) => [$m['id'] => round((float) $m['confidence'], 2)])
            ->all();

        return ['description' => (string) ($result['description'] ?? ''), 'matches' => $matches];
    }
}
