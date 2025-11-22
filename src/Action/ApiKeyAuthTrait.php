<?php

namespace Moonspot\Phlag\Action;

/**
 * API Key Authentication Trait
 *
 * Provides Bearer token authentication for flag endpoints by validating
 * the Authorization header against API keys stored in the database.
 *
 * ## Usage
 *
 * Classes using this trait should call `authenticateApiKey()` before
 * processing the request. If authentication fails, a 401 response
 * is automatically returned.
 *
 * ```php
 * class GetPhlagState extends Base {
 *     use ApiKeyAuthTrait;
 *
 *     public function loadData(): array {
 *         // Authenticate before processing
 *         if (!$this->authenticateApiKey()) {
 *             return []; // Already responded with 401
 *         }
 *         // Continue with normal processing
 *     }
 * }
 * ```
 *
 * ## Security Considerations
 *
 * - Requires Bearer token in Authorization header
 * - Token must exactly match an api_key in phlag_api_keys table
 * - Returns 401 Unauthorized if authentication fails
 * - Generic error messages prevent API key enumeration
 *
 * @package Moonspot\Phlag\Action
 */
trait ApiKeyAuthTrait {

    /**
     * Authenticates the request using Bearer token
     *
     * Extracts the Bearer token from the Authorization header and validates
     * it against the phlag_api_keys table. If authentication fails, returns
     * an error response array.
     *
     * ## How It Works
     *
     * - Checks for Authorization header
     * - Extracts Bearer token from header
     * - Queries database for matching API key
     * - Returns null if valid, error array otherwise
     *
     * ## Edge Cases
     *
     * - Missing Authorization header returns error array
     * - Invalid header format (not "Bearer <token>") returns error array
     * - Token not found in database returns error array
     * - All error messages are generic to prevent enumeration
     *
     * @return ?array Returns null if authenticated, error response array otherwise
     */
    protected function authenticateApiKey(): ?array {

        $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        // Check if Authorization header is present
        if (empty($auth_header)) {
            return $this->getUnauthorizedResponse('Missing authorization header');
        }

        // Extract Bearer token
        // Expected format: "Bearer <token>"
        $token = $this->extractBearerToken($auth_header);

        if ($token === null) {
            return $this->getUnauthorizedResponse('Invalid authorization header format');
        }

        // Validate token against database
        if (!$this->validateApiKey($token)) {
            return $this->getUnauthorizedResponse('Invalid API key');
        }

        return null;
    }

    /**
     * Extracts Bearer token from Authorization header
     *
     * Parses the Authorization header and extracts the token portion
     * after the "Bearer " prefix. Returns null if the format is invalid.
     *
     * ## Expected Format
     *
     * Authorization: Bearer <token>
     *
     * @param string $auth_header The Authorization header value
     *
     * @return ?string The extracted token or null if format is invalid
     */
    protected function extractBearerToken(string $auth_header): ?string {

        $token = null;

        // Check if header starts with "Bearer "
        if (stripos($auth_header, 'Bearer ') === 0) {
            // Extract token after "Bearer "
            $token = substr($auth_header, 7);
            
            // Trim whitespace
            $token = trim($token);
            
            // Return null if token is empty after extraction
            if ($token === '') {
                $token = null;
            }
        }

        return $token;
    }

    /**
     * Validates API key against database
     *
     * Queries the phlag_api_keys table to check if the provided token
     * exists. Returns true if found, false otherwise.
     *
     * Heads-up: This method requires the repository property to be set,
     * which is provided by the Base class that actions extend.
     *
     * @param string $token The API key token to validate
     *
     * @return bool Returns true if API key exists, false otherwise
     */
    protected function validateApiKey(string $token): bool {

        $is_valid = false;

        // Search for API key in database
        $results = $this->repository->find(
            'PhlagApiKey',
            ['api_key' => $token]
        );

        // If we found a matching key, it's valid
        if (!empty($results)) {
            $is_valid = true;
        }

        return $is_valid;
    }

    /**
     * Builds a 401 Unauthorized response array
     *
     * Creates a standardized error response for authentication failures.
     * Uses generic messages to prevent API key enumeration attacks.
     *
     * @param string $message Error message to include in response
     *
     * @return array Unauthorized response array with http_status, error, and message
     */
    protected function getUnauthorizedResponse(string $message): array {
        return [
            'http_status' => 401,
            'error'       => 'Unauthorized',
            'message'     => $message,
        ];
    }
}
