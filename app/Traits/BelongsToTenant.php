<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\Tenant;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::creating(function (object $model): void {
            if ($model->getAttribute('tenant_id') !== null) {
                return;
            }

            try {
                $model->setAttribute('tenant_id', app(TenantContext::class)->id());
            } catch (LogicException) {
                // Factories and public flows may provide the tenant through a parent relation.
                foreach (['quote', 'service', 'client', 'attachable'] as $relation) {
                    if (method_exists($model, $relation) && ($parent = $model->{$relation})?->tenant_id !== null) {
                        $model->setAttribute('tenant_id', $parent->tenant_id);
                        break;
                    }
                }
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where($this->qualifyColumn('tenant_id'), $tenantId);
    }
}
