<?php

declare(strict_types=1);

namespace Navi\Exceptions;

use Exception;
use Throwable;

/**
 * Base exception for all Navi SDK errors.
 */
class NaviException extends Exception
{
    protected ?array $responseBody;
    protected ?int $statusCode;

    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        ?array $responseBody = null,
        ?int $statusCode = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->responseBody = $responseBody;
        $this->statusCode = $statusCode;
    }

    /**
     * Get the response body from the API if available.
     */
    public function getResponseBody(): ?array
    {
        return $this->responseBody;
    }

    /**
     * Get the HTTP status code if available.
     */
    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }
}
