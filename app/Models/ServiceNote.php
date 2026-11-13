<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ServiceNote extends Model
{
    use BelongsToTenant;

    protected $fillable = ['service_id', 'author_id', 'body'];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
