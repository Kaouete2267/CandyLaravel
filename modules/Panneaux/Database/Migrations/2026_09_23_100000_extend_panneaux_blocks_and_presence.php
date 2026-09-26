<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Étend les panneaux d'après l'ancienne application :
 *  - blocs de plusieurs sortes : « manual » (bonbons choisis), « brand » (alimenté automatiquement par une marque),
 *    « native-mark » / « native-legend » (blocs système, non supprimables), « custom » (bloc d'info libre) ;
 *  - réglages par bloc : actif, zone (avant / étiquettes / après), largeur sur 12, répétition par page, titre, contenu ;
 *  - présence d'un bonbon sur un panneau (actif = en stock, DLUO), différente d'un panneau à l'autre ;
 *  - un bonbon peut figurer sur plusieurs panneaux (l'unicité de block_product sur product_id disparaît).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blocks', function (Blueprint $table) {
            $table->string('kind', 20)->default('manual')->after('panel_id');
            $table->foreignId('brand_id')->nullable()->after('kind')->constrained('lunar_brands')->nullOnDelete();
            $table->string('zone', 6)->default('labels')->after('brand_id');        // before | labels | after
            $table->boolean('active')->default(true)->after('zone');
            $table->string('title')->nullable()->after('name');
            $table->text('content')->nullable()->after('title');
            $table->unsignedTinyInteger('width')->default(6)->after('position');   // blocs d'info : sur une grille de 12
            $table->boolean('repeat')->default(false)->after('width');             // blocs d'info : sur chaque page
            $table->boolean('show_header')->default(true)->after('repeat');        // blocs d'étiquettes : bandeau de titre
            $table->json('options')->nullable()->after('show_header');
        });

        // Un bonbon peut être sur plusieurs panneaux : on remplace l'unicité par un simple index (nécessaire à la clé étrangère).
        Schema::table('block_product', function (Blueprint $table) {
            $table->index('product_id', 'block_product_product_id_index');
        });
        Schema::table('block_product', function (Blueprint $table) {
            $table->dropUnique('block_product_product_id_unique');
        });

        Schema::create('panel_products', function (Blueprint $table) {
            $table->foreignId('panel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('lunar_products')->cascadeOnDelete();
            $table->boolean('active')->default(true);   // actif = en stock : s'imprime sur ce panneau
            $table->string('dluo', 7)->nullable();      // MM/AAAA, informatif
            $table->timestamps();

            $table->primary(['panel_id', 'product_id']);
        });

        // Continuité avec les données existantes : un bloc nommé comme une marque devient un bloc « marque »
        // automatique, et les bonbons déjà placés deviennent présents sur leur panneau.
        foreach (DB::table('blocks')->get() as $block) {
            $brand = $block->name ? DB::table('lunar_brands')->where('name', $block->name)->first() : null;

            if ($brand) {
                DB::table('blocks')->where('id', $block->id)->update(['kind' => 'brand', 'brand_id' => $brand->id]);
            } elseif ($block->code === 'DIV') {
                DB::table('blocks')->where('id', $block->id)->update(['kind' => 'brand', 'brand_id' => null]);
            }
        }

        foreach (DB::table('block_product')->join('blocks', 'blocks.id', '=', 'block_product.block_id')->get(['blocks.panel_id', 'block_product.product_id']) as $row) {
            DB::table('panel_products')->insertOrIgnore(['panel_id' => $row->panel_id, 'product_id' => $row->product_id, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('panel_products');

        Schema::table('block_product', function (Blueprint $table) {
            $table->unique('product_id', 'block_product_product_id_unique');
            $table->dropIndex('block_product_product_id_index');
        });

        Schema::table('blocks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('brand_id');
            $table->dropColumn(['kind', 'zone', 'active', 'title', 'content', 'width', 'repeat', 'show_header', 'options']);
        });
    }
};
