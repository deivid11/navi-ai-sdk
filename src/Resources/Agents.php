<?php

declare(strict_types=1);

namespace Navi\Resources;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Navi\Exceptions\NaviException;
use Navi\Exceptions\NotFoundException;
use Navi\Exceptions\ValidationException;
use Navi\Models\Agent;

/**
 * Agents resource for discovering available agents.
 */
class Agents
{
    public function __construct(
        private readonly Client $client,
        private readonly string $baseUrl,
    ) {}

    /**
     * List available agents for this integration.
     *
     * @param array{
     *     search?: string,
     *     limit?: int,
     *     offset?: int
     * } $params
     * @return Agent[]
     */
    public function list(array $params = []): array
    {
        $response = $this->request('GET', '/agents', $params);
        return array_map(
            fn(array $data) => Agent::fromArray($data),
            $response
        );
    }

    /**
     * Get a specific agent by ID.
     */
    public function get(string $agentId): Agent
    {
        $agents = $this->list();
        foreach ($agents as $agent) {
            if ($agent->id === $agentId) {
                return $agent;
            }
        }
        throw new NotFoundException("Agent {$agentId} not found or not allowed");
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
