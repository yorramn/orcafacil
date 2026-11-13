<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasAuditContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ActivityLog extends Model
{
    use BelongsToTenant, HasAuditContext;

    public $timestamps = false;

    protected $fillable = ['actor_user_id', 'event', 'subject_type', 'subject_id', 'properties_json', 'request_id', 'ip_hash', 'created_at'];

    protected function casts(): array
    {
        return ['properties_json' => 'array', 'created_at' => 'datetime'];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
