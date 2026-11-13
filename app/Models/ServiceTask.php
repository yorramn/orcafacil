<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ServiceTask extends Model
{
    use BelongsToTenant;

    protected $fillable = ['service_id', 'title', 'description', 'position', 'status', 'completed_at', 'completed_by'];

    protected function casts(): array
    {
        return ['completed_at' => 'datetime'];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
