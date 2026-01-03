<?php

namespace Moonspot\Phlag\Web\Service;

use DealNews\GetConfig\GetConfig;
use League\OAuth2\Client\Provider\Google;
use League\OAuth2\Client\Token\AccessToken;
use Moonspot\Phlag\Data\GoogleUser;

/**
 * Google OAuth Service
 *
 * Handles Google OAuth authentication flow for the Phlag application.
 * Wraps the league/oauth2-google provider and provides methods for
 * initiating OAuth, exchanging tokens, and retrieving user profile data.
 *
 * ## Responsibilities
 *
 * - Check if OAuth is enabled and configured
 * - Generate authorization URLs for redirecting to Google
 * - Exchange authorization codes for access tokens
 * - Retrieve and validate Google user profiles
 * - Validate email domains against allowed list
 * - Generate secure random passwords for OAuth-only accounts
 *
 * ## Configuration
 *
 * Set the following configuration values using GetConfig:
 *
 * - `google_oauth.enabled` - "true" to enable OAuth
 * - `google_oauth.client_id` - Google OAuth client ID
 * - `google_oauth.client_secret` - Google OAuth client secret
 * - `google_oauth.allowed_domains` - Comma-separated allowed domains (optional)
 *
 * ## Usage
 *
 * ```php
 * $oauth_service = new GoogleOAuthService();
 *
 * if ($oauth_service->isEnabled()) {
 *     // Redirect user to Google
 *     $auth_url = $oauth_service->getAuthorizationUrl();
 *     $_SESSION['oauth_state'] = $oauth_service->getState();
 *     header('Location: ' . $auth_url);
 * }
 * ```
 *
 * ## Edge Cases
 *
 * - Returns false from isEnabled() if any required config is missing
 * - Domain validation is case-insensitive
 * - Empty allowed_domains config permits any domain
 * - Generated passwords are 32 bytes of cryptographically secure data
 *
 * Heads-up: The callback URL must be registered in Google Cloud Console
 * and must match exactly what is configured there.
 *
 * @package Moonspot\Phlag\Web\Service
 */
class GoogleOAuthService {

    /**
     * GetConfig instance for reading configuration
     *
     * @var GetConfig
     */
    protected GetConfig $config;

    /**
     * Google OAuth provider instance
     *
     * @var ?Google
     */
    protected ?Google $provider = null;

    /**
     * OAuth state parameter for CSRF protection
     *
     * @var string
     */
    protected string $state = '';

    /**
     * Last error message
     *
     * @var string
     */
    protected string $last_error = '';

    /**
     * Creates the Google OAuth service
     *
     * Initializes the service with GetConfig for reading configuration.
     * The Google provider is lazily initialized only when needed.
     *
     * @param GetConfig|null $config Optional GetConfig instance for testing
     */
    public function __construct(?GetConfig $config = null) {

        $this->config = $config ?? GetConfig::init();
    }

    /**
     * Checks if Google OAuth is enabled and properly configured
     *
     * Returns true only if:
     * - google_oauth.enabled is set to "true" or "1"
     * - google_oauth.client_id is set
     * - google_oauth.client_secret is set
     *
     * Heads-up: PHP's parse_ini_file() converts `true` to `"1"` and `false`
     * to `""`, so we accept both "true" and "1" as enabled values.
     *
     * ## Usage
     *
     * ```php
     * if ($oauth_service->isEnabled()) {
     *     // Show Google login button
     * }
     * ```
     *
     * @return bool True if OAuth is enabled and configured
     */
    public function isEnabled(): bool {

        $enabled_value = $this->config->get('google_oauth.enabled');
        $enabled       = $enabled_value === 'true' || $enabled_value === '1';
        $client_id     = $this->config->get('google_oauth.client_id');
        $client_secret = $this->config->get('google_oauth.client_secret');

        return $enabled && !empty($client_id) && !empty($client_secret);
    }

    /**
     * Gets the Google OAuth authorization URL
     *
     * Generates a URL for redirecting users to Google's OAuth consent screen.
     * After authorization, Google redirects back to the callback URL with
     * an authorization code.
     *
     * ## Security Considerations
     *
     * - Store the state parameter in session before redirecting
     * - Validate state on callback to prevent CSRF attacks
     *
     * ## Usage
     *
     * ```php
     * $auth_url = $oauth_service->getAuthorizationUrl();
     * $_SESSION['oauth_state'] = $oauth_service->getState();
     * header('Location: ' . $auth_url);
     * exit;
     * ```
     *
     * @param string $callback_url The callback URL to receive the auth code
     *
     * @return string Authorization URL to redirect user to
     */
    public function getAuthorizationUrl(string $callback_url): string {

        $provider = $this->getProvider($callback_url);

        $auth_url    = $provider->getAuthorizationUrl([
            'scope' => ['email', 'profile'],
        ]);
        $this->state = $provider->getState();

        return $auth_url;
    }

    /**
     * Gets the OAuth state parameter
     *
     * The state parameter is a randomly generated value used to prevent
     * CSRF attacks. It should be stored in the session before redirecting
     * and validated on callback.
     *
     * @return string The current state parameter
     */
    public function getState(): string {

        return $this->state;
    }

    /**
     * Exchanges an authorization code for an access token
     *
     * After Google redirects back to the callback URL with an authorization
     * code, this method exchanges it for an access token that can be used
     * to retrieve user profile data.
     *
     * ## Edge Cases
     *
     * - Returns null if the code is invalid or expired
     * - Returns null if there's a network error
     * - Logs error details for debugging
     *
     * @param string $code         Authorization code from Google callback
     * @param string $callback_url The callback URL (must match original)
     *
     * @return ?AccessToken Access token or null on failure
     */
    public function getAccessToken(string $code, string $callback_url): ?AccessToken {

        $token = null;

        try {
            $provider = $this->getProvider($callback_url);
            $token    = $provider->getAccessToken('authorization_code', [
                'code' => $code,
            ]);
        } catch (\Throwable $e) {
            $this->last_error = $e->getMessage();
            error_log("Google OAuth token exchange failed: {$this->last_error}");
        }

        return $token;
    }

    /**
     * Retrieves the Google user profile using an access token
     *
     * Fetches the user's Google profile data including their unique ID,
     * email address, and display name.
     *
     * ## Usage
     *
     * ```php
     * $token = $oauth_service->getAccessToken($code, $callback_url);
     * if ($token) {
     *     $google_user = $oauth_service->getGoogleUser($token, $callback_url);
     *     echo $google_user->email;
     * }
     * ```
     *
     * @param AccessToken $token        Access token from getAccessToken()
     * @param string      $callback_url The callback URL for provider init
     *
     * @return ?GoogleUser User profile or null on failure
     */
    public function getGoogleUser(
        AccessToken $token,
        string $callback_url
    ): ?GoogleUser {

        $google_user = null;

        try {
            $provider     = $this->getProvider($callback_url);
            $owner_data   = $provider->getResourceOwner($token);
            $google_user  = new GoogleUser();

            $google_user->google_id = $owner_data->getId();
            $google_user->email     = $owner_data->getEmail();
            $google_user->name      = $owner_data->getName() ?? '';

        } catch (\Throwable $e) {
            $this->last_error = $e->getMessage();
            error_log("Google OAuth user fetch failed: {$this->last_error}");
        }

        return $google_user;
    }

    /**
     * Validates an email domain against the allowed domains list
     *
     * Checks if the email's domain is in the configured allowed_domains list.
     * If no domains are configured, all emails are allowed.
     *
     * ## How It Works
     *
     * 1. Extracts domain from email address
     * 2. Loads allowed domains from configuration
     * 3. Compares domain against allowed list (case-insensitive)
     *
     * ## Usage
     *
     * ```php
     * if (!$oauth_service->isAllowedDomain('user@example.com')) {
     *     throw new \Exception('Email domain not allowed');
     * }
     * ```
     *
     * @param string $email Email address to validate
     *
     * @return bool True if domain is allowed or no restrictions configured
     */
    public function isAllowedDomain(string $email): bool {

        $allowed = true;

        $allowed_domains_config = $this->config->get('google_oauth.allowed_domains');

        // If no domains configured, allow all
        if (empty($allowed_domains_config)) {
            return true;
        }

        // Extract domain from email
        $email_parts = explode('@', $email, 2);
        if (count($email_parts) !== 2) {
            return false;
        }
        $email_domain = strtolower(trim($email_parts[1]));

        // Parse allowed domains (comma-separated)
        $allowed_domains = array_map(
            fn($d) => strtolower(trim($d)),
            explode(',', $allowed_domains_config)
        );

        // Filter out empty values
        $allowed_domains = array_filter($allowed_domains);

        // If filter results in empty array, allow all
        if (empty($allowed_domains)) {
            return true;
        }

        $allowed = in_array($email_domain, $allowed_domains, true);

        return $allowed;
    }

    /**
     * Generates a cryptographically secure random password
     *
     * Creates a 32-byte random password for OAuth-only accounts. These
     * accounts authenticate via Google and don't need to know their password,
     * but a password is still required by the database schema.
     *
     * ## Security Considerations
     *
     * - Uses random_bytes() for cryptographic security
     * - 32 bytes = 256 bits of entropy
     * - Password is not displayed to the user
     *
     * @return string Base64-encoded random password
     */
    public function generateRandomPassword(): string {

        return base64_encode(random_bytes(32));
    }

    /**
     * Gets the last error message
     *
     * Returns the error message from the last failed OAuth operation.
     * Useful for logging or displaying detailed error information.
     *
     * @return string Error message or empty string if no error
     */
    public function getError(): string {

        return $this->last_error;
    }

    /**
     * Gets a configured Google OAuth provider instance
     *
     * Initializes a new Google provider with credentials from config and
     * the provided callback URL. A fresh provider instance is created on
     * each call to ensure the redirect URI matches the current context.
     *
     * @param string $callback_url The OAuth callback URL
     *
     * @return Google The configured Google provider
     */
    protected function getProvider(string $callback_url): Google {

        // Always create a new provider with the correct callback URL
        $this->provider = new Google([
            'clientId'     => $this->config->get('google_oauth.client_id'),
            'clientSecret' => $this->config->get('google_oauth.client_secret'),
            'redirectUri'  => $callback_url,
        ]);

        return $this->provider;
    }
}
