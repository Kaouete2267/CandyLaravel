<?php

namespace Modules\Stock\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lunar\Admin\Models\Staff;
use Lunar\Models\ProductVariant;
use Modules\Stock\Enums\StockReason;

#[Fillable([
    'product_variant_id', 'staff_id', 'quantity', 'stock_after', 'reason',
    'input_unit', 'input_quantity', 'note',
])]
class StockMovement extends Model
{
    protected $table = 'stock_movements';

    protected function casts(): array
    {
        return ['reason' => StockReason::class];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::modelClass(), 'product_variant_id')->withTrashed();
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
