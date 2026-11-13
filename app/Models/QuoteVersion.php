<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class QuoteVersion extends Model
{
    use BelongsToTenant;

    protected $fillable = ['quote_id', 'version', 'snapshot_json', 'total_cents', 'created_by'];

    protected function casts(): array
    {
        return ['snapshot_json' => 'array'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
