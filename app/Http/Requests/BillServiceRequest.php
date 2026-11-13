<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Service;
use Illuminate\Foundation\Http\FormRequest;

final class BillServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $service = $this->route('service');

        return $service instanceof Service && $this->user()?->can('update', $service) === true;
    }

    public function rules(): array
    {
        return [
            'installments' => ['present', 'array', 'max:60'],
            'installments.*.description' => ['required', 'string', 'max:255'],
            'installments.*.due_date' => ['required', 'date_format:Y-m-d'],
            'installments.*.amount_cents' => ['required', 'integer', 'min:1'],
        ];
    }
}
