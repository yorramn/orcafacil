<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\CatalogItem;
use App\Models\Client;
use App\Models\Quote;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class QuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $quote = $this->route('quote');

        if ($this->user() === null) {
            return false;
        }

        return $quote === null
            ? $this->user()->can('create', Quote::class)
            : $this->user()->can('update', $quote);
    }

    protected function prepareForValidation(): void
    {
        $data = [];
        foreach (['discount' => 'discount_cents', 'extra' => 'extra_cents'] as $source => $target) {
            if ($this->filled($source) && ! $this->has($target)) {
                $data[$target] = self::cents($this->input($source));
            }
        }

        $items = collect($this->input('items', []))->map(function (mixed $item): mixed {
            if (! is_array($item)) {
                return $item;
            }
            if (isset($item['unit_price']) && ! isset($item['unit_price_cents'])) {
                $item['unit_price_cents'] = self::cents($item['unit_price']);
            }

            return $item;
        })->all();

        $this->merge([...$data, 'items' => $items]);
    }

    private static function cents(mixed $value): int
    {
        $value = trim((string) $value);
        if (! preg_match('/^\d+(?:[\.,]\d{1,2})?$/', $value)) {
            throw ValidationException::withMessages(['amount' => 'Valores monetários devem ser informados em centavos inteiros ou texto decimal.']);
        }

        [$whole, $fraction] = array_pad(preg_split('/[\.,]/', $value, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();

        return ['client_id' => ['required', 'integer', Rule::exists(Client::class, 'id')->where('tenant_id', $tenantId)], 'title' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string'], 'discount_cents' => ['nullable', 'integer', 'min:0'], 'extra_cents' => ['nullable', 'integer', 'min:0'], 'valid_until' => ['nullable', 'date'], 'payment_terms' => ['nullable', 'string'], 'estimated_days' => ['nullable', 'integer', 'min:0'], 'internal_notes' => ['nullable', 'string'], 'public_notes' => ['nullable', 'string'], 'items' => ['required', 'array', 'min:1'], 'items.*.catalog_item_id' => ['nullable', 'integer', Rule::exists(CatalogItem::class, 'id')->where('tenant_id', $tenantId)], 'items.*.description' => ['required', 'string', 'max:255'], 'items.*.unit' => ['required', 'string', 'max:20'], 'items.*.quantity' => ['required', 'regex:/^(?:0|[1-9]\d*)(?:[\.,]\d{1,3})?$/', 'gt:0'], 'items.*.unit_price_cents' => ['required', 'integer', 'min:0'], 'items.*.discount_cents' => ['nullable', 'integer', 'min:0'], 'media' => ['sometimes', 'array', 'max:10'], 'media.*' => ['file', 'max:102400', 'mimetypes:image/jpeg,image/png,image/webp,video/mp4,video/webm,video/quicktime,application/pdf,text/plain']];
    }
}
