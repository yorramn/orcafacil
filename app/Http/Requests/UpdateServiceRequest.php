<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $service = $this->route('service');

        return $service instanceof Service && $this->user()?->can('update', $service) === true;
    }

    public function rules(): array
    {
        /** @var Service $service */
        $service = $this->route('service');
        $today = CarbonImmutable::today('America/Sao_Paulo');
        $start = $this->input('scheduled_start');
        $end = $this->input('scheduled_end');
        $startChanged = $start !== $service->scheduled_start?->toDateString();
        $endChanged = $end !== $service->scheduled_end?->toDateString();
        $validStart = is_string($start)
            && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $start, $parts)
            && checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]);
        $latestEnd = $validStart
            ? CarbonImmutable::parse($start, 'America/Sao_Paulo')->addMonthsNoOverflow(6)->toDateString()
            : $today->addMonthsNoOverflow(6)->toDateString();

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'scheduled_start' => [
                'nullable', 'required_with:scheduled_end', 'date_format:Y-m-d',
                ...($startChanged ? ['after_or_equal:'.$today->toDateString(), 'before_or_equal:'.$today->addDays(90)->toDateString()] : []),
            ],
            'scheduled_end' => [
                'nullable', 'date_format:Y-m-d', 'after_or_equal:scheduled_start',
                ...($endChanged || $startChanged ? ['after_or_equal:'.$today->toDateString(), 'before_or_equal:'.$latestEnd] : []),
            ],
        ];
    }
}
