<?php

namespace Moonspot\Phlag\Web\Security;

/**
 * CSRF Token Manager
 *
 * Manages generation, validation, and storage of CSRF tokens to protect
 * against Cross-Site Request Forgery attacks. Tokens are stored in the
 * user's session and validated on form submissions.
 *
 * Usage:
 *
 * ```php
 * // Generate token for a form
 * $token = CsrfToken::generate();
 *
 * // In your template
 * <input type="hidden" name="csrf_token" value="<?= $token ?>">
 *
 * // Validate on form submission
 * if (!CsrfToken::validate($_POST['csrf_token'])) {
 *     throw new \Exception('Invalid CSRF token');
 * }
 * ```
 *
 * Heads-up: Tokens are regenerated after each validation to prevent
 * replay attacks. Sessions must be started before using this class.
 */
class CsrfToken {

    /**
     * Session key where CSRF token is stored
     */
    protected const SESSION_KEY = 'csrf_token';

    /**
     * Generates a new CSRF token and stores it in the session
     *
     * Creates a cryptographically secure random token using random_bytes()
     * and base64 encoding. The token is stored in the session for later
     * validation. Each call generates a fresh token.
     *
     * @return string The generated CSRF token (43 characters)
     *
     * @throws \Exception If random_bytes() fails to generate random data
     */
    public static function generate(): string {
        $token = bin2hex(random_bytes(32));
        $_SESSION[self::SESSION_KEY] = $token;
        return $token;
    }

    /**
     * Validates a CSRF token against the session value
     *
     * Compares the provided token with the one stored in the session using
     * timing-safe comparison to prevent timing attacks. After validation,
     * the token is regenerated to prevent replay attacks.
     *
     * Edge cases:
     * - Returns false if no token is stored in session
     * - Returns false if provided token is null or empty
     * - Returns false on timing attack attempts
     *
     * @param string|null $token The token to validate from form submission
     *
     * @return bool True if token is valid, false otherwise
     */
    public static function validate(?string $token): bool {
        $return = false;

        if (empty($token)) {
            return $return;
        }

        if (!isset($_SESSION[self::SESSION_KEY])) {
            return $return;
        }

        $stored_token = $_SESSION[self::SESSION_KEY];

        if (hash_equals($stored_token, $token)) {
            $return = true;
        }

        // Regenerate token after validation to prevent replay attacks
        self::generate();

        return $return;
    }

    /**
     * Gets the current CSRF token from session without generating new one
     *
     * Useful when you need to retrieve the token multiple times on the same
     * page without regenerating it. If no token exists in the session, this
     * generates a new one.
     *
     * @return string The current CSRF token
     */
    public static function get(): string {
        if (!isset($_SESSION[self::SESSION_KEY])) {
            return self::generate();
        }

        return $_SESSION[self::SESSION_KEY];
    }

    /**
     * Destroys the current CSRF token
     *
     * Removes the token from the session. Useful during logout or when
     * you want to invalidate all existing tokens.
     *
     * @return void
     */
    public static function destroy(): void {
        unset($_SESSION[self::SESSION_KEY]);
    }
}
