<?php

declare(strict_types=1);

namespace Navi\Exceptions;

/**
 * Thrown when a requested resource is not found.
 */
class NotFoundException extends NaviException
{
    public function __construct(string $message = 'Resource not found')
    {
        parent::__construct($message, 404, null, null, 404);
    }
}
