<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Validation\Rules\Password;

trait PasswordValidationRules
{
    /**
     * Password rules for registration and reset.
     *
     * Ten characters rather than eight, and checked against Have I Been
     * Pwned's breach corpus (FR-005). No composition rules on top: forcing a
     * symbol produces `Password1!` and nothing safer.
     *
     * `uncompromised()` makes a k-anonymity API call. If it cannot reach the
     * service the rule passes rather than locking people out of their own
     * account.
     *
     * @return array<int, Rule|array<mixed>|string>
     */
    protected function passwordRules(): array
    {
        return [
            'required',
            'string',
            Password::min(10)->uncompromised(),
            'confirmed',
        ];
    }
}
