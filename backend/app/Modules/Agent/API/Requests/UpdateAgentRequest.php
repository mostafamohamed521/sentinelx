<?php

namespace App\Modules\Agent\API\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'min:1', 'max:255'],
            'framework' => ['sometimes', 'string', 'min:1', 'max:100'],
            'framework_version' => ['sometimes', 'nullable', 'string', 'max:50'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            // Immutable via this endpoint — status only changes via Archive,
            // organization_id is never client-supplied. See 03-lifecycle.md §3.
            'organization_id' => ['prohibited'],
            'status' => ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $mutableFields = ['name', 'framework', 'framework_version', 'description'];

            if (empty(array_intersect($mutableFields, array_keys($this->mutableInput())))) {
                $validator->errors()->add('_', 'At least one field must be provided.');
            }
        });
    }

    /**
     * `validated()` cannot be called before validation finishes — this
     * reads the raw input instead, restricted to the fields we care about.
     *
     * @return array<string, mixed>
     */
    private function mutableInput(): array
    {
        return $this->only(['name', 'framework', 'framework_version', 'description']);
    }

    // No failedValidation() override — see StoreAgentRequest's own comment
    // on why (ERROR-001/ERROR-004).
}
