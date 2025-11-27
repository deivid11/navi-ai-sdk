<?php

declare(strict_types=1);

namespace Navi\Models;

use DateTimeImmutable;

/**
 * Represents a conversation with an agent.
 */
class Conversation
{
    public function __construct(
        public readonly string $id,
        public readonly string $status,
        public readonly ?string $title = null,
        public readonly ?string $agentId = null,
        public readonly ?string $agentName = null,
        public readonly ?string $userId = null,
        public readonly ?DateTimeImmutable $createdAt = null,
        public readonly ?DateTimeImmutable $lastActivityAt = null,
        public readonly ?int $messageCount = null,
        /** @var Message[] */
        public readonly array $messages = [],
    ) {}

    /**
     * Check if the conversation is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if the conversation is closed.
     */
    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    /**
     * Create a Conversation from an API response array.
     */
    public static function fromArray(array $data): self
    {
        $messages = [];
        if (isset($data['messages']) && is_array($data['messages'])) {
            $messages = array_map(
                fn(array $msg) => Message::fromArray($msg),
                $data['messages']
            );
        }

        return new self(
            id: $data['id'],
            status: $data['status'],
            title: $data['title'] ?? null,
            agentId: $data['agentId'] ?? null,
            agentName: $data['agentName'] ?? null,
            userId: $data['userId'] ?? null,
            createdAt: isset($data['createdAt']) ? new DateTimeImmutable($data['createdAt']) : null,
            lastActivityAt: isset($data['lastActivityAt']) ? new DateTimeImmutable($data['lastActivityAt']) : null,
            messageCount: $data['messageCount'] ?? null,
            messages: $messages,
        );
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'title' => $this->title,
            'agentId' => $this->agentId,
            'agentName' => $this->agentName,
            'userId' => $this->userId,
            'createdAt' => $this->createdAt?->format('c'),
            'lastActivityAt' => $this->lastActivityAt?->format('c'),
            'messageCount' => $this->messageCount,
            'messages' => array_map(fn(Message $m) => $m->toArray(), $this->messages),
        ];
    }
}
