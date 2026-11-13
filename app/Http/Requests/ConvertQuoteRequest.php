<?php

namespace App\Http\Requests;

use App\Models\Quote;
use App\Services\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ConvertQuoteRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->route('quote') !== null && ! $this->has('quote_id')) {
            $this->merge(['quote_id' => $this->route('quote')->getKey()]);
        }
    }

    public function authorize(): bool
    {
        $quote = $this->route('quote');

        return $this->user() !== null && $quote !== null
            ? $this->user()->can('convert', $quote)
            : $this->user() !== null && app(TenantContext::class)->get()->quotes()->whereKey($this->integer('quote_id'))->exists();
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();
        $today = CarbonImmutable::today('America/Sao_Paulo');
        $start = $this->input('scheduled_start');
        $validStart = is_string($start)
            && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $start, $parts)
            && checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]);
        $latestEnd = $validStart
            ? CarbonImmutable::parse($start, 'America/Sao_Paulo')->addMonthsNoOverflow(6)->toDateString()
            : $today->addMonthsNoOverflow(6)->toDateString();

        return [
            'quote_id' => ['required', 'integer', Rule::exists(Quote::class, 'id')->where('tenant_id', $tenantId)],
            'assigned_to' => ['nullable', 'integer', Rule::exists('tenant_user', 'user_id')->where('tenant_id', $tenantId)->where('status', 'active')],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'scheduled_start' => ['nullable', 'required_with:scheduled_end', 'date_format:Y-m-d', 'after_or_equal:'.$today->toDateString(), 'before_or_equal:'.$today->addDays(90)->toDateString()],
            'scheduled_end' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:scheduled_start', 'after_or_equal:'.$today->toDateString(), 'before_or_equal:'.$latestEnd],
            'tasks' => ['nullable', 'array'],
            'tasks.*.title' => ['required', 'string', 'max:255'],
            'tasks.*.description' => ['nullable', 'string'],
            'images' => ['nullable', 'array', 'max:10'],
            'images.*' => ['required', 'file', 'max:10240', 'mimetypes:image/jpeg,image/png,image/webp'],
        ];
    }
}
