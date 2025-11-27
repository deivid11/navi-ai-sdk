<?php

declare(strict_types=1);

namespace Navi;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Navi\Exceptions\AuthenticationException;
use Navi\Exceptions\NaviException;
use Navi\Exceptions\RateLimitException;
use Navi\Models\ApiStatus;
use Navi\Resources\Agents;
use Navi\Resources\Conversations;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Navi PHP SDK Client
 *
 * Main entry point for interacting with the Navi API.
 *
 * @example
 * ```php
 * $navi = new NaviClient('navi_sk_your_api_key', [
 *     'base_url' => 'https://your-navi.com'
 * ]);
 *
 * // Create a conversation
 * $conversation = $navi->conversations->create([
 *     'agentId' => 'agent-uuid',
 *     'userId' => 'user-123'
 * ]);
 *
 * // Chat with streaming
 * $navi->conversations->chat($conversation->id, 'Hello!', function($event) {
 *     if ($event->isTextDelta()) {
 *         echo $event->getText();
 *     }
 * });
 * ```
 */
class NaviClient
{
    private const DEFAULT_BASE_URL = 'http://localhost:3000';
    private const DEFAULT_API_PATH = '/api/integration';
    private const DEFAULT_TIMEOUT = 30;
    private const STREAM_TIMEOUT = 300; // 5 minutes for streaming

    private Client $httpClient;
    private string $baseUrl;

    /**
     * Conversations resource for managing conversations and chat.
     */
    public readonly Conversations $conversations;

    /**
     * Agents resource for discovering available agents.
     */
    public readonly Agents $agents;

    /**
     * Create a new Navi client.
     *
     * @param string $apiKey Your Navi API key (starts with navi_sk_)
     * @param array{
     *     base_url?: string,
     *     api_path?: string,
     *     timeout?: int,
     *     verify_ssl?: bool,
     *     http_client?: Client
     * } $options
     *
     * @throws AuthenticationException If API key is invalid format
     */
    public function __construct(string $apiKey, array $options = [])
    {
        $this->validateApiKey($apiKey);

        $baseUrl = rtrim($options['base_url'] ?? self::DEFAULT_BASE_URL, '/');
        $apiPath = $options['api_path'] ?? self::DEFAULT_API_PATH;
        $this->baseUrl = $baseUrl . $apiPath;

        // Create HTTP client with middleware
        if (isset($options['http_client'])) {
            $this->httpClient = $options['http_client'];
        } else {
            $stack = HandlerStack::create();

            // Add authentication header
            $stack->push(Middleware::mapRequest(function (RequestInterface $request) use ($apiKey) {
                return $request->withHeader('Authorization', 'Bearer ' . $apiKey);
            }));

            // Add error handling middleware
            $stack->push($this->createErrorMiddleware());

            $this->httpClient = new Client([
                'handler' => $stack,
                'timeout' => $options['timeout'] ?? self::DEFAULT_TIMEOUT,
                'read_timeout' => self::STREAM_TIMEOUT,
                'verify' => $options['verify_ssl'] ?? true,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'User-Agent' => 'Navi-PHP-SDK/1.0.0',
                ],
            ]);
        }

        // Initialize resources
        $this->conversations = new Conversations($this->httpClient, $this->baseUrl);
        $this->agents = new Agents($this->httpClient, $this->baseUrl);
    }

    /**
     * Get the API integration status.
     */
    public function status(): ApiStatus
    {
        try {
            $response = $this->httpClient->get($this->baseUrl . '/status');
            $data = json_decode($response->getBody()->getContents(), true);
            return ApiStatus::fromArray($data);
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            throw new NaviException('Failed to get API status: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get the base URL being used.
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * Validate the API key format.
     */
    private function validateApiKey(string $apiKey): void
    {
        if (empty($apiKey)) {
            throw new AuthenticationException('API key is required');
        }

        if (!str_starts_with($apiKey, 'navi_sk_')) {
            throw new AuthenticationException('Invalid API key format. Key should start with "navi_sk_"');
        }

        if (strlen($apiKey) < 20) {
            throw new AuthenticationException('Invalid API key format. Key is too short');
        }
    }

    /**
     * Create error handling middleware.
     */
    private function createErrorMiddleware(): callable
    {
        return function (callable $handler) {
            return function (RequestInterface $request, array $options) use ($handler) {
                return $handler($request, $options)->then(
                    function (ResponseInterface $response) {
                        return $response;
                    },
                    function (\Exception $e) {
                        if ($e instanceof \GuzzleHttp\Exception\ClientException) {
                            $response = $e->getResponse();
                            $statusCode = $response->getStatusCode();

                            if ($statusCode === 401) {
                                throw new AuthenticationException('Invalid or expired API key');
                            }

                            if ($statusCode === 429) {
                                $retryAfter = $response->getHeader('Retry-After')[0] ?? null;
                                throw new RateLimitException(
                                    'Rate limit exceeded',
                                    $retryAfter ? (int) $retryAfter : null
                                );
                            }
                        }

                        throw $e;
                    }
                );
            };
        };
    }
}
