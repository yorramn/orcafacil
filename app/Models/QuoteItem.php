<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class QuoteItem extends Model
{
    use BelongsToTenant;

    protected $fillable = ['quote_id', 'catalog_item_id', 'description_snapshot', 'unit', 'quantity', 'unit_price_cents', 'discount_cents', 'subtotal_cents', 'sort_order'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3'];
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class);
    }
}
