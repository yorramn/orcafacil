<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Client extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = ['name', 'kind', 'document', 'email', 'phone', 'whatsapp', 'address', 'address_number', 'address_complement', 'neighborhood', 'city', 'state', 'postal_code', 'notes', 'status'];

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function receivables(): HasMany
    {
        return $this->hasMany(Receivable::class);
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return $search ? $query->where(fn (Builder $q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")) : $query;
    }
}
