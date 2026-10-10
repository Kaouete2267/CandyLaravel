<?php

namespace Modules\Fournisseurs\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Lunar\Models\Product;

/**
 * Fournisseur : coordonnées, conditions (conditionnements habituels : sac de vente et carton d'achat, délai de
 * livraison) et paramètres libres.
 */
#[Fillable(['name', 'contact_name', 'email', 'phone', 'address', 'default_bag_kg', 'default_carton_kg', 'lead_time_days', 'settings', 'notes'])]
class Supplier extends Model
{
    protected $table = 'suppliers';

    protected function casts(): array
    {
        return [
            'default_bag_kg' => 'integer',
            'default_carton_kg' => 'integer',
            'lead_time_days' => 'integer',
            'settings' => 'array',
        ];
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::modelClass(), 'product_supplier')
            ->using(ProductSupplier::class)
            ->withPivot(['id', 'reference', 'bag_kg', 'carton_kg', 'carton_price', 'is_main'])
            ->withTimestamps();
    }
}
