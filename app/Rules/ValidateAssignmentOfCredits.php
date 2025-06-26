<?php

namespace App\Rules;

use App\Enums\EventType;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidateAssignmentOfCredits implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === EventType::Public->value) {
            $fail("You can't provide credits if event type is {$value}");
        }
    }
}
