<?php

declare(strict_types=1);

namespace Navi\Models;

/**
 * Represents a paginated list of messages.
 */
class MessagesPage
{
    public function __construct(
        /** @var Message[] */
        public readonly array $messages,
        public readonly int $total,
        public readonly int $limit,
        public readonly int $offset,
        public readonly bool $hasMore,
    ) {}

    /**
     * Create a MessagesPage from an API response array.
     */
    public static function fromArray(array $data): self
    {
        $messages = array_map(
            fn(array $msg) => Message::fromArray($msg),
            $data['messages'] ?? []
        );

        return new self(
            messages: $messages,
            total: $data['total'] ?? 0,
            limit: $data['limit'] ?? 50,
            offset: $data['offset'] ?? 0,
            hasMore: $data['hasMore'] ?? false,
        );
    }

    /**
     * Get the offset for the next page.
     */
    public function getNextOffset(): int
    {
        return $this->offset + $this->limit;
    }

    /**
     * Check if this is the first page.
     */
    public function isFirstPage(): bool
    {
        return $this->offset === 0;
    }

    /**
     * Check if this is the last page.
     */
    public function isLastPage(): bool
    {
        return !$this->hasMore;
    }
}
