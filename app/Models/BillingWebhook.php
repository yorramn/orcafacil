<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class BillingWebhook extends Model
{
    protected $fillable = ['provider', 'event_id', 'event', 'payload_json', 'processed_at'];

    protected function casts(): array
    {
        return ['payload_json' => 'array', 'processed_at' => 'datetime'];
    }
}
