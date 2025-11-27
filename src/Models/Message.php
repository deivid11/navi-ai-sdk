<?php

declare(strict_types=1);

namespace Navi\Models;

use DateTimeImmutable;

/**
 * Represents a message in a conversation.
 */
class Message
{
    public function __construct(
        public readonly string $id,
        public readonly string $role,
        public readonly string $content,
        public readonly DateTimeImmutable $timestamp,
    ) {}

    /**
     * Check if this message is from the user.
     */
    public function isUser(): bool
    {
        return $this->role === 'user';
    }

    /**
     * Check if this message is from the assistant.
     */
    public function isAssistant(): bool
    {
        return $this->role === 'assistant';
    }

    /**
     * Create a Message from an API response array.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            role: $data['role'],
            content: $data['content'],
            timestamp: new DateTimeImmutable($data['timestamp']),
        );
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'role' => $this->role,
            'content' => $this->content,
            'timestamp' => $this->timestamp->format('c'),
        ];
    }
}
