<?php

namespace Modules\Legacy\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Lunar\Models\Brand;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;
use Modules\Allergenes\Models\Allergen;
use Modules\Allergenes\Support\ProductAllergens;
use Modules\Panneaux\Models\Block;
use Modules\Panneaux\Models\Panel;
use Modules\Panneaux\Support\PanelItems;
use Modules\Panneaux\Support\PanelSettings;
use Modules\Support\Modules;
use Modules\Support\ProductCreator;
use Modules\Types\Models\CandyType;
use Modules\Types\Support\ProductCandyType;

/**
 * Importe le jeu de données de l'ancienne application dans le catalogue actuel :
 *
 *   marque       -> marque Lunar (avec son logo)
 *   type         -> type de bonbon (couleur de fond = celle du profil « Patrice », le profil réellement utilisé)
 *   bonbon       -> produit Lunar publié, SKU « BB-<ancien id> », ingrédients en français, allergènes déduits
 *   profil (Marc, Patrice…) -> un PANNEAU, avec ses réglages propres (polices, colonnes, titre…), ses bonbons
 *                  (ceux qui avaient une DLUO dans ce profil) et leur DLUO, un bloc « marque » automatique
 *                  par marque, et les blocs d'information de l'ancienne appli
 *
 *   photo        -> image(s) du produit, prises dans le dossier des images : « <SKU>-<nom>.jpg » (ex. BB-030-violette.jpg),
 *                  la première par ordre alphabétique est l'image principale
 *
 * Ce que l'ancienne base ne contenait pas n'est pas inventé : stock à 0, seuil d'alerte à 0, conditionnement laissé au fournisseur (sinon carton par défaut),
 * traductions NL/EN vides (la vitrine retombe sur le français).
 */
class LegacyImporter
{
    public const SKU_PREFIX = 'BB-';

    /** Repère posé sur les panneaux importés (pour les retrouver avec --fresh). */
    public const PANEL_NOTE = 'Importé de l\'ancienne application';

    /** Mots qui doivent rester en majuscules quand on adoucit un nom écrit tout en capitales. */
    private const KEEP_UPPER = ['XXL', 'XL', 'XS', 'XXS', '3D', 'TV', 'UFO'];

    private const DEMO_SKUS = ['HAR-CROC', 'HAR-GOLD', 'JB-BEAN', 'LON-CHOC', 'LON-NOUG'];

    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    private const COLOR_NAMES = [
        'red' => '#ff0000', 'black' => '#000000', 'white' => '#ffffff', 'grey' => '#808080', 'gray' => '#808080',
        'yellow' => '#ffff00', 'blue' => '#0000ff', 'purple' => '#800080', 'green' => '#008000', 'orange' => '#ffa500',
    ];

    public function __construct(private AllergenDetector $detector) {}

    /**
     * Lit le fichier source (JSON « data » de l'ancienne application, ou l'ancien bdd.js) et prépare le plan
     * d'import sans toucher à la base.
     *
     * @return array<string, mixed>
     */
    public function plan(string $source, string $logosDir, bool $keepCase = false, ?string $imagesDir = null): array
    {
        if (! is_file($source)) {
            throw new InvalidArgumentException("Fichier source introuvable : {$source}");
        }

        $raw = preg_replace('~^\xEF\xBB\xBF|^\s*BDD\s*=\s*~', '', (string) file_get_contents($source));
        $data = json_decode(rtrim(trim($raw), ';'), true);

        if (! is_array($data) || ! isset($data['BONBON'], $data['MARK'], $data['TYPE'])) {
            throw new InvalidArgumentException('Le fichier source n\'a pas la structure attendue (BONBON, MARK, TYPE).');
        }

        $warnings = [];
        $users = $data['USER'] ?? [];
        // Le profil qui a des bonbons donne les couleurs des types (les couleurs étaient propres à chaque profil).
        $reference = collect($users)->sortByDesc(fn ($u) => collect($u['dluo'] ?? [])->filter()->count())->first();

        $brandsById = [];
        foreach ($data['MARK'] as $mark) {
            $name = $this->properName($mark['name'], $keepCase, title: true);
            $logo = null;

            if (! empty($mark['logo'])) {
                $candidate = rtrim($logosDir, '/\\').DIRECTORY_SEPARATOR.basename($mark['logo']);
                is_file($candidate) ? $logo = $candidate : $warnings[] = "Logo introuvable pour la marque {$name} : ".basename($mark['logo']);
            }

            $brandsById[$mark['id']] = ['name' => $name, 'logo' => $logo];
        }

        $typesById = [];
        foreach ($data['TYPE'] as $type) {
            $typesById[$type['id']] = [
                'code' => Str::slug($type['name'], '_'),
                'name' => $this->properName($type['name'], $keepCase),
                'color' => $this->hex($reference['type'][$type['id']] ?? null, '#ffffff'),
            ];
        }

        $products = [];
        $skuById = [];
        foreach ($data['BONBON'] as $row) {
            $brand = $brandsById[$row['marque']] ?? null;
            $type = $typesById[$row['type']] ?? null;
            $ingredients = $this->cleanIngredients((string) ($row['ing'] ?? ''));
            $detected = $this->detector->detect($ingredients);

            $notes = $detected['notes'];
            $brand || $notes[] = 'marque inconnue dans l\'ancienne base';
            $type || $notes[] = 'type inconnu dans l\'ancienne base';
            $ingredients === '' && $notes[] = 'aucun ingrédient renseigné';

            $sku = sprintf('%s%03d', self::SKU_PREFIX, $row['id']);
            $skuById[$row['id']] = $sku;

            $products[$sku] = [
                'legacy_id' => (int) $row['id'],
                'sku' => $sku,
                'name' => $this->properName($row['nom'], $keepCase),
                'brand' => $brand['name'] ?? null,
                'type' => $type['code'] ?? null,
                'ingredients' => $ingredients,
                'contains' => $detected['contains'],
                'may_contain' => $detected['may_contain'],
                'notes' => $notes,
                'images' => $imagesDir ? $this->imagesFor($sku, $imagesDir) : [],
            ];
        }

        $panels = [];
        foreach ($users as $key => $user) {
            $presence = [];
            foreach ($user['dluo'] ?? [] as $legacyId => $dluo) {
                if ($dluo !== '' && isset($skuById[$legacyId])) {
                    $presence[$skuById[$legacyId]] = PanelItems::normalizeDluo($dluo);
                }
            }

            $panels[] = [
                'key' => $key,
                'name' => 'Panneau '.($user['name'] ?? Str::title($key)),
                'settings' => $this->settings($user, $data),
                'presence' => $presence,
                'info' => $this->infoBlocks($user, $data, array_intersect_key($products, $presence)),
            ];
        }

        return [
            'brands' => collect($brandsById)->mapWithKeys(fn ($b) => [$b['name'] => ['logo' => $b['logo']]])->all(),
            'types' => collect($typesById)->mapWithKeys(fn ($t) => [$t['code'] => ['name' => $t['name'], 'color' => $t['color']]])->all(),
            'products' => array_values($products),
            'panels' => $panels,
            'warnings' => $warnings,
        ];
    }

    /** Réglages d'un profil, dans la forme des réglages d'un panneau. @return array<string, mixed> */
    private function settings(array $user, array $data): array
    {
        $font = fn ($f, $default) => [$f[0] ?? $default[0], (string) ($f[1] ?? $default[1]), $this->hex($f[2] ?? null, $default[2])];
        $d = PanelSettings::DEFAULTS;
        $landscape = ($user['page']['orientation'] ?? '') === 'paysage';
        $large = ($user['page']['col'] ?? 'medium') === 'large';

        return PanelSettings::sanitize([
            'showDluo' => true,
            'page' => [
                'orientation' => $landscape ? 'paysage' : 'portrait',
                // Ancienne règle : portrait 3 colonnes (2 si larges), paysage 4 (3 si larges).
                'columnsPerRow' => $landscape ? ($large ? 3 : 4) : ($large ? 2 : 3),
                'dluo' => (bool) ($user['page']['dluo'] ?? false),
            ],
            'titre' => [
                'content' => (string) ($user['titre']['content'] ?? ''),
                'font' => $user['titre']['font'] ?? $d['titre']['font'],
                'size' => $user['titre']['size'] ?? $d['titre']['size'],
                'color' => $this->hex($user['titre']['color'] ?? null, $d['titre']['color']),
                'bordercolor' => $this->hex($user['titre']['bordercolor'] ?? null, $d['titre']['bordercolor']),
                'ononepage' => (bool) ($user['titre']['ononepage'] ?? true),
            ],
            'bonbon' => [
                'bordercolor' => $this->hex($user['bonbon']['bordercolor'] ?? null, $d['bonbon']['bordercolor']),
                'name' => $font($user['bonbon']['name'] ?? [], $d['bonbon']['name']),
                'ing' => $font($user['bonbon']['ing'] ?? [], $d['bonbon']['ing']),
                'dluo' => $font($user['bonbon']['dluo'] ?? [], $d['bonbon']['dluo']),
                'allergene' => $user['bonbon']['allergene'] ?? 'background',
            ],
            'info' => [
                'bordercolor' => $this->hex($user['info']['bordercolor'] ?? null, $d['info']['bordercolor']),
                'title' => $font($user['info']['title'] ?? [], $d['info']['title']),
                'content' => $font($user['info']['content'] ?? [], $d['info']['content']),
                'bgcolor' => [
                    'head' => $this->hex($user['info']['bgcolor']['head'] ?? null, $d['info']['bgcolor']['head']),
                    'body' => $this->hex($user['info']['bgcolor']['body'] ?? null, $d['info']['bgcolor']['body']),
                ],
            ],
            // Anciennes listes ALLERGENE/SANSALLERGENE du profil : plus reprises, le surlignage vient
            // désormais automatiquement du module Allergènes (mots-clés communs à tout le catalogue).
        ]);
    }

    /**
     * Blocs d'information d'un profil : marques, légende, présentation des compositions, allergènes majeurs,
     * et « Haribo : sans gélatine animale » (liste des bonbons du panneau qui figurent dans la liste de la marque).
     *
     * @param  array<string, array<string, mixed>>  $onPanel  bonbons présents sur ce panneau (par SKU)
     * @return array<int, array<string, mixed>>
     */
    private function infoBlocks(array $user, array $data, array $onPanel): array
    {
        $info = $data['INFO'] ?? [];
        $hidden = ($user['info']['position'] ?? 'before') === 'hidden';
        $zone = ($user['info']['position'] ?? 'before') === 'after' ? 'after' : 'before';
        $on = fn (string $key) => ! $hidden && (bool) ($user['info'][$key] ?? true);
        $foot = fn (array $i) => '<hr><small>'.trim(($i['update'] ?? '').' — '.($i['src'] ?? '').(isset($i['link']) ? ' ('.$i['link'].')' : ''), ' —').'</small>';

        $blocks = [
            ['code' => Block::KIND_MARK, 'title' => $info['mark']['title'] ?? null, 'active' => $on('mark'), 'zone' => $zone],
            ['code' => Block::KIND_LEGEND, 'title' => $info['legend']['title'] ?? null, 'active' => $on('legend'), 'zone' => $zone],
            ['code' => 'custom-allergen', 'title' => $info['allergen']['title'] ?? null, 'content' => $info['allergen']['content'] ?? null, 'active' => $on('allergen'), 'zone' => $zone],
            ['code' => 'custom-law', 'title' => $info['law']['title'] ?? null, 'content' => ($info['law']['content'] ?? '').$foot($info['law'] ?? []), 'active' => $on('law'), 'zone' => $zone],
        ];

        if (isset($info['tipsharibo']['list'])) {
            $names = collect($onPanel)->pluck('name')->filter(fn ($name) => collect($info['tipsharibo']['list'])
                ->contains(fn ($v) => preg_match('/'.preg_quote($v, '/').'/iu', $name) && ($v !== 'Dragibus' || mb_strlen($v) === mb_strlen($name))))
                ->map(fn ($n) => mb_strtoupper($n))->sort()->values();

            if ($names->isNotEmpty()) {
                $blocks[] = [
                    'code' => 'custom-tipsharibo',
                    'title' => $info['tipsharibo']['title'],
                    'content' => '<p><b>«</b> '.e($names->implode(', ')).' <b>»</b></p><p>'.e($info['tipsharibo']['bonus'] ?? '').'</p>'.$foot($info['tipsharibo']),
                    'active' => $on('tipsharibo'),
                    'zone' => $zone,
                ];
            }
        }

        return array_values(array_filter($blocks, fn ($b) => $b['title'] !== null));
    }

    /**
     * Écrit le plan en base. Idempotent : un SKU déjà présent est ignoré (sauf avec $fresh, qui les supprime d'abord).
     *
     * @param  array{fresh?: bool, purge_demo?: bool}  $options
     * @return array<string, int>
     */
    public function import(array $plan, array $options = [], ?callable $log = null): array
    {
        $log ??= fn (string $message) => null;
        $stats = ['created' => 0, 'skipped' => 0, 'deleted' => 0, 'brands' => 0, 'logos' => 0, 'images' => 0, 'panels' => 0, 'blocks' => 0, 'presences' => 0, 'purged_demo' => 0];

        if ($options['purge_demo'] ?? false) {
            $stats['purged_demo'] = $this->purgeDemo();
            $log("Démonstration supprimée : {$stats['purged_demo']} produit(s).");
        }

        if ($options['fresh'] ?? false) {
            $stats['deleted'] = $this->deleteImported();
            $log("Import précédent supprimé : {$stats['deleted']} produit(s) et ses panneaux.");
        }

        $types = $this->ensureTypes($plan['types']);
        $this->ensureBrands($plan['brands'], $stats);
        $allergenIds = Modules::enabled('Allergenes') ? Allergen::pluck('id', 'code')->all() : [];

        foreach ($plan['products'] as $item) {
            if ($existing = ProductVariant::where('sku', $item['sku'])->first()?->product) {
                $stats['skipped']++;
                // Bonbon déjà importé : on lui ajoute ses photos seulement s'il n'en a encore aucune.
                $stats['images'] += $this->attachImages($existing, $item['images']);

                continue;
            }

            DB::transaction(function () use ($item, $types, $allergenIds, &$stats) {
                $product = ProductCreator::create([
                    'name' => ['fr' => $item['name']],
                    'ingredients' => ['fr' => $item['ingredients']],
                    'brand' => $item['brand'],
                    'sku' => $item['sku'],
                    'min_stock_kg' => 0,
                    'status' => 'published',
                ]);

                if ($item['type'] && isset($types[$item['type']])) {
                    ProductCandyType::assign($product, $types[$item['type']]->id);
                }

                if ($allergenIds) {
                    $ids = fn (array $codes) => array_map(fn ($c) => $allergenIds[$c], array_filter($codes, fn ($c) => isset($allergenIds[$c])));
                    ProductAllergens::sync($product, $ids($item['contains']), $ids($item['may_contain']));
                }

                $stats['images'] += $this->attachImages($product, $item['images']);
                $stats['created']++;
            });
        }

        if (Modules::enabled('Panneaux')) {
            foreach ($plan['panels'] as $panelPlan) {
                $this->importPanel($panelPlan, $plan['products'], $stats);
            }
        }

        return $stats;
    }

    /** @param array<string, mixed> $panelPlan */
    private function importPanel(array $panelPlan, array $products, array &$stats): void
    {
        $panel = Panel::where('name', $panelPlan['name'])->first();

        if ($panel) {
            return;   // déjà importé : on ne touche pas aux réglages qu'on a pu modifier depuis
        }

        $panel = Panel::create(['name' => $panelPlan['name'], 'notes' => self::PANEL_NOTE, 'settings' => $panelPlan['settings']]);
        $stats['panels']++;

        // Blocs d'information : on complète / remplace ceux créés par défaut avec le contenu de l'ancienne appli.
        $panel->blocks()->where('kind', Block::KIND_CUSTOM)->delete();
        $position = 0;
        foreach ($panelPlan['info'] as $info) {
            $isCustom = str_starts_with($info['code'], 'custom-');
            $values = [
                'title' => $info['title'],
                'active' => $info['active'],
                'zone' => $info['zone'],
                'position' => ++$position,
            ];

            if ($isCustom) {
                $panel->blocks()->create([...$values, 'kind' => Block::KIND_CUSTOM, 'code' => $info['code'], 'content' => $info['content'], 'width' => 6]);
            } else {
                $panel->blocks()->where('code', $info['code'])->update($values);
            }
        }

        $bySku = collect($products)->keyBy('sku');
        $brands = [];

        foreach ($panelPlan['presence'] as $sku => $dluo) {
            $variant = ProductVariant::where('sku', $sku)->first();
            if (! $variant) {
                continue;
            }

            PanelItems::add($panel, $variant->product_id, $dluo);
            $stats['presences']++;
            $brands[$bySku[$sku]['brand'] ?? ''] = $bySku[$sku]['brand'];
        }

        // Un bloc « marque » automatique par marque présente (par ordre alphabétique), « sans marque » en dernier.
        $named = collect($brands)->filter()->sortBy(fn ($b) => Str::lower(Str::ascii($b)))->values();
        $index = 0;
        foreach ($named as $brandName) {
            $brand = Brand::where('name', $brandName)->first();
            $panel->blocks()->create([
                'kind' => Block::KIND_BRAND, 'brand_id' => $brand?->id, 'zone' => 'labels',
                'code' => $this->letterCode($index), 'name' => $brandName, 'position' => ++$index,
            ]);
            $stats['blocks']++;
        }
        if (array_key_exists('', $brands)) {
            $panel->blocks()->create([
                'kind' => Block::KIND_BRAND, 'brand_id' => null, 'zone' => 'labels',
                'code' => 'DIV', 'name' => 'Sans marque', 'position' => ++$index,
            ]);
            $stats['blocks']++;
        }

        $stats['blocks'] += $panel->blocks()->where('zone', '!=', 'labels')->count();
    }

    /** Types de bonbon : crée ceux qui manquent et applique la couleur de fond de l'ancienne appli. @return array<string, CandyType> */
    private function ensureTypes(array $types): array
    {
        $result = [];
        $position = 0;

        foreach ($types as $code => $type) {
            $position++;
            $result[$code] = CandyType::firstOrCreate(['code' => $code], ['name' => ['fr' => $type['name']], 'position' => $position]);
            $result[$code]->update(['color' => $type['color'], 'font_color' => '#000000']);
        }

        return $result;
    }

    private function ensureBrands(array $brands, array &$stats): void
    {
        foreach ($brands as $name => $info) {
            $brand = Brand::firstOrCreate(['name' => $name]);
            $brand->wasRecentlyCreated && $stats['brands']++;

            if ($info['logo'] && $brand->getMedia('images')->isEmpty()) {
                $brand->addMedia($info['logo'])
                    ->preservingOriginal()
                    ->withCustomProperties(['primary' => true])
                    ->toMediaCollection('images');
                $stats['logos']++;
            }
        }
    }

    /**
     * Images d'un bonbon dans le dossier des images : « <SKU>.<ext> » ou « <SKU>-<nom>.<ext> », par ordre alphabétique.
     *
     * @return array<int, string>
     */
    private function imagesFor(string $sku, string $imagesDir): array
    {
        $dir = rtrim($imagesDir, '/\\').DIRECTORY_SEPARATOR;

        return collect([...glob($dir.$sku.'.*') ?: [], ...glob($dir.$sku.'-*') ?: []])
            ->filter(fn (string $path) => in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::IMAGE_EXTENSIONS, true))
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Ajoute les images à un produit qui n'en a aucune ; la première devient l'image principale.
     *
     * @param  array<int, string>  $images
     */
    private function attachImages(Product $product, array $images): int
    {
        if ($images === [] || $product->getMedia('images')->isNotEmpty()) {
            return 0;
        }

        foreach ($images as $index => $path) {
            $product->addMedia($path)
                ->preservingOriginal()
                ->usingFileName(preg_replace('/^'.preg_quote(self::SKU_PREFIX, '/').'\d+-/', '', basename($path)))
                ->withCustomProperties(['primary' => $index === 0])
                ->toMediaCollection('images');
        }

        return count($images);
    }

    private function deleteImported(): int
    {
        if (Modules::enabled('Panneaux')) {
            Panel::where('notes', self::PANEL_NOTE)->orWhere('name', 'Panneau vrac')->get()->each->delete();
        }

        return $this->deleteBySku(fn ($query) => $query->where('sku', 'like', self::SKU_PREFIX.'%'));
    }

    private function purgeDemo(): int
    {
        $count = $this->deleteBySku(fn ($query) => $query->whereIn('sku', self::DEMO_SKUS));

        if (Modules::enabled('Panneaux')) {
            Panel::where('name', 'Panneau vitrine')->get()->each->delete();
        }

        // Marques de la démo qui n'ont plus aucun produit (Haribo est réutilisée par l'import).
        Brand::whereIn('name', ['Jelly Belly', 'Lonka'])->whereDoesntHave('products')->delete();

        return $count;
    }

    private function deleteBySku(callable $scope): int
    {
        $products = Product::withTrashed()->whereHas('variants', fn ($q) => $scope($q))->get();
        $products->each->forceDelete();

        return $products->count();
    }

    /** A, B, … Z, AA, AB… */
    private function letterCode(int $index): string
    {
        $code = '';
        do {
            $code = chr(65 + $index % 26).$code;
            $index = intdiv($index, 26) - 1;
        } while ($index >= 0);

        return $code;
    }

    /** « red », « #ddd », « #DDDDDD » → « #rrggbb » (ou la valeur par défaut). */
    private function hex(mixed $value, string $default): string
    {
        $value = strtolower(trim((string) $value));

        if (isset(self::COLOR_NAMES[$value])) {
            return self::COLOR_NAMES[$value];
        }
        if (preg_match('/^#([0-9a-f])([0-9a-f])([0-9a-f])$/', $value, $m)) {
            return "#{$m[1]}{$m[1]}{$m[2]}{$m[2]}{$m[3]}{$m[3]}";
        }

        return preg_match('/^#[0-9a-f]{6}$/', $value) ? $value : $default;
    }

    /** Adoucit un nom écrit ENTIÈREMENT en capitales ; laisse intact un nom déjà en casse mixte. */
    public function properName(string $name, bool $keepCase = false, bool $title = false): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name));

        if ($keepCase || $name !== mb_strtoupper($name)) {
            return $name;
        }

        if ($title) {
            return mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');
        }

        $words = array_map(
            fn ($w) => in_array(mb_strtoupper($w), self::KEEP_UPPER, true) ? mb_strtoupper($w) : mb_strtolower($w),
            explode(' ', $name)
        );

        return $this->upperFirst(implode(' ', $words));
    }

    /** Retire les « Ingrédients : » (parfois répétés), les retours à la ligne et la ponctuation finale parasite. */
    public function cleanIngredients(string $text): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text));
        $text = preg_replace('/^(?:ingr[ée]dients?\s*:?\s*)+/iu', '', $text);

        return $this->upperFirst(trim($text, " \t.,;"));
    }

    private function upperFirst(string $text): string
    {
        return mb_strtoupper(mb_substr($text, 0, 1)).mb_substr($text, 1);
    }
}
