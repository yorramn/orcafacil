<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\ServiceTask;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ServiceTaskStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $task = $this->route('task');

        return $task instanceof ServiceTask && $this->user()?->can('updateStatus', $task) === true;
    }

    public function rules(): array
    {
        return ['status' => ['required', 'string', Rule::in(['pending', 'completed'])]];
    }
}
