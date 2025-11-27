<?php

declare(strict_types=1);

namespace Navi\Exceptions;

/**
 * Thrown when API key authentication fails.
 */
class AuthenticationException extends NaviException
{
    public function __construct(string $message = 'Invalid or missing API key')
    {
        parent::__construct($message, 401, null, null, 401);
    }
}
