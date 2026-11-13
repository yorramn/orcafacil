<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;

final class ServiceTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $service = $this->route('service');

        return $this->user() !== null && $service && (int) $service->tenant_id === app(TenantContext::class)->id();
    }

    public function rules(): array
    {
        return ['title' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string'], 'position' => ['sometimes', 'integer', 'min:0']];
    }
}
