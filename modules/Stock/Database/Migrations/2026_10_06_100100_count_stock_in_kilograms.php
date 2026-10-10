<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Le stock se compte désormais en kg (1 sac = 1 kg : les quantités existantes sont reprises telles quelles).
 * Le sac disparaît : saisies en kg ou en cartons, contenu du carton donné par le fournisseur (sinon le réglage
 * par défaut). L'attribut « Sacs par carton » est supprimé, « Seuil d'alerte (sacs) » devient « Seuil d'alerte (kg) ».
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('stock_movements')->where('input_unit', 'bag')->update(['input_unit' => 'kg']);

        $this->renameProductAttribute('min_stock_bags', 'min_stock_kg', ['fr' => 'Seuil d\'alerte (kg)', 'nl' => 'Alarmdrempel (kg)', 'en' => 'Alert threshold (kg)']);
        $this->dropProductAttribute('bags_per_carton');

        // Texte du parcours d'aide déjà enregistré (voir OnboardingSeeder).
        if (Schema::hasTable('onboarding_steps')) {
            DB::table('onboarding_steps')->where('description', 'like', '%par sac ou par carton%')->get(['id', 'description'])
                ->each(fn (object $step) => DB::table('onboarding_steps')->where('id', $step->id)
                    ->update(['description' => str_replace('par sac ou par carton', 'en kg ou en cartons', $step->description)]));
        }
    }

    public function down(): void
    {
        DB::table('stock_movements')->where('input_unit', 'kg')->update(['input_unit' => 'bag']);

        $this->renameProductAttribute('min_stock_kg', 'min_stock_bags', ['fr' => 'Seuil d\'alerte (sacs)', 'nl' => 'Alarmdrempel (zakken)', 'en' => 'Alert threshold (bags)']);
    }

    /** @param  array<string, string>  $names */
    private function renameProductAttribute(string $from, string $to, array $names): void
    {
        DB::table('lunar_attributes')
            ->where('attribute_type', 'product')->where('handle', $from)
            ->update(['handle' => $to, 'name' => json_encode($names)]);

        $this->rewriteProductData(function (array $data) use ($from, $to) {
            if (array_key_exists($from, $data)) {
                $data[$to] = $data[$from];
                unset($data[$from]);
            }

            return $data;
        });
    }

    private function dropProductAttribute(string $handle): void
    {
        $ids = DB::table('lunar_attributes')->where('attribute_type', 'product')->where('handle', $handle)->pluck('id');

        DB::table('lunar_attributables')->whereIn('attribute_id', $ids)->delete();
        DB::table('lunar_attributes')->whereIn('id', $ids)->delete();

        $this->rewriteProductData(function (array $data) use ($handle) {
            unset($data[$handle]);

            return $data;
        });
    }

    private function rewriteProductData(callable $change): void
    {
        DB::table('lunar_products')->orderBy('id')->each(function (object $product) use ($change) {
            $data = json_decode((string) $product->attribute_data, true) ?: [];
            $changed = $change($data);

            if ($changed !== $data) {
                DB::table('lunar_products')->where('id', $product->id)->update(['attribute_data' => json_encode($changed)]);
            }
        });
    }
};
