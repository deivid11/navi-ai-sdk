<?php

declare(strict_types=1);

namespace Navi\Models;

/**
 * Represents the API integration status.
 */
class ApiStatus
{
    public function __construct(
        public readonly string $status,
        public readonly string $integrationName,
        public readonly ?int $rateLimitPerMinute = null,
        public readonly ?string $defaultAgentId = null,
        public readonly ?string $defaultAgentName = null,
    ) {}

    /**
     * Check if the integration is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if a rate limit is configured.
     */
    public function hasRateLimit(): bool
    {
        return $this->rateLimitPerMinute !== null;
    }

    /**
     * Check if a default agent is configured.
     */
    public function hasDefaultAgent(): bool
    {
        return $this->defaultAgentId !== null;
    }

    /**
     * Create an ApiStatus from an API response array.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            status: $data['status'],
            integrationName: $data['integrationName'],
            rateLimitPerMinute: $data['rateLimitPerMinute'] ?? null,
            defaultAgentId: $data['defaultAgentId'] ?? null,
            defaultAgentName: $data['defaultAgentName'] ?? null,
        );
    }
}
