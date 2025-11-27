<?php

declare(strict_types=1);

namespace Navi;

use InvalidArgumentException;

/**
 * Widget Authentication Helper
 *
 * Use this class to generate signed user tokens for authenticated widget sessions.
 * Tokens are signed using HMAC-SHA256 with your widget secret.
 *
 * @example
 * ```php
 * // Initialize with your widget secret (from widget creation response)
 * $auth = new WidgetAuth('ws_a1b2c3d4e5f6...');
 *
 * // Generate a token for a user
 * $token = $auth->generateUserToken([
 *     'userId' => 'user_123',        // Required
 *     'name' => 'John Doe',          // Optional
 *     'email' => 'john@example.com', // Optional
 *     'context' => [                 // Optional - passed to the agent
 *         'plan' => 'premium',
 *         'company' => 'Acme Inc',
 *     ],
 * ]);
 *
 * // With expiration (1 hour from now)
 * $token = $auth->generateUserToken([
 *     'userId' => 'user_123',
 *     'name' => 'John Doe',
 * ], 3600);
 *
 * // Pass the token to your frontend widget
 * // Frontend: naviWidget.startSession({ userToken: '...' })
 * ```
 */
class WidgetAuth
{
    private string $widgetSecret;

    /**
     * Create a new WidgetAuth instance.
     *
     * @param string $widgetSecret The widget secret from widget creation (starts with 'ws_' or is a 64-char hex string)
     * @throws InvalidArgumentException If the secret is invalid
     */
    public function __construct(string $widgetSecret)
    {
        if (empty($widgetSecret)) {
            throw new InvalidArgumentException('Widget secret is required');
        }

        if (strlen($widgetSecret) < 32) {
            throw new InvalidArgumentException('Widget secret is too short');
        }

        $this->widgetSecret = $widgetSecret;
    }

    /**
     * Generate a signed user token for widget authentication.
     *
     * This token should be generated on your backend and passed to the frontend
     * widget when starting a session. It allows Navi to verify the user's identity
     * and maintain persistent conversations for that user.
     *
     * @param array{
     *     userId: string,
     *     name?: string,
     *     email?: string,
     *     context?: array<string, mixed>
     * } $userData User data to include in the token
     * @param int|null $expiresIn Token expiration time in seconds from now (null = no expiration)
     * @return string The signed token (format: base64(payload).hmac_signature)
     *
     * @throws InvalidArgumentException If userId is missing
     *
     * @example
     * ```php
     * // Basic usage
     * $token = $auth->generateUserToken(['userId' => 'user_123']);
     *
     * // With full user info
     * $token = $auth->generateUserToken([
     *     'userId' => 'user_123',
     *     'name' => 'John Doe',
     *     'email' => 'john@example.com',
     *     'context' => ['plan' => 'premium'],
     * ], 3600); // Expires in 1 hour
     * ```
     */
    public function generateUserToken(array $userData, ?int $expiresIn = null): string
    {
        // Validate required fields
        if (empty($userData['userId'])) {
            throw new InvalidArgumentException('userId is required in user data');
        }

        if (!is_string($userData['userId'])) {
            throw new InvalidArgumentException('userId must be a string');
        }

        // Build payload
        $payload = [
            'userId' => $userData['userId'],
        ];

        // Add optional fields
        if (isset($userData['name']) && is_string($userData['name'])) {
            $payload['name'] = $userData['name'];
        }

        if (isset($userData['email']) && is_string($userData['email'])) {
            $payload['email'] = $userData['email'];
        }

        if (isset($userData['context']) && is_array($userData['context'])) {
            $payload['context'] = $userData['context'];
        }

        // Add expiration if specified
        if ($expiresIn !== null && $expiresIn > 0) {
            $payload['exp'] = time() + $expiresIn;
        }

        // Encode payload as base64
        $encodedPayload = base64_encode(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        // Generate HMAC signature
        $signature = hash_hmac('sha256', $encodedPayload, $this->widgetSecret);

        // Return token in format: base64(payload).signature
        return $encodedPayload . '.' . $signature;
    }

    /**
     * Verify a user token (useful for debugging/testing).
     *
     * @param string $token The token to verify
     * @return array{valid: bool, payload?: array<string, mixed>, error?: string}
     *
     * @example
     * ```php
     * $result = $auth->verifyUserToken($token);
     * if ($result['valid']) {
     *     echo 'Token is valid for user: ' . $result['payload']['userId'];
     * } else {
     *     echo 'Token invalid: ' . $result['error'];
     * }
     * ```
     */
    public function verifyUserToken(string $token): array
    {
        // Split token
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return ['valid' => false, 'error' => 'Invalid token format'];
        }

        [$encodedPayload, $providedSignature] = $parts;

        // Verify signature
        $expectedSignature = hash_hmac('sha256', $encodedPayload, $this->widgetSecret);

        if (!hash_equals($expectedSignature, $providedSignature)) {
            return ['valid' => false, 'error' => 'Invalid signature'];
        }

        // Decode payload
        $payloadJson = base64_decode($encodedPayload);
        if ($payloadJson === false) {
            return ['valid' => false, 'error' => 'Invalid payload encoding'];
        }

        $payload = json_decode($payloadJson, true);
        if ($payload === null) {
            return ['valid' => false, 'error' => 'Invalid payload JSON'];
        }

        // Check expiration
        if (isset($payload['exp']) && is_int($payload['exp'])) {
            if (time() > $payload['exp']) {
                return ['valid' => false, 'error' => 'Token has expired', 'payload' => $payload];
            }
        }

        return ['valid' => true, 'payload' => $payload];
    }

    /**
     * Decode a token without verifying (useful for debugging).
     *
     * @param string $token The token to decode
     * @return array<string, mixed>|null The payload or null if decoding failed
     */
    public static function decodeToken(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return null;
        }

        $payloadJson = base64_decode($parts[0]);
        if ($payloadJson === false) {
            return null;
        }

        return json_decode($payloadJson, true);
    }
}
