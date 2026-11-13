<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SettleReceivableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['paid_amount_cents' => ['prohibited'], 'paid_at' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:'.now('America/Sao_Paulo')->toDateString()], 'payment_method' => ['nullable', 'string', 'max:80'], 'notes' => ['nullable', 'string']];
    }
}
