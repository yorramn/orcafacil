<?php

namespace App\Http\Requests;

use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;

final class AttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $service = $this->route('service');

        return $this->user() !== null && $service && (int) $service->tenant_id === app(TenantContext::class)->id();
    }

    public function rules(): array
    {
        return ['file' => ['required', 'file', 'max:10240', 'mimetypes:image/jpeg,image/png,image/webp']];
    }
}
