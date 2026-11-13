<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Quote;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;

final class QuoteAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $quote = $this->route('quote');

        return $quote instanceof Quote
            && $this->user()?->can('view', $quote) === true
            && (int) $quote->tenant_id === app(TenantContext::class)->id();
    }

    public function rules(): array
    {
        return ['file' => ['required', 'file', 'max:102400', 'mimetypes:image/jpeg,image/png,image/webp,video/mp4,video/webm,video/quicktime,application/pdf,text/plain']];
    }
}
