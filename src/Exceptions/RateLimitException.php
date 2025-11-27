<?php

declare(strict_types=1);

namespace Navi\Exceptions;

/**
 * Thrown when rate limit is exceeded.
 */
class RateLimitException extends NaviException
{
    protected ?int $retryAfter;

    public function __construct(string $message = 'Rate limit exceeded', ?int $retryAfter = null)
    {
        parent::__construct($message, 429, null, null, 429);
        $this->retryAfter = $retryAfter;
    }

    /**
     * Get the number of seconds to wait before retrying.
     */
    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }
}
