<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Lunar\Admin\Models\Staff;
use Lunar\FieldTypes\Number;
use Lunar\FieldTypes\Text;
use Lunar\FieldTypes\TranslatedText;
use Lunar\Models\Attribute;
use Lunar\Models\AttributeGroup;
use Lunar\Models\Channel;
use Lunar\Models\Collection;
use Lunar\Models\CollectionGroup;
use Lunar\Models\Country;
use Lunar\Models\Currency;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Language;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Lunar\Models\TaxClass;
use Lunar\Models\TaxRate;
use Lunar\Models\TaxRateAmount;
use Lunar\Models\TaxZone;

/**
 * Socle Lunar du projet : équivalent non interactif de `lunar:install`, adapté à la Belgique
 * (euro, FR/NL/EN, TVA 6 % sur les confiseries) avec le type de produit « Bonbon ».
 * Idempotent : peut être relancé sans dupliquer.
 */
class BonbonBaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('bonbon.locales') as $code => $name) {
            Language::firstOrCreate(['code' => $code], ['name' => $name, 'default' => $code === array_key_first(config('bonbon.locales'))]);
        }

        Currency::firstOrCreate(['code' => 'EUR'], [
            'name' => 'Euro', 'exchange_rate' => 1, 'decimal_places' => 2, 'default' => true, 'enabled' => true,
        ]);

        Channel::firstOrCreate(['handle' => 'webstore'], [
            'name' => 'Vitrine', 'default' => true, 'url' => config('app.url'),
        ]);

        CustomerGroup::firstOrCreate(['handle' => 'retail'], ['name' => 'Particuliers', 'default' => true]);
        CollectionGroup::firstOrCreate(['handle' => 'main'], ['name' => 'Principal']);

        $this->seedTaxes();
        $this->seedAttributes();

        if (! Staff::whereAdmin(true)->exists()) {
            Artisan::call('lunar:create-admin', [
                '--firstname' => 'Admin',
                '--lastname' => 'Bonbons',
                '--email' => 'admin@bonbon.test',
                '--password' => 'password',
            ]);
        }
    }

    private function seedTaxes(): void
    {
        $tax = TaxClass::firstOrCreate(['name' => 'Confiserie (6 %)'], ['default' => true]);

        $belgium = Country::firstOrCreate(['iso3' => 'BEL'], [
            'name' => 'Belgium', 'iso2' => 'BE', 'phonecode' => '32', 'capital' => 'Brussels',
            'currency' => 'EUR', 'native' => 'België', 'emoji' => '🇧🇪', 'emoji_u' => 'U+1F1E7 U+1F1EA',
        ]);

        $zone = TaxZone::firstOrCreate(['name' => 'Belgique'], [
            'zone_type' => 'country', 'price_display' => 'tax_inclusive', 'default' => true, 'active' => true,
        ]);
        $zone->countries()->firstOrCreate(['country_id' => $belgium->id]);

        $rate = TaxRate::firstOrCreate(['tax_zone_id' => $zone->id, 'name' => 'TVA 6 %'], ['priority' => 1]);
        TaxRateAmount::firstOrCreate(['tax_rate_id' => $rate->id, 'tax_class_id' => $tax->id], ['percentage' => 6]);
    }

    private function seedAttributes(): void
    {
        $group = AttributeGroup::firstOrCreate(['handle' => 'details'], [
            'attributable_type' => Product::morphName(),
            'name' => ['fr' => 'Détails', 'nl' => 'Details', 'en' => 'Details'],
            'position' => 1,
        ]);

        $collectionGroup = AttributeGroup::firstOrCreate(['handle' => 'collection_details'], [
            'attributable_type' => Collection::morphName(),
            'name' => ['fr' => 'Détails', 'nl' => 'Details', 'en' => 'Details'],
            'position' => 1,
        ]);

        $attributes = [
            // [type d'entité, groupe, handle, libellés, classe, config, requis, système]
            ['product', $group, 'name', ['fr' => 'Nom', 'nl' => 'Naam', 'en' => 'Name'], TranslatedText::class, ['richtext' => false], true, true],
            ['product', $group, 'description', ['fr' => 'Description', 'nl' => 'Beschrijving', 'en' => 'Description'], TranslatedText::class, ['richtext' => false], false, false],
            ['product', $group, 'ingredients', ['fr' => 'Ingrédients', 'nl' => 'Ingrediënten', 'en' => 'Ingredients'], TranslatedText::class, ['richtext' => false], false, false],
            // Liste brute passée à l'IA (OCR de la photo ou texte corrigé à la main), gardée telle quelle au cas où.
            // Non rattachée au type de produit plus bas : c'est la section IA de la fiche qui l'affiche.
            ['product', $group, 'ingredients_scan', ['fr' => 'Ingrédients bruts (scan)', 'nl' => 'Ruwe ingrediënten (scan)', 'en' => 'Raw ingredients (scan)'], Text::class, ['richtext' => false], false, false],
            // Mentions d'allergènes lues sur l'emballage (traces, mots en gras…), pour justifier les allergènes cochés.
            ['product', $group, 'allergens_scan', ['fr' => 'Mentions allergènes (scan)', 'nl' => 'Allergenenvermeldingen (scan)', 'en' => 'Allergen statements (scan)'], Text::class, ['richtext' => false], false, false],
            ['product', $group, 'bags_per_carton', ['fr' => 'Sacs par carton', 'nl' => 'Zakken per doos', 'en' => 'Bags per carton'], Number::class, ['min' => 1], false, false],
            ['product', $group, 'min_stock_bags', ['fr' => 'Seuil d\'alerte (sacs)', 'nl' => 'Alarmdrempel (zakken)', 'en' => 'Alert threshold (bags)'], Number::class, ['min' => 0], false, false],
            ['collection', $collectionGroup, 'name', ['fr' => 'Nom', 'nl' => 'Naam', 'en' => 'Name'], TranslatedText::class, ['richtext' => false], true, true],
            ['collection', $collectionGroup, 'description', ['fr' => 'Description', 'nl' => 'Beschrijving', 'en' => 'Description'], TranslatedText::class, ['richtext' => true], false, false],
        ];

        foreach ($attributes as $position => [$entity, $attributeGroup, $handle, $names, $type, $config, $required, $system]) {
            Attribute::firstOrCreate(
                ['attribute_type' => $entity, 'handle' => $handle],
                [
                    'attribute_group_id' => $attributeGroup->id,
                    'position' => $position + 1,
                    'name' => $names,
                    'description' => ['fr' => '', 'nl' => '', 'en' => ''],
                    'section' => 'main',
                    'type' => $type,
                    'required' => $required,
                    'default_value' => null,
                    'configuration' => $config,
                    'system' => $system,
                    'searchable' => in_array($handle, ['name', 'description', 'ingredients'], true),
                    'filterable' => false,
                ]
            );
        }

        // Les attributs « scan » restent hors du type : ils sont affichés par la section IA, pas par Lunar.
        $scanAttributeIds = Attribute::whereAttributeType(Product::morphName())
            ->whereIn('handle', ['ingredients_scan', 'allergens_scan'])
            ->pluck('id');

        $type = ProductType::firstOrCreate(['name' => 'Bonbon']);
        $type->mappedAttributes()->syncWithoutDetaching(
            Attribute::whereAttributeType(Product::morphName())->whereKeyNot($scanAttributeIds)->pluck('id')
        );
        $type->mappedAttributes()->detach($scanAttributeIds);
    }
}
