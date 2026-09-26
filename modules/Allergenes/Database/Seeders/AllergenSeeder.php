<?php

namespace Modules\Allergenes\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Allergenes\Models\Allergen;

/** Les 14 allergènes à déclaration obligatoire (règlement UE 1169/2011). */
class AllergenSeeder extends Seeder
{
    public function run(): void
    {
        $allergens = [
            'gluten' => ['fr' => 'Gluten', 'nl' => 'Gluten', 'en' => 'Gluten'],
            'crustaceans' => ['fr' => 'Crustacés', 'nl' => 'Schaaldieren', 'en' => 'Crustaceans'],
            'eggs' => ['fr' => 'Œufs', 'nl' => 'Eieren', 'en' => 'Eggs'],
            'fish' => ['fr' => 'Poisson', 'nl' => 'Vis', 'en' => 'Fish'],
            'peanuts' => ['fr' => 'Arachides', 'nl' => 'Pinda\'s', 'en' => 'Peanuts'],
            'soy' => ['fr' => 'Soja', 'nl' => 'Soja', 'en' => 'Soy'],
            'milk' => ['fr' => 'Lait', 'nl' => 'Melk', 'en' => 'Milk'],
            'tree_nuts' => ['fr' => 'Fruits à coque', 'nl' => 'Noten', 'en' => 'Tree nuts'],
            'celery' => ['fr' => 'Céleri', 'nl' => 'Selderij', 'en' => 'Celery'],
            'mustard' => ['fr' => 'Moutarde', 'nl' => 'Mosterd', 'en' => 'Mustard'],
            'sesame' => ['fr' => 'Sésame', 'nl' => 'Sesam', 'en' => 'Sesame'],
            'sulphites' => ['fr' => 'Sulfites', 'nl' => 'Sulfieten', 'en' => 'Sulphites'],
            'lupin' => ['fr' => 'Lupin', 'nl' => 'Lupine', 'en' => 'Lupin'],
            'molluscs' => ['fr' => 'Mollusques', 'nl' => 'Weekdieren', 'en' => 'Molluscs'],
        ];

        $position = 0;
        foreach ($allergens as $code => $names) {
            Allergen::updateOrCreate(['code' => $code], ['name' => $names, 'position' => ++$position]);
        }
    }
}
