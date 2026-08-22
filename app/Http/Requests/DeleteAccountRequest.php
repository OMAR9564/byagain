<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class DeleteAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Only a signed-in person can delete their own account, and there is
        // no path by which one account reaches another's — the route takes no
        // identifier at all.
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // Deletion is irreversible, so it asks for the password again
            // even on a live session (FR-008).
            'password' => ['required', 'string', 'current_password'],
            'confirm' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'password.current_password' => __('auth.password'),
            'confirm.accepted' => __('settings.account.delete_help'),
        ];
    }
}
