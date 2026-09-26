<?php

namespace Modules\Support;

use Illuminate\Support\Facades\DB;
use Lunar\FieldTypes\Number;
use Lunar\FieldTypes\Text;
use Lunar\FieldTypes\TranslatedText;
use Lunar\Models\Brand;
use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Price;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Lunar\Models\TaxClass;

/**
 * Crée un « bonbon » complet dans Lunar : produit (attributs traduits), variant unique (le sac),
 * prix, disponibilité et photo. Utilisé par la création assistée par IA et par les données de démo.
 */
class ProductCreator
{
    /**
     * @param array{
     *     name: array<string,string>,
     *     description?: array<string,string>,
     *     ingredients?: array<string,string>,
     *     brand?: ?string,
     *     sku?: ?string,
     *     ean?: ?string,
     *     bag_weight_kg?: int|float|null,
     *     bags_per_carton?: int,
     *     min_stock_bags?: int,
     *     price_per_bag?: ?float,
     *     status?: 'published'|'draft',
     *     image_path?: ?string,
     * } $data
     */
    public static function create(array $data): Product
    {
        return DB::transaction(function () use ($data) {
            $attributes = collect([
                'name' => self::translated($data['name']),
                'bags_per_carton' => new Number(max(1, (int) ($data['bags_per_carton'] ?? 1))),
                'min_stock_bags' => new Number(max(0, (int) ($data['min_stock_bags'] ?? 0))),
            ]);

            foreach (['description', 'ingredients'] as $handle) {
                if (! empty(array_filter($data[$handle] ?? []))) {
                    $attributes->put($handle, self::translated($data[$handle]));
                }
            }

            $product = Product::create([
                'product_type_id' => ProductType::firstOrFail()->id,
                'status' => $data['status'] ?? 'published',
                'brand_id' => filled($data['brand'] ?? null) ? Brand::firstOrCreate(['name' => trim($data['brand'])])->id : null,
                'attribute_data' => $attributes,
            ]);

            $variant = $product->variants()->create([
                'tax_class_id' => TaxClass::getDefault()->id,
                'sku' => filled($data['sku'] ?? null) ? $data['sku'] : null,
                'ean' => filled($data['ean'] ?? null) ? $data['ean'] : null,
                'stock' => 0,
                'purchasable' => 'always',
                'shippable' => true,
                'weight_value' => $data['bag_weight_kg'] ?? 0,
                'weight_unit' => 'kg',
            ]);

            if (($data['price_per_bag'] ?? null) !== null) {
                Price::create([
                    'priceable_type' => $variant->getMorphClass(),
                    'priceable_id' => $variant->id,
                    'currency_id' => Currency::getDefault()->id,
                    'price' => (int) round($data['price_per_bag'] * 100),
                    'min_quantity' => 1,
                ]);
            }

            // Disponible sur la vitrine par défaut, pour les particuliers.
            if ($channel = Channel::getDefault()) {
                $product->channels()->sync([$channel->id => ['enabled' => true, 'starts_at' => now()]]);
            }
            if ($group = CustomerGroup::getDefault()) {
                $product->customerGroups()->sync([$group->id => [
                    'enabled' => true, 'visible' => true, 'purchasable' => true, 'starts_at' => now(),
                ]]);
            }

            if (filled($data['image_path'] ?? null)) {
                $product->addMedia($data['image_path'])
                    ->preservingOriginal()
                    ->withCustomProperties(['primary' => true])
                    ->toMediaCollection('images');
            }

            return $product->fresh(['variants']);
        });
    }

    /** @param array<string,string> $values */
    private static function translated(array $values): TranslatedText
    {
        return new TranslatedText(collect($values)->filter(fn ($v) => filled($v))->map(fn ($v) => new Text($v)));
    }
}
