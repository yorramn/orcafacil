<?php

namespace App\Models;

use App\Enums\ServiceStatus;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Service extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = ['client_id', 'quote_id', 'assigned_to', 'title', 'description', 'status', 'contracted_total_cents', 'scheduled_start', 'scheduled_end', 'completed_at', 'address_snapshot_json'];

    protected function casts(): array
    {
        return ['status' => ServiceStatus::class, 'scheduled_start' => 'datetime', 'scheduled_end' => 'datetime', 'completed_at' => 'datetime', 'address_snapshot_json' => 'array'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ServiceTask::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ServiceNote::class);
    }

    public function occurrences(): HasMany
    {
        return $this->hasMany(ServiceOccurrence::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function receivables(): HasMany
    {
        return $this->hasMany(Receivable::class);
    }

    public function billing(): HasOne
    {
        return $this->hasOne(ServiceBilling::class);
    }
}
