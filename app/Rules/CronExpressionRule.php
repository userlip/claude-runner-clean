<?php

namespace App\Rules;

use Closure;
use Cron\CronExpression;
use Illuminate\Contracts\Validation\ValidationRule;

class CronExpressionRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            new CronExpression((string) $value);
        } catch (\Throwable $e) {
            $fail('Invalid cron expression.');
        }
    }
}
