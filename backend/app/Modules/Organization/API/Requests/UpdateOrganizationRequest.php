<?php

namespace App\Modules\Organization\API\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrganizationRequest extends FormRequest
{
    /**
     * Role gate (Owner only) is enforced by
     * Organization\API\Middleware\EnsureOwnerRole at the route level, not
     * here — consistent with every other module's FormRequest, which
     * always returns true and leaves Role checks to routing/middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `name` is the only field this Sprint makes editable — see
     * 04-organization-settings.md §2. `slug` and `status` are explicitly
     * rejected (422), never silently stripped, per 08-api-contract.md §2.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['prohibited'],
            'status' => ['prohibited'],
        ];
    }
}
