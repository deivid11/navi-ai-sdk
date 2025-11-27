<?php

declare(strict_types=1);

namespace Navi\Streaming;

use Generator;
use Navi\Models\StreamEvent;

/**
 * Handles Server-Sent Events (SSE) stream parsing.
 */
class SSEHandler
{
    private string $buffer = '';

    /**
     * Parse SSE data from a stream.
     *
     * @param resource $stream The stream resource to read from
     * @return Generator<StreamEvent>
     */
    public function parseStream($stream): Generator
    {
        while (!feof($stream)) {
            $chunk = fread($stream, 8192);
            if ($chunk === false) {
                break;
            }

            yield from $this->processChunk($chunk);
        }

        // Process any remaining data in buffer
        if (!empty($this->buffer)) {
            yield from $this->processBuffer();
        }
    }

    /**
     * Parse SSE data from a string.
     *
     * @return Generator<StreamEvent>
     */
    public function parseString(string $data): Generator
    {
        yield from $this->processChunk($data);

        // Process any remaining data in buffer
        if (!empty($this->buffer)) {
            yield from $this->processBuffer();
        }
    }

    /**
     * Process a chunk of data.
     *
     * @return Generator<StreamEvent>
     */
    private function processChunk(string $chunk): Generator
    {
        $this->buffer .= $chunk;
        yield from $this->processBuffer();
    }

    /**
     * Process the buffer and yield events.
     *
     * @return Generator<StreamEvent>
     */
    private function processBuffer(): Generator
    {
        // Split by double newlines (event separator)
        while (($pos = strpos($this->buffer, "\n\n")) !== false) {
            $eventBlock = substr($this->buffer, 0, $pos);
            $this->buffer = substr($this->buffer, $pos + 2);

            $event = $this->parseEventBlock($eventBlock);
            if ($event !== null) {
                yield $event;
            }
        }
    }

    /**
     * Parse a single SSE event block.
     */
    private function parseEventBlock(string $block): ?StreamEvent
    {
        $lines = explode("\n", $block);
        $eventType = null;
        $data = '';

        foreach ($lines as $line) {
            $line = trim($line);

            if (empty($line)) {
                continue;
            }

            if (str_starts_with($line, 'event:')) {
                $eventType = trim(substr($line, 6));
            } elseif (str_starts_with($line, 'data:')) {
                $data .= trim(substr($line, 5));
            }
        }

        if ($eventType === null || empty($data)) {
            return null;
        }

        $decoded = json_decode($data, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            // If JSON decode fails, wrap the raw data
            $decoded = ['raw' => $data];
        }

        return new StreamEvent($eventType, $decoded);
    }

    /**
     * Reset the handler state.
     */
    public function reset(): void
    {
        $this->buffer = '';
    }
}
