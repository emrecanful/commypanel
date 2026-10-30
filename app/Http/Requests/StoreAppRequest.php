<?php

namespace App\Http\Requests;

use App\Deploy\Source\GitUrl;
use App\Enums\SourceType;
use App\Enums\Stack;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Step 1 of the app wizard: name, optional type, and where the code comes from.
 */
class StoreAppRequest extends FormRequest
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
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'stack_hint' => ['nullable', Rule::in([Stack::Dotnet->value, Stack::Go->value, Stack::Node->value])],
            'source' => ['required', Rule::in([SourceType::Git->value, SourceType::Upload->value])],
            'git_url' => ['exclude_unless:source,git', 'required', 'string', 'max:500', function (string $attribute, mixed $value, Closure $fail) {
                if (! GitUrl::isValid((string) $value, (bool) config('commypanel.deploy.git_allow_local'))) {
                    $fail('Use an https:// or ssh Git URL, for example https://github.com/acme/shop.git or git@github.com:acme/shop.git.');
                }
            }],
            'git_branch' => ['exclude_unless:source,git', 'required', 'string', function (string $attribute, mixed $value, Closure $fail) {
                if (! GitUrl::isValidBranch((string) $value)) {
                    $fail('That is not a valid branch name.');
                }
            }],
        ];
    }
}
