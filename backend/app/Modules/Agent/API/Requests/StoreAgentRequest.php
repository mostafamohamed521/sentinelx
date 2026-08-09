<?php

namespace App\Modules\Agent\API\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAgentRequest extends FormRequest
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
            'name' => ['required', 'string', 'min:1', 'max:255'],
            'framework' => ['required', 'string', 'min:1', 'max:100'],
            'framework_version' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:2000'],
            // organization_id and status are never accepted from the request
            // body — always server-derived. Rejected outright (422), never
            // silently stripped. See 03-lifecycle.md §2 and 06-api-contract.md §4.
            'organization_id' => ['prohibited'],
            'status' => ['prohibited'],
        ];
    }

    // No failedValidation() override — the platform-wide nested
    // VALIDATION_ERROR envelope this class used to hand-author here is now
    // produced globally, for every FormRequest, by bootstrap/app.php's
    // ValidationException render (ERROR-001/ERROR-004: one definition, not
    // several that happen to currently agree).
}
