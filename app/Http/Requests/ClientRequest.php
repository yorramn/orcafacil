<?php

namespace App\Http\Requests;

use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;

final class ClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        $client = $this->route('client');

        return $this->user() !== null && (! $client || (int) $client->tenant_id === app(TenantContext::class)->id());
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:255'], 'kind' => ['required', 'in:person,company,PF,PJ'], 'document' => ['nullable', 'string', 'max:30'], 'email' => ['nullable', 'email', 'max:255'], 'phone' => ['nullable', 'string', 'max:30'], 'whatsapp' => ['nullable', 'string', 'max:30'], 'address' => ['nullable', 'string', 'max:255'], 'address_number' => ['nullable', 'string', 'max:30'], 'address_complement' => ['nullable', 'string', 'max:100'], 'neighborhood' => ['nullable', 'string', 'max:100'], 'city' => ['nullable', 'string', 'max:100'], 'state' => ['nullable', 'string', 'size:2'], 'postal_code' => ['nullable', 'string', 'max:20'], 'notes' => ['nullable', 'string'], 'status' => ['sometimes', 'in:active,archived']];
    }
}
