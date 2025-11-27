<?php

declare(strict_types=1);

namespace Navi\Models;

/**
 * Represents the complete response from a chat request.
 * Used when calling chatSync() instead of streaming.
 */
class ChatResponse
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $content,
        public readonly ?string $executionId = null,
        public readonly ?string $userMessageId = null,
        public readonly ?string $assistantMessageId = null,
        public readonly ?int $durationMs = null,
        public readonly ?int $tokensUsed = null,
        public readonly ?string $error = null,
    ) {}

    /**
     * Create a ChatResponse from stream events.
     */
    public static function fromStreamEvents(array $events): self
    {
        $content = '';
        $executionId = null;
        $userMessageId = null;
        $assistantMessageId = null;
        $durationMs = null;
        $tokensUsed = null;
        $error = null;
        $success = true;

        foreach ($events as $event) {
            if ($event instanceof StreamEvent) {
                match ($event->type) {
                    StreamEvent::TYPE_MESSAGE_CREATED => $userMessageId = $event->data['userMessageId'] ?? null,
                    StreamEvent::TYPE_RESPONSE_DELTA => $content .= $event->getText() ?? '',
                    StreamEvent::TYPE_COMPLETE => (function () use ($event, &$executionId, &$assistantMessageId, &$durationMs, &$tokensUsed) {
                        $executionId = $event->getExecutionId();
                        $assistantMessageId = $event->getAssistantMessageId();
                        $durationMs = $event->data['stats']['durationMs'] ?? null;
                        $tokensUsed = $event->data['stats']['tokensUsed'] ?? null;
                    })(),
                    StreamEvent::TYPE_ERROR => (function () use ($event, &$error, &$success) {
                        $error = $event->getError();
                        $success = false;
                    })(),
                    default => null,
                };
            }
        }

        return new self(
            success: $success,
            content: $content ?: null,
            executionId: $executionId,
            userMessageId: $userMessageId,
            assistantMessageId: $assistantMessageId,
            durationMs: $durationMs,
            tokensUsed: $tokensUsed,
            error: $error,
        );
    }
}
