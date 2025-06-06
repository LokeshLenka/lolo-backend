<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidCreditManager implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_null($value)) return;

        $user = User::find($value);

        if (!$user || !$user->hasRole('cm') || !$user->isApproved()) {
            $fail("The selected $attribute is not verified as credit manager");
        }
    }
}
