<?php

namespace App\Models;

use App\Enums\ReceivableStatus;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Receivable extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = ['client_id', 'quote_id', 'service_id', 'service_billing_id', 'installment_number', 'description', 'status', 'due_date', 'amount_cents', 'paid_amount_cents', 'paid_at', 'payment_method', 'notes', 'canceled_at', 'cancellation_reason'];

    protected function casts(): array
    {
        return ['status' => ReceivableStatus::class, 'due_date' => 'date', 'paid_at' => 'datetime', 'canceled_at' => 'datetime'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function serviceBilling(): BelongsTo
    {
        return $this->belongsTo(ServiceBilling::class);
    }

    public function scopeOpen($q)
    {
        return $q->whereIn('status', [ReceivableStatus::Pending, ReceivableStatus::Overdue]);
    }
}
