<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SubscriptionStatus;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Subscription extends Model
{
    use BelongsToTenant;

    protected $fillable = ['plan', 'status', 'provider', 'provider_reference', 'provider_customer_reference', 'trial_ends_at', 'current_period_start', 'current_period_end', 'canceled_at', 'metadata_json'];

    protected function casts(): array
    {
        return ['status' => SubscriptionStatus::class, 'trial_ends_at' => 'datetime', 'current_period_start' => 'datetime', 'current_period_end' => 'datetime', 'canceled_at' => 'datetime', 'metadata_json' => 'array'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
