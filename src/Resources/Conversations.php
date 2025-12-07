<?php

declare(strict_types=1);

namespace Navi\Resources;

use Generator;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Navi\Exceptions\NaviException;
use Navi\Exceptions\NotFoundException;
use Navi\Exceptions\ValidationException;
use Navi\Models\ChatResponse;
use Navi\Models\Conversation;
use Navi\Models\MessagesPage;
use Navi\Models\StreamEvent;
use Navi\Streaming\SSEHandler;

/**
 * Conversations resource for managing conversations and chat.
 */
class Conversations
{
    public function __construct(
        private readonly Client $client,
        private readonly string $baseUrl,
    ) {}

    /**
     * Create a new conversation.
     *
     * @param array{
     *     agentId: string,
     *     userId?: string,
     *     userName?: string,
     *     title?: string,
     *     message?: string,
     *     context?: array<string, mixed>
     * } $params
     */
    public function create(array $params): Conversation
    {
        $response = $this->request('POST', '/conversations', $params);
        return Conversation::fromArray($response);
    }

    /**
     * List conversations.
     *
     * @param array{
     *     userId?: string,
     *     status?: string,
     *     limit?: int,
     *     offset?: int
     * } $params
     * @return Conversation[]
     */
    public function list(array $params = []): array
    {
        $response = $this->request('GET', '/conversations', $params);
        return array_map(
            fn(array $data) => Conversation::fromArray($data),
            $response
        );
    }

    /**
     * Get a specific conversation with its messages.
     */
    public function get(string $conversationId): Conversation
    {
        $response = $this->request('GET', "/conversations/{$conversationId}");
        return Conversation::fromArray($response);
    }

    /**
     * List messages in a conversation with pagination.
     *
     * @param array{
     *     limit?: int,
     *     offset?: int,
     *     order?: 'asc'|'desc'
     * } $params
     */
    public function messages(string $conversationId, array $params = []): MessagesPage
    {
        $response = $this->request('GET', "/conversations/{$conversationId}/messages", $params);
        return MessagesPage::fromArray($response);
    }

    /**
     * Send a message and receive streaming response via callback.
     *
     * @param callable(StreamEvent): void $callback Called for each stream event
     * @param array{
     *     context?: array<string, mixed>,
     *     runtimeParams?: array<string, mixed>
     * } $options
     *
     * Runtime parameters are dynamic values that can be injected at execution time
     * and referenced in agent configurations using ${params.key} syntax:
     * - In MCP headers: `Authorization: Bearer ${params.user_token}`
     * - In HTTP headers: `X-API-Key: ${params.api_key}`
     * - In custom functions: `tools.getParam('user_id')`
     *
     * Built-in parameters (automatically available):
     * - params.current_date: Current date (YYYY-MM-DD)
     * - params.current_datetime: Full ISO datetime
     * - params.current_timestamp: Unix timestamp in ms
     * - params.execution_id: Unique execution identifier
     */
    public function chat(
        string $conversationId,
        string $message,
        callable $callback,
        array $options = []
    ): void {
        $body = ['message' => $message];
        if (isset($options['context'])) {
            $body['context'] = $options['context'];
        }
        if (isset($options['runtimeParams'])) {
            $body['runtimeParams'] = $options['runtimeParams'];
        }

        $url = $this->baseUrl . "/conversations/{$conversationId}/chat";

        try {
            $response = $this->client->post($url, [
                'json' => $body,
                'stream' => true,
                'headers' => [
                    'Accept' => 'text/event-stream',
                ],
            ]);

            $stream = $response->getBody()->detach();
            if ($stream === null) {
                throw new NaviException('Failed to get response stream');
            }

            $handler = new SSEHandler();
            foreach ($handler->parseStream($stream) as $event) {
                $callback($event);

                // Stop if we received an error or complete event
                if ($event->isError() || $event->isComplete()) {
                    break;
                }
            }

            fclose($stream);
        } catch (GuzzleException $e) {
            throw $this->handleGuzzleException($e);
        }
    }

    /**
     * Send a message and receive streaming response as a generator.
     *
     * @param array{
     *     context?: array<string, mixed>,
     *     runtimeParams?: array<string, mixed>
     * } $options
     * @return Generator<StreamEvent>
     */
    public function chatStream(
        string $conversationId,
        string $message,
        array $options = []
    ): Generator {
        $body = ['message' => $message];
        if (isset($options['context'])) {
            $body['context'] = $options['context'];
        }
        if (isset($options['runtimeParams'])) {
            $body['runtimeParams'] = $options['runtimeParams'];
        }

        $url = $this->baseUrl . "/conversations/{$conversationId}/chat";

        try {
            $response = $this->client->post($url, [
                'json' => $body,
                'stream' => true,
                'headers' => [
                    'Accept' => 'text/event-stream',
                ],
            ]);

            $stream = $response->getBody()->detach();
            if ($stream === null) {
                throw new NaviException('Failed to get response stream');
            }

            $handler = new SSEHandler();
            foreach ($handler->parseStream($stream) as $event) {
                yield $event;

                // Stop if we received an error or complete event
                if ($event->isError() || $event->isComplete()) {
                    break;
                }
            }

            fclose($stream);
        } catch (GuzzleException $e) {
            throw $this->handleGuzzleException($e);
        }
    }

    /**
     * Send a message and wait for the complete response (non-streaming).
     *
     * @param array{
     *     context?: array<string, mixed>,
     *     runtimeParams?: array<string, mixed>
     * } $options
     */
    public function chatSync(
        string $conversationId,
        string $message,
        array $options = []
    ): ChatResponse {
        $events = [];
        foreach ($this->chatStream($conversationId, $message, $options) as $event) {
            $events[] = $event;
        }
        return ChatResponse::fromStreamEvents($events);
    }

    /**
     * Close a conversation.
     */
    public function close(string $conversationId): void
    {
        $this->request('DELETE', "/conversations/{$conversationId}");
    }

    /**
     * Make an HTTP request.
     *
     * @throws NaviException
     */
    private function request(string $method, string $path, array $params = []): array
    {
        $url = $this->baseUrl . $path;
        $options = [];

        if ($method === 'GET' && !empty($params)) {
            $options['query'] = $params;
        } elseif (!empty($params)) {
            $options['json'] = $params;
        }

        try {
            $response = $this->client->request($method, $url, $options);
            $body = $response->getBody()->getContents();

            if (empty($body)) {
                return [];
            }

            $decoded = json_decode($body, true);
            if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                throw new NaviException('Invalid JSON response: ' . json_last_error_msg());
            }

            return $decoded;
        } catch (GuzzleException $e) {
            throw $this->handleGuzzleException($e);
        }
    }

    /**
     * Handle Guzzle exceptions and convert to Navi exceptions.
     */
    private function handleGuzzleException(GuzzleException $e): NaviException
    {
        if ($e instanceof \GuzzleHttp\Exception\ClientException) {
            $response = $e->getResponse();
            $statusCode = $response->getStatusCode();
            $body = json_decode($response->getBody()->getContents(), true);
            $message = $body['message'] ?? $body['error'] ?? $e->getMessage();

            return match ($statusCode) {
                404 => new NotFoundException($message),
                400 => new ValidationException($message, $body['errors'] ?? []),
                default => new NaviException($message, $statusCode, $e, $body, $statusCode),
            };
        }

        return new NaviException($e->getMessage(), (int) $e->getCode(), $e);
    }
}
