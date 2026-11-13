<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ServiceOccurrence extends Model
{
    use BelongsToTenant;

    protected $fillable = ['service_id', 'author_id', 'type', 'description', 'metadata_json', 'occurred_at'];

    protected function casts(): array
    {
        return ['metadata_json' => 'array', 'occurred_at' => 'datetime'];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
