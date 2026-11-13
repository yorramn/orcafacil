<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasAuditContext;
use Illuminate\Database\Eloquent\Model;

final class QuoteEvent extends Model
{
    use BelongsToTenant, HasAuditContext;

    public $timestamps = false;

    protected $fillable = ['quote_id', 'actor_user_id', 'event', 'metadata_json', 'occurred_at', 'request_id', 'ip_hash'];

    protected function casts(): array
    {
        return ['metadata_json' => 'array', 'occurred_at' => 'datetime'];
    }
}
