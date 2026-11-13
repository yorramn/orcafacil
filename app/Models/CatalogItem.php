<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class CatalogItem extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = ['type', 'name', 'description', 'unit', 'default_unit_price_cents', 'default_cost_cents', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
