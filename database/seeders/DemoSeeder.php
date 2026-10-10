<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Lunar\Models\Brand;
use Lunar\Models\ProductVariant;
use Modules\Allergenes\Models\Allergen;
use Modules\Allergenes\Support\ProductAllergens;
use Modules\Panneaux\Models\Block;
use Modules\Panneaux\Models\Panel;
use Modules\Panneaux\Support\PanelItems;
use Modules\Stock\Enums\StockReason;
use Modules\Stock\Services\StockService;
use Modules\Support\ProductCreator;

/** Données de démonstration : `php artisan db:seed --class=DemoSeeder` (idempotent sur le SKU). */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $panel = Panel::firstOrCreate(['name' => 'Panneau vitrine'], ['location' => 'Entrée du magasin']);
        $allergens = Allergen::pluck('id', 'code');

        $products = [
            [
                'sku' => 'HAR-CROC', 'brand' => 'Haribo', 'color' => [239, 68, 68],
                'name' => ['fr' => 'Croco', 'nl' => 'Krokodil', 'en' => 'Croco'],
                'description' => ['fr' => 'Crocodiles moelleux au goût fruité.', 'nl' => 'Zachte krokodillen met fruitsmaak.', 'en' => 'Soft, fruity crocodile gummies.'],
                'allergens' => [], 'stock' => 18, 'price' => 8.3,
            ],
            [
                'sku' => 'HAR-GOLD', 'brand' => 'Haribo', 'color' => [245, 158, 11],
                'name' => ['fr' => 'Ours d\'or', 'nl' => 'Goudbeertjes', 'en' => 'Goldbears'],
                'description' => ['fr' => 'Les célèbres oursons en gélatine aux fruits.', 'nl' => 'De beroemde fruitgom beertjes.', 'en' => 'The famous fruit gummy bears.'],
                'allergens' => [['soy', 'may_contain']], 'stock' => 3, 'price' => 8.85,
            ],
            [
                'sku' => 'JB-BEAN', 'brand' => 'Jelly Belly', 'color' => [168, 85, 247],
                'name' => ['fr' => 'Jelly Beans assortis', 'nl' => 'Jelly Beans mix', 'en' => 'Assorted Jelly Beans'],
                'description' => ['fr' => 'Haricots sucrés aux 20 saveurs.', 'nl' => 'Zoete bonen met 20 smaken.', 'en' => 'Sweet beans in 20 flavours.'],
                'allergens' => [], 'stock' => 25, 'price' => 12.9,
            ],
            [
                'sku' => 'LON-CHOC', 'brand' => 'Lonka', 'color' => [120, 72, 40],
                'name' => ['fr' => 'Caramels au chocolat', 'nl' => 'Chocoladekaramels', 'en' => 'Chocolate caramels'],
                'description' => ['fr' => 'Caramels mous enrobés de chocolat au lait.', 'nl' => 'Zachte karamels met melkchocolade.', 'en' => 'Soft caramels coated in milk chocolate.'],
                'allergens' => [['milk', 'contains'], ['soy', 'contains'], ['tree_nuts', 'may_contain']], 'stock' => 16, 'price' => 9.25,
            ],
            [
                'sku' => 'LON-NOUG', 'brand' => 'Lonka', 'color' => [234, 179, 8],
                'name' => ['fr' => 'Nougat tendre', 'nl' => 'Zachte nougat', 'en' => 'Soft nougat'],
                'description' => ['fr' => 'Nougat aux amandes.', 'nl' => 'Nougat met amandelen.', 'en' => 'Almond nougat.'],
                'allergens' => [['tree_nuts', 'contains'], ['eggs', 'contains'], ['milk', 'may_contain']], 'stock' => 0, 'price' => 14.0,
            ],
        ];

        foreach ($products as $row) {
            if (ProductVariant::where('sku', $row['sku'])->exists()) {
                continue;
            }

            $product = ProductCreator::create([
                'name' => $row['name'],
                'description' => $row['description'],
                'brand' => $row['brand'],
                'sku' => $row['sku'],
                'min_stock_kg' => 5,
                'price_per_kg' => $row['price'],
                'image_path' => $this->placeholder($row['sku'], $row['name']['fr'], $row['color']),
            ]);

            PanelItems::add($panel, $product);

            ProductAllergens::sync(
                $product,
                collect($row['allergens'])->where(1, 'contains')->map(fn ($a) => $allergens[$a[0]])->all(),
                collect($row['allergens'])->where(1, 'may_contain')->map(fn ($a) => $allergens[$a[0]])->all(),
            );

            if ($row['stock'] > 0) {
                app(StockService::class)->move($product->variants->first(), $row['stock'], StockReason::Receipt, note: 'Stock initial (démo)');
            }
        }

        // Un bloc marque automatique par marque de la démo.
        foreach (['Haribo', 'Jelly Belly', 'Lonka'] as $index => $name) {
            $panel->blocks()->firstOrCreate(
                ['kind' => Block::KIND_BRAND, 'brand_id' => Brand::where('name', $name)->value('id')],
                ['zone' => 'labels', 'code' => chr(65 + $index), 'name' => $name, 'position' => $index + 1],
            );
        }
    }

    /** Image de démonstration : pastille de couleur avec l'initiale, générée avec GD. */
    private function placeholder(string $sku, string $label, array $rgb): string
    {
        $dir = storage_path('app/demo');
        File::ensureDirectoryExists($dir);
        $path = "{$dir}/{$sku}.jpg";

        $img = imagecreatetruecolor(600, 600);
        imagefill($img, 0, 0, imagecolorallocate($img, 248, 244, 238));
        imagefilledellipse($img, 300, 300, 460, 460, imagecolorallocate($img, ...$rgb));
        imagefilledellipse($img, 230, 220, 150, 110, imagecolorallocatealpha($img, 255, 255, 255, 90));
        imagestring($img, 5, 300 - (int) (imagefontwidth(5) * mb_strlen($label) / 2), 560, $label, imagecolorallocate($img, 80, 80, 80));
        imagejpeg($img, $path, 88);

        return $path;
    }
}
