<?php

namespace App\Modules\Authentication\Identity\API\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    /**
     * Registration creates a new Organization plus its first
     * User, who is always the Owner — see 08-identity-lifecycle.md §2-3.
     * No authenticated identity is required to reach this endpoint.
     */
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
            'organization_name' => ['required', 'string', 'max:255'],
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'organization_name.required' => 'Please provide a name for your organization.',
            'email.unique' => 'An account with this email already exists.',
        ];
    }
}
