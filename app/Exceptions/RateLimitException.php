<?php

namespace App\Exceptions;

use Exception;

class RateLimitException extends Exception
{
    public function __construct(
        public ?string $resetTime = null,
        string $message = 'Rate limit exceeded'
    ) {
        parent::__construct($message);
    }

    /**
     * Get a human-readable description of when the rate limit resets.
     */
    public function getResetDescription(): string
    {
        return $this->resetTime ?? 'unknown time';
    }
}
