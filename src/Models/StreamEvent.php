<?php

declare(strict_types=1);

namespace Navi\Models;

/**
 * Represents a Server-Sent Event from the chat stream.
 */
class StreamEvent
{
    /**
     * Event types
     */
    public const TYPE_MESSAGE_CREATED = 'message_created';
    public const TYPE_REASONING_START = 'reasoning_start';
    public const TYPE_REASONING_DELTA = 'reasoning_delta';
    public const TYPE_REASONING_COMPLETE = 'reasoning_complete';
    public const TYPE_TOOL_START = 'tool_start';
    public const TYPE_TOOL_COMPLETE = 'tool_complete';
    public const TYPE_RESPONSE_START = 'response_start';
    public const TYPE_RESPONSE_DELTA = 'response_delta';
    public const TYPE_RESPONSE_COMPLETE = 'response_complete';
    public const TYPE_COMPLETE = 'complete';
    public const TYPE_ERROR = 'error';

    public function __construct(
        public readonly string $type,
        public readonly array $data,
    ) {}

    /**
     * Check if this is a text delta event (reasoning or response).
     */
    public function isTextDelta(): bool
    {
        return in_array($this->type, [
            self::TYPE_REASONING_DELTA,
            self::TYPE_RESPONSE_DELTA,
        ]);
    }

    /**
     * Get text content if this is a delta event.
     */
    public function getText(): ?string
    {
        return $this->data['text'] ?? null;
    }

    /**
     * Check if this is the final complete event.
     */
    public function isComplete(): bool
    {
        return $this->type === self::TYPE_COMPLETE;
    }

    /**
     * Check if this is an error event.
     */
    public function isError(): bool
    {
        return $this->type === self::TYPE_ERROR;
    }

    /**
     * Get the error message if this is an error event.
     */
    public function getError(): ?string
    {
        return $this->data['error'] ?? null;
    }

    /**
     * Check if this is a tool event.
     */
    public function isToolEvent(): bool
    {
        return in_array($this->type, [
            self::TYPE_TOOL_START,
            self::TYPE_TOOL_COMPLETE,
        ]);
    }

    /**
     * Get tool name if this is a tool event.
     */
    public function getToolName(): ?string
    {
        return $this->data['tool'] ?? null;
    }

    /**
     * Get the final response content if this is the complete event.
     */
    public function getResponse(): ?string
    {
        return $this->data['response'] ?? null;
    }

    /**
     * Get the execution ID if available.
     */
    public function getExecutionId(): ?string
    {
        return $this->data['executionId'] ?? null;
    }

    /**
     * Get the assistant message ID if available.
     */
    public function getAssistantMessageId(): ?string
    {
        return $this->data['assistantMessageId'] ?? null;
    }
}
