<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Step 3 of the app wizard: runtime settings and environment variables.
 */
class FinishSetupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'container_port' => ['required', 'integer', 'between:1,65535'],
            'health_path' => ['required', 'string', 'max:200', 'regex:#^/[A-Za-z0-9._~/%?=&+-]*$#'],
            'memory_limit_mb' => ['nullable', 'integer', 'between:16,65536'],
            'cpu_limit' => ['nullable', 'numeric', 'between:0.05,64'],
            'env' => ['nullable', 'string', 'max:200000'],
        ];
    }
}
