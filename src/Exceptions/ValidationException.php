<?php

declare(strict_types=1);

namespace Navi\Exceptions;

/**
 * Thrown when request validation fails.
 */
class ValidationException extends NaviException
{
    protected array $errors;

    public function __construct(string $message = 'Validation failed', array $errors = [])
    {
        parent::__construct($message, 400, null, null, 400);
        $this->errors = $errors;
    }

    /**
     * Get the validation errors.
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
