<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\QuoteStatus;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

final class Quote extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = ['client_id', 'created_by', 'number', 'title', 'description', 'status', 'valid_until', 'subtotal_cents', 'discount_cents', 'extra_cents', 'total_cents', 'payment_terms', 'estimated_days', 'internal_notes', 'public_notes', 'public_token_hash', 'public_token_encrypted', 'public_token_expires_at', 'public_revoked_at', 'sent_at', 'viewed_at', 'approved_at', 'rejected_at'];

    protected $hidden = ['public_token_hash', 'public_token_encrypted'];

    protected $appends = ['display_number'];

    public function getDisplayNumberAttribute(): string
    {
        return str_pad((string) $this->number, 4, '0', STR_PAD_LEFT)
            .'-'.($this->created_at?->format('y') ?? now()->format('y'));
    }

    protected function casts(): array
    {
        return ['status' => QuoteStatus::class, 'valid_until' => 'date', 'public_token_encrypted' => 'encrypted', 'public_token_expires_at' => 'datetime', 'public_revoked_at' => 'datetime', 'sent_at' => 'datetime', 'viewed_at' => 'datetime', 'approved_at' => 'datetime', 'rejected_at' => 'datetime'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class)->orderBy('sort_order');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(QuoteVersion::class)->latest();
    }

    public function events(): HasMany
    {
        return $this->hasMany(QuoteEvent::class)->latest('occurred_at');
    }

    public function service(): HasOne
    {
        return $this->hasOne(Service::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function publicIsAvailable(): bool
    {
        return $this->public_token_hash !== null && $this->public_revoked_at === null && ($this->public_token_expires_at === null || $this->public_token_expires_at->isFuture());
    }

    public function publicUrl(): ?string
    {
        return $this->publicIsAvailable() && $this->public_token_encrypted
            ? route('public.quotes.show', ['token' => $this->public_token_encrypted])
            : null;
    }
}
