<?php

namespace App\Modules\Authentication\Identity\API\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    /**
     * PATCH /me acts only on the caller's own record — not a Role
     * question at all, see 05-profile.md §2 and 07-authorization.md §2.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * email and role are never editable here — see 05-profile.md §4 — so
     * they're deliberately not listed as validated/fillable fields at all,
     * rather than accepted and silently ignored.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
        ];
    }
}
