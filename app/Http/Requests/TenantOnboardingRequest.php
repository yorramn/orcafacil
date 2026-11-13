<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class TenantOnboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Tenant::class) === true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'alpha_dash', 'min:3', 'max:80', Rule::unique('tenants', 'slug')],
            'business_name' => ['nullable', 'string', 'max:255'],
            'timezone' => ['required', 'timezone'],
            'currency' => ['required', Rule::in(['BRL'])],
        ];
    }
}
