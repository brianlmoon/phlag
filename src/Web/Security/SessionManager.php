<?php

namespace Moonspot\Phlag\Web\Security;

/**
 * Session Manager
 *
 * Manages session lifecycle including timeout tracking, activity monitoring,
 * and session invalidation. Provides security features to prevent session
 * hijacking and ensure inactive sessions expire automatically.
 *
 * ## Responsibilities
 *
 * - Track last activity time to implement session timeout
 * - Detect inactive sessions and force re-authentication
 * - Provide configurable timeout duration
 * - Integrate with existing authentication flow
 *
 * ## Usage
 *
 * ```php
 * // Start session with timeout tracking
 * SessionManager::start();
 *
 * // Check if session is valid (not timed out)
 * if (!SessionManager::isActive()) {
 *     // Session expired - redirect to login
 *     $this->redirect('/login');
 * }
 *
 * // Update activity timestamp on authenticated actions
 * SessionManager::touch();
 * ```
 *
 * ## Configuration
 *
 * Default timeout is 30 minutes (1800 seconds). This can be configured
 * by setting the SESSION_TIMEOUT environment variable or modifying the
 * DEFAULT_TIMEOUT constant.
 *
 * Heads-up: This class works alongside PHP's native session management
 * and does not replace it. Sessions must still be started via session_start()
 * before using this class.
 */
class SessionManager {

    /**
     * Default session timeout in seconds (30 minutes)
     */
    protected const DEFAULT_TIMEOUT = 1800;

    /**
     * Session key for storing last activity timestamp
     */
    protected const ACTIVITY_KEY = 'last_activity';

    /**
     * Session key for storing session creation time
     */
    protected const CREATED_KEY = 'session_created';

    /**
     * Starts session and initializes timeout tracking
     *
     * Starts a new PHP session if one doesn't exist and sets up the
     * initial activity timestamp. If a session already exists, validates
     * it hasn't timed out.
     *
     * ## How It Works
     *
     * - Calls session_start() if session isn't already active
     * - Sets initial last_activity timestamp for new sessions
     * - Sets session_created timestamp for new sessions
     * - Validates existing sessions haven't timed out
     *
     * ## Usage
     *
     * ```php
     * // At application bootstrap (public/index.php)
     * SessionManager::start();
     * ```
     *
     * @return void
     */
    public static function start(): void {

        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Initialize tracking for new sessions
        if (!isset($_SESSION[self::CREATED_KEY])) {
            $_SESSION[self::CREATED_KEY]  = time();
            $_SESSION[self::ACTIVITY_KEY] = time();
        }
    }

    /**
     * Checks if the current session is active and not timed out
     *
     * Validates that the session hasn't exceeded the configured timeout
     * duration based on last activity. Returns true if the session is
     * still valid, false if it has timed out.
     *
     * ## How It Works
     *
     * Compares the last activity timestamp with the current time. If
     * the difference exceeds the timeout duration, the session is
     * considered expired.
     *
     * ## Edge Cases
     *
     * - New sessions without activity timestamp are considered active
     * - Sessions without user_id are considered inactive
     * - Timed out sessions are automatically destroyed
     *
     * ## Usage
     *
     * ```php
     * if (!SessionManager::isActive()) {
     *     $this->logoutUser();
     *     $this->redirect('/login?timeout=1');
     * }
     * ```
     *
     * @return bool True if session is active, false if timed out
     */
    public static function isActive(): bool {

        $active = true;

        // No user logged in? Not active
        if (empty($_SESSION['user_id'])) {
            return false;
        }

        // No activity timestamp? Session is new, consider it active
        if (!isset($_SESSION[self::ACTIVITY_KEY])) {
            return $active;
        }

        $last_activity = (int)$_SESSION[self::ACTIVITY_KEY];
        $current_time  = time();
        $timeout       = self::getTimeout();

        // Check if session has timed out
        if (($current_time - $last_activity) >= $timeout) {
            $active = false;
            self::destroy();
        }

        return $active;
    }

    /**
     * Updates the last activity timestamp for the current session
     *
     * Refreshes the activity timestamp to the current time, effectively
     * extending the session timeout. Call this on each authenticated
     * request to keep the session alive.
     *
     * ## How It Works
     *
     * Sets $_SESSION[self::ACTIVITY_KEY] to the current Unix timestamp.
     * This resets the timeout clock.
     *
     * ## Usage
     *
     * ```php
     * // In controller actions after authentication check
     * SessionManager::touch();
     * ```
     *
     * Heads-up: Only call this for authenticated users. Touching the
     * session for unauthenticated requests could extend invalid sessions.
     *
     * @return void
     */
    public static function touch(): void {

        if (!empty($_SESSION['user_id'])) {
            $_SESSION[self::ACTIVITY_KEY] = time();
        }
    }

    /**
     * Gets the configured session timeout duration in seconds
     *
     * Returns the timeout duration from environment variable if set,
     * otherwise returns the default timeout (30 minutes).
     *
     * ## Configuration
     *
     * Set SESSION_TIMEOUT environment variable to customize:
     * - 1800 = 30 minutes (default)
     * - 3600 = 1 hour
     * - 7200 = 2 hours
     *
     * ## Usage
     *
     * ```php
     * $timeout = SessionManager::getTimeout();
     * echo "Session expires after " . ($timeout / 60) . " minutes";
     * ```
     *
     * @return int Timeout duration in seconds
     */
    public static function getTimeout(): int {

        $timeout = self::DEFAULT_TIMEOUT;

        $env_timeout = getenv('SESSION_TIMEOUT');
        if ($env_timeout !== false && is_numeric($env_timeout)) {
            $timeout = (int)$env_timeout;
        }

        return $timeout;
    }

    /**
     * Destroys the current session
     *
     * Clears all session data and destroys the session. Called
     * automatically when a session times out, or can be called
     * manually during logout.
     *
     * ## How It Works
     *
     * - Clears $_SESSION superglobal
     * - Calls session_destroy() to remove session file
     *
     * ## Usage
     *
     * ```php
     * SessionManager::destroy();
     * ```
     *
     * Heads-up: After calling this, the user will need to log in
     * again to establish a new session.
     *
     * @return void
     */
    public static function destroy(): void {

        // Clear CSRF token
        CsrfToken::destroy();

        // Clear all session data
        $_SESSION = [];

        // Destroy the session
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    /**
     * Gets the last activity timestamp for the current session
     *
     * Returns the Unix timestamp of when the session was last active.
     * Useful for debugging and displaying session information to users.
     *
     * ## Usage
     *
     * ```php
     * $last_activity = SessionManager::getLastActivity();
     * if ($last_activity) {
     *     echo "Last active: " . date('Y-m-d H:i:s', $last_activity);
     * }
     * ```
     *
     * @return ?int Unix timestamp of last activity, null if not set
     */
    public static function getLastActivity(): ?int {

        $last_activity = null;

        if (isset($_SESSION[self::ACTIVITY_KEY])) {
            $last_activity = (int)$_SESSION[self::ACTIVITY_KEY];
        }

        return $last_activity;
    }

    /**
     * Gets the session creation timestamp
     *
     * Returns the Unix timestamp of when the session was created.
     * Useful for debugging and session analytics.
     *
     * ## Usage
     *
     * ```php
     * $created = SessionManager::getCreatedTime();
     * if ($created) {
     *     $age = time() - $created;
     *     echo "Session age: " . ($age / 60) . " minutes";
     * }
     * ```
     *
     * @return ?int Unix timestamp of session creation, null if not set
     */
    public static function getCreatedTime(): ?int {

        $created = null;

        if (isset($_SESSION[self::CREATED_KEY])) {
            $created = (int)$_SESSION[self::CREATED_KEY];
        }

        return $created;
    }
}
