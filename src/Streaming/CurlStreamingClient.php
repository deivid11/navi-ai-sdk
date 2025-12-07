<?php

declare(strict_types=1);

namespace Navi\Streaming;

use Navi\Exceptions\NaviException;
use Navi\Models\StreamEvent;

/**
 * Curl-based streaming client for true real-time SSE streaming.
 *
 * Uses CURLOPT_WRITEFUNCTION to process data chunks as they arrive,
 * avoiding Guzzle's potential buffering issues.
 */
class CurlStreamingClient
{
    private string $apiKey;
    private SSEHandler $sseHandler;
    private bool $verifySsl;
    private int $readTimeout;

    public function __construct(
        string $apiKey,
        bool $verifySsl = true,
        int $readTimeout = 300
    ) {
        $this->apiKey = $apiKey;
        $this->sseHandler = new SSEHandler();
        $this->verifySsl = $verifySsl;
        $this->readTimeout = $readTimeout;
    }

    /**
     * Make a streaming POST request and process SSE events via callback.
     *
     * @param string $url Full URL to request
     * @param array $body Request body (will be JSON encoded)
     * @param callable(StreamEvent): void $callback Called for each SSE event
     * @throws NaviException
     */
    public function stream(string $url, array $body, callable $callback): void
    {
        $ch = curl_init();

        if ($ch === false) {
            throw new NaviException('Failed to initialize curl');
        }

        $this->sseHandler->reset();
        $buffer = '';

        // Set up the write callback that processes chunks as they arrive
        $writeCallback = function ($ch, string $data) use ($callback, &$buffer): int {
            $buffer .= $data;

            // Process complete SSE events from buffer
            while (($pos = strpos($buffer, "\n\n")) !== false) {
                $eventBlock = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 2);

                $event = $this->parseEventBlock($eventBlock);
                if ($event !== null) {
                    $callback($event);

                    // Stop processing on error or complete
                    if ($event->isError() || $event->isComplete()) {
                        return 0; // This will abort the transfer
                    }
                }
            }

            return strlen($data);
        };

        try {
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($body),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Accept: text/event-stream',
                    'Authorization: Bearer ' . $this->apiKey,
                    'User-Agent: Navi-PHP-SDK/1.0.0',
                    'Cache-Control: no-cache',
                ],
                CURLOPT_WRITEFUNCTION => $writeCallback,
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => $this->readTimeout,
                CURLOPT_CONNECTTIMEOUT => 30,
                CURLOPT_SSL_VERIFYPEER => $this->verifySsl,
                CURLOPT_SSL_VERIFYHOST => $this->verifySsl ? 2 : 0,
                // Disable buffering for immediate streaming
                CURLOPT_BUFFERSIZE => 128,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            ]);

            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            $errno = curl_errno($ch);

            // Process any remaining data in buffer
            if (!empty($buffer)) {
                $event = $this->parseEventBlock($buffer);
                if ($event !== null) {
                    $callback($event);
                }
            }

            // Check for curl errors (but ignore if we manually aborted via return 0)
            if ($errno !== 0 && $errno !== CURLE_WRITE_ERROR) {
                throw new NaviException("Curl error ({$errno}): {$error}");
            }

            // Check HTTP status code
            if ($httpCode >= 400) {
                throw new NaviException("HTTP error {$httpCode}");
            }

        } finally {
            curl_close($ch);
        }
    }

    /**
     * Make a streaming POST request and return events as a generator.
     *
     * @param string $url Full URL to request
     * @param array $body Request body (will be JSON encoded)
     * @return \Generator<StreamEvent>
     * @throws NaviException
     */
    public function streamGenerator(string $url, array $body): \Generator
    {
        $ch = curl_init();

        if ($ch === false) {
            throw new NaviException('Failed to initialize curl');
        }

        $this->sseHandler->reset();

        // Use a shared buffer and event queue for the generator
        $eventQueue = new \SplQueue();
        $buffer = '';
        $finished = false;
        $error = null;

        $writeCallback = function ($ch, string $data) use (&$buffer, &$eventQueue, &$finished): int {
            $buffer .= $data;

            // Process complete SSE events from buffer
            while (($pos = strpos($buffer, "\n\n")) !== false) {
                $eventBlock = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 2);

                $event = $this->parseEventBlock($eventBlock);
                if ($event !== null) {
                    $eventQueue->enqueue($event);

                    if ($event->isError() || $event->isComplete()) {
                        $finished = true;
                        return 0;
                    }
                }
            }

            return strlen($data);
        };

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: text/event-stream',
                'Authorization: Bearer ' . $this->apiKey,
                'User-Agent: Navi-PHP-SDK/1.0.0',
                'Cache-Control: no-cache',
            ],
            CURLOPT_WRITEFUNCTION => $writeCallback,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => $this->readTimeout,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => $this->verifySsl,
            CURLOPT_SSL_VERIFYHOST => $this->verifySsl ? 2 : 0,
            CURLOPT_BUFFERSIZE => 128,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        ]);

        // Execute the request (this blocks until complete, but events are queued)
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $errno = curl_errno($ch);
        curl_close($ch);

        // Check for errors
        if ($errno !== 0 && $errno !== CURLE_WRITE_ERROR) {
            throw new NaviException("Curl error ({$errno}): {$curlError}");
        }

        if ($httpCode >= 400) {
            throw new NaviException("HTTP error {$httpCode}");
        }

        // Process remaining buffer
        if (!empty($buffer)) {
            $event = $this->parseEventBlock($buffer);
            if ($event !== null) {
                $eventQueue->enqueue($event);
            }
        }

        // Yield all queued events
        while (!$eventQueue->isEmpty()) {
            yield $eventQueue->dequeue();
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
            $decoded = ['raw' => $data];
        }

        return new StreamEvent($eventType, $decoded);
    }
}