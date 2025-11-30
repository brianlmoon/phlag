<?php

namespace Moonspot\Phlag\Action;

/**
 * API Key Authentication Trait
 *
 * Provides Bearer token authentication for flag endpoints by validating
 * the Authorization header against API keys stored in the database, and
 * enforcing environment-scoped access control.
 *
 * ## Breaking Change (v3.0)
 *
 * The `authenticateApiKey()` method now requires an `$environment_name`
 * parameter to validate that the API key has access to the requested
 * environment. This enables restricting API keys to specific environments
 * for improved security.
 *
 * ## Access Control Model
 *
 * - **No environment assignments** → Unrestricted access (backward compatible)
 * - **One or more assignments** → Restricted to only those environments
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
 *         $auth_error = $this->authenticateApiKey($this->environment);
 *         
 *         if ($auth_error !== null) {
 *             return $auth_error;
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
 * - Validates API key has access to requested environment
 * - Returns 401 Unauthorized if authentication or authorization fails
 * - Generic error messages prevent API key enumeration
 *
 * @package Moonspot\Phlag\Action
 */
trait ApiKeyAuthTrait {

    /**
     * Authenticates API key and validates environment access
     *
     * Validates the Bearer token and checks if the API key has access
     * to the requested environment. API keys with no environment assignments
     * have unrestricted access (backward compatible).
     *
     * ## How It Works
     *
     * - Checks for Authorization header
     * - Extracts Bearer token from header
     * - Queries database for matching API key
     * - Validates environment exists
     * - Checks if API key is authorized for environment
     * - Returns null if valid, error array otherwise
     *
     * ## Edge Cases
     *
     * - Missing Authorization header returns error array
     * - Invalid header format (not "Bearer <token>") returns error array
     * - Token not found in database returns error array
     * - Non-existent environment returns error array
     * - API key not authorized for environment returns error array
     * - All error messages are generic to prevent enumeration
     *
     * @param string $environment_name Environment name to validate access for
     *
     * @return ?array Returns null if authenticated and authorized, error response array otherwise
     */
    protected function authenticateApiKey(string $environment_name): ?array {

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

        // Validate token and get API key record
        $api_key = $this->getValidApiKey($token);
        
        if ($api_key === null) {
            return $this->getUnauthorizedResponse('Invalid API key');
        }

        // Validate environment access
        if (!$this->validateEnvironmentAccess($api_key->plag_api_key_id, $environment_name)) {
            return $this->getUnauthorizedResponse('API key not authorized for this environment');
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
     * Retrieves API key record if valid
     *
     * Returns the PhlagApiKey object if found, null otherwise.
     *
     * Heads-up: This method requires the repository property to be set,
     * which is provided by the Base class that actions extend.
     *
     * @param string $token The API key token to validate
     *
     * @return ?\Moonspot\Phlag\Data\PhlagApiKey API key object or null
     */
    protected function getValidApiKey(string $token): ?\Moonspot\Phlag\Data\PhlagApiKey {

        $api_key = null;

        // Search for API key in database
        $results = $this->repository->find(
            'PhlagApiKey',
            ['api_key' => $token]
        );

        // If we found a matching key, return it
        if (!empty($results)) {
            $api_key = reset($results);
        }

        return $api_key;
    }

    /**
     * Validates API key has access to environment
     *
     * Checks if the API key is authorized for the requested environment.
     * API keys with no environment assignments have unrestricted access.
     *
     * ## Logic
     *
     * - If no environments assigned to key → Allow (global access)
     * - If environments assigned → Check if requested environment is in list
     *
     * ## Edge Cases
     *
     * - Non-existent environment returns false
     * - Empty environment name returns false
     * - Invalid API key ID returns false
     *
     * @param int    $api_key_id      API key ID
     * @param string $environment_name Environment name to check
     *
     * @return bool Returns true if authorized, false otherwise
     */
    protected function validateEnvironmentAccess(int $api_key_id, string $environment_name): bool {

        $is_authorized = false;

        // Validate inputs
        if ($api_key_id <= 0 || empty($environment_name)) {
            return false;
        }

        // Get environment record
        $environments = $this->repository->find(
            'PhlagEnvironment',
            ['name' => $environment_name]
        );

        if (empty($environments)) {
            // Environment doesn't exist - deny access
            return false;
        }

        $environment = reset($environments);

        // Get all environment assignments for this API key
        $assignments = $this->repository->find(
            'PhlagApiKeyEnvironment',
            ['plag_api_key_id' => $api_key_id]
        );

        // No assignments = unrestricted access (backward compatible)
        if (empty($assignments)) {
            $is_authorized = true;
        } else {
            // Check if requested environment is in the assigned list
            foreach ($assignments as $assignment) {
                if ($assignment->phlag_environment_id === $environment->phlag_environment_id) {
                    $is_authorized = true;
                    break;
                }
            }
        }

        return $is_authorized;
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
