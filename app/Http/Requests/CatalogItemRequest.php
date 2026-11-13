<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CatalogItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['type' => ['required', 'in:service,material,fee'], 'name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string'], 'unit' => ['required', 'string', 'max:20'], 'default_unit_price_cents' => ['required', 'integer', 'min:0'], 'default_cost_cents' => ['nullable', 'integer', 'min:0'], 'active' => ['sometimes', 'boolean']];
    }
}
