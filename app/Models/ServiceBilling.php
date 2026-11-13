<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ServiceBilling extends Model
{
    use BelongsToTenant;

    protected $fillable = ['service_id', 'billed_by', 'total_cents', 'billed_at'];

    protected function casts(): array
    {
        return ['billed_at' => 'datetime'];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function receivables(): HasMany
    {
        return $this->hasMany(Receivable::class);
    }
}
