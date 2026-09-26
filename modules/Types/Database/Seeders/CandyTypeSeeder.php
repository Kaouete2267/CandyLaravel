<?php

namespace Modules\Types\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Types\Models\CandyType;

/** Types de départ, ceux de l'ancienne application (couleurs et traductions modifiables dans l'admin). */
class CandyTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['code' => 'acidule', 'color' => '#eab308', 'font_color' => '#000000', 'name' => ['fr' => 'Acidulé', 'nl' => 'Zuur', 'en' => 'Sour']],
            ['code' => 'chocolat', 'color' => '#803629', 'font_color' => '#ffffff', 'name' => ['fr' => 'Chocolat', 'nl' => 'Chocolade', 'en' => 'Chocolate']],
            ['code' => 'lisse', 'color' => '#a855f7', 'font_color' => '#ffffff', 'name' => ['fr' => 'Lisse', 'nl' => 'Glad', 'en' => 'Smooth']],
            ['code' => 'sans_sucre', 'color' => '#22c55e', 'font_color' => '#000000', 'name' => ['fr' => 'Sans sucre', 'nl' => 'Suikervrij', 'en' => 'Sugar-free']],
            ['code' => 'sucre', 'color' => '#ec4899', 'font_color' => '#ffffff', 'name' => ['fr' => 'Sucré', 'nl' => 'Gesuikerd', 'en' => 'Sugar-coated']],
        ];

        foreach ($types as $position => $type) {
            CandyType::updateOrCreate(['code' => $type['code']], [...$type, 'position' => $position + 1]);
        }
    }
}
