<?php

namespace Modules\Ia\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Ia\Models\IngredientTerm;
use Modules\Ia\Services\IngredientGlossary;
use Modules\Ia\Support\IngredientCategory;

/**
 * Amorce le glossaire d'ingrédients (voir {@see IngredientGlossary}) avec les ingrédients de confiserie les
 * plus courants, pour que les tout premiers scans bénéficient déjà d'un classement cohérent. Liste
 * volontairement non exhaustive et modeste : le glossaire se complète tout seul au fil des scans réels — le
 * menu « Glossaire d'ingrédients » sert à relire/corriger ce que l'IA y ajoute automatiquement.
 * Idempotent : ne recrée jamais une entrée déjà présente (même sous un autre alias), pour ne pas écraser une
 * correction que l'équipe aurait déjà faite.
 */
class IngredientGlossarySeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->terms() as [$category, $aliases, $names]) {
            $this->upsert($category, $aliases, $names);
        }
    }

    /** @return array<int, array{0: ?string, 1: array<int,string>, 2: array<string,string>}> */
    private function terms(): array
    {
        $acides = IngredientCategory::Acides->value;
        $extraits = IngredientCategory::Extraits->value;
        $colorants = IngredientCategory::Colorants->value;
        $sirops = IngredientCategory::Sirops->value;
        $amidons = IngredientCategory::Amidons->value;
        $gommes = IngredientCategory::Gommes->value;
        $cires = IngredientCategory::Cires->value;
        $huilesGraisses = IngredientCategory::HuilesGraisses->value;
        $concentres = IngredientCategory::Concentres->value;

        return [
            // Acides
            [$acides, ['citrique', 'acide citrique'], ['fr' => 'citrique', 'nl' => 'citroenzuur', 'en' => 'citric']],
            [$acides, ['malique', 'acide malique'], ['fr' => 'malique', 'nl' => 'appelzuur', 'en' => 'malic']],
            [$acides, ['ascorbique', 'acide ascorbique', 'vitamine c'], ['fr' => 'ascorbique', 'nl' => 'ascorbinezuur', 'en' => 'ascorbic']],
            [$acides, ['tartrique', 'acide tartrique'], ['fr' => 'tartrique', 'nl' => 'wijnsteenzuur', 'en' => 'tartaric']],
            [$acides, ['lactique', 'acide lactique'], ['fr' => 'lactique', 'nl' => 'melkzuur', 'en' => 'lactic']],

            // Extraits
            [$extraits, ['riche en tocophérol', 'extrait riche en tocophérol', 'extraits riches en tocophérol', 'tocophérol'], ['fr' => 'riche en tocophérol', 'nl' => 'rijk aan tocoferol', 'en' => 'rich in tocopherol']],
            [$extraits, ['de réglisse', 'extrait de réglisse', 'réglisse'], ['fr' => 'de réglisse', 'nl' => 'van zoethout', 'en' => 'liquorice extract']],

            // Colorants (le code E est identique dans les trois langues)
            [$colorants, ['e100'], ['fr' => 'E100', 'nl' => 'E100', 'en' => 'E100']],
            [$colorants, ['e120'], ['fr' => 'E120', 'nl' => 'E120', 'en' => 'E120']],
            [$colorants, ['e122'], ['fr' => 'E122', 'nl' => 'E122', 'en' => 'E122']],
            [$colorants, ['e129'], ['fr' => 'E129', 'nl' => 'E129', 'en' => 'E129']],
            [$colorants, ['e131'], ['fr' => 'E131', 'nl' => 'E131', 'en' => 'E131']],
            [$colorants, ['e133'], ['fr' => 'E133', 'nl' => 'E133', 'en' => 'E133']],
            [$colorants, ['e160a'], ['fr' => 'E160a', 'nl' => 'E160a', 'en' => 'E160a']],
            [$colorants, ['e162'], ['fr' => 'E162', 'nl' => 'E162', 'en' => 'E162']],
            [$colorants, ['e163'], ['fr' => 'E163', 'nl' => 'E163', 'en' => 'E163']],

            // Sirops (le néerlandais fusionne naturellement "stroop" dans le mot : on l'omet côté texte final
            // pour ne pas le répéter avec le libellé de catégorie "siropen"/"siroop").
            [$sirops, ['de glucose', 'sirop de glucose'], ['fr' => 'de glucose', 'nl' => 'glucose', 'en' => 'glucose']],
            [$sirops, ['de glucose-fructose', 'sirop de glucose-fructose'], ['fr' => 'de glucose-fructose', 'nl' => 'glucose-fructose', 'en' => 'glucose-fructose']],

            // Amidons (idem : sans "zetmeel", déjà porté par le libellé de catégorie).
            [$amidons, ['de maïs', 'amidon de maïs', 'fécule de maïs'], ['fr' => 'de maïs', 'nl' => 'maïs', 'en' => 'corn']],
            [$amidons, ['de blé', 'amidon de blé'], ['fr' => 'de blé', 'nl' => 'tarwe', 'en' => 'wheat']],

            // Gommes (idem : sans "gom"/"gum").
            [$gommes, ['arabique', 'gomme arabique'], ['fr' => 'arabique', 'nl' => 'arabische', 'en' => 'arabic']],
            [$gommes, ['xanthane', 'gomme xanthane'], ['fr' => 'xanthane', 'nl' => 'xanthaan', 'en' => 'xanthan']],

            // Cires (idem : sans "was"/"wax").
            [$cires, ['de carnauba', 'cire de carnauba'], ['fr' => 'de carnauba', 'nl' => 'carnauba', 'en' => 'carnauba']],
            [$cires, ['d\'abeille', 'cire d\'abeille'], ['fr' => 'd\'abeille', 'nl' => 'bijen', 'en' => 'bee']],

            // Huiles/graisses (idem : sans "olie"/"vet"/"oil"/"fat").
            [$huilesGraisses, ['de palme', 'huile de palme', 'graisse de palme'], ['fr' => 'de palme', 'nl' => 'palm', 'en' => 'palm']],
            [$huilesGraisses, ['de noix de coco', 'huile de noix de coco', 'huile de coco'], ['fr' => 'de noix de coco', 'nl' => 'kokos', 'en' => 'coconut']],

            // Concentrés (sans "concentraat"/"concentrate", déjà porté par le libellé de catégorie).
            [$concentres, ['de betterave', 'concentré de betterave'], ['fr' => 'de betterave', 'nl' => 'biet', 'en' => 'beetroot']],
            [$concentres, ['de carotte', 'concentré de carotte'], ['fr' => 'de carotte', 'nl' => 'wortel', 'en' => 'carrot']],

            // Ingrédients isolés (aucune catégorie)
            [null, ['sucre'], ['fr' => 'sucre', 'nl' => 'suiker', 'en' => 'sugar']],
            [null, ['dextrose'], ['fr' => 'dextrose', 'nl' => 'dextrose', 'en' => 'dextrose']],
            [null, ['farine de blé'], ['fr' => 'farine de blé', 'nl' => 'tarwebloem', 'en' => 'wheat flour']],
            [null, ['gélatine'], ['fr' => 'gélatine', 'nl' => 'gelatine', 'en' => 'gelatin']],
            [null, ['sel'], ['fr' => 'sel', 'nl' => 'zout', 'en' => 'salt']],
        ];
    }

    /** @param  array<int,string>  $aliases  @param  array<string,string>  $names */
    private function upsert(?string $category, array $aliases, array $names): void
    {
        $normalized = array_values(array_unique(array_map(
            fn (string $alias) => IngredientGlossary::normalize($alias),
            $aliases
        )));

        $alreadyKnown = IngredientTerm::query()->get()
            ->contains(fn (IngredientTerm $term) => array_intersect($normalized, $term->aliases ?? []) !== []);

        if ($alreadyKnown) {
            return;
        }

        IngredientTerm::create([
            'category' => $category,
            'aliases' => $normalized,
            'name' => $names,
            'reviewed_at' => now(),
        ]);
    }
}
