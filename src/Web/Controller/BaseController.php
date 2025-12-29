<?php

namespace Moonspot\Phlag\Web\Controller;

use DealNews\GetConfig\GetConfig;
use Moonspot\Phlag\Web\Security\CsrfToken;
use Moonspot\Phlag\Web\Security\SessionManager;

/**
 * Base Web Controller
 *
 * Provides shared functionality for all Phlag web controllers including
 * Twig environment initialization, template rendering, and common error
 * handling. All web controllers should extend this base class to ensure
 * consistent behavior across the application.
 *
 * ## Responsibilities
 *
 * - Initialize and configure Twig template engine
 * - Render templates with common context variables
 * - Handle error responses with proper HTTP status codes
 * - Provide shared utilities for child controllers
 *
 * ## Usage
 *
 * Child controllers should extend this class and implement their action methods:
 *
 * ```php
 * class PhlagController extends BaseController {
 *     public function list(): void {
 *         $this->render('phlag/list.html.twig', [
 *             'title' => 'Manage Flags'
 *         ]);
 *     }
 * }
 * ```
 *
 * @package Moonspot\Phlag\Web\Controller
 */
abstract class BaseController {

    /**
     * Twig template environment
     *
     * @var \Twig\Environment
     */
    protected \Twig\Environment $twig;

    /**
     * Base URL for the application
     *
     * Used in templates for generating absolute URLs and API endpoints.
     *
     * @var string
     */
    protected string $base_url;

    /**
     * API base URL for making AJAX requests
     *
     * @var string
     */
    protected string $api_url;

    /**
     * Template directory path
     *
     * @var string
     */
    protected string $template_dir;

    /**
     * Creates the base controller and initializes Twig
     *
     * Sets up the Twig environment with the template directory,
     * configures caching, and determines base URLs for the application.
     */
    public function __construct() {

        // Determine template directory path
        $this->template_dir = dirname(__DIR__) . '/templates';

        // Initialize Twig loader with template directory
        $loader = new \Twig\Loader\FilesystemLoader($this->template_dir);

        // Configure Twig environment
        // Cache is disabled in development; enable in production
        $this->twig = new \Twig\Environment($loader, [
            'cache' => false, // Set to sys_get_temp_dir() . '/twig_cache' in production
            'debug' => true,  // Set to false in production
            'strict_variables' => true,
        ]);

        // Add debug extension for development
        $this->twig->addExtension(new \Twig\Extension\DebugExtension());

        // Determine base URL from request
        $this->base_url = $this->determineBaseUrl();
        $this->api_url = $this->base_url . '/api';
    }

    /**
     * Renders a Twig template and outputs the result
     *
     * Merges the provided context with common template variables
     * (base_url, api_url, is_logged_in, current_username) and renders
     * the specified template. Output is sent directly to the browser.
     *
     * ## Usage
     *
     * ```php
     * $this->render('phlag/list.html.twig', [
     *     'title' => 'Flags',
     *     'items' => []
     * ]);
     * ```
     *
     * @param string $template Template filename relative to template directory
     * @param array  $context  Variables to pass to the template
     *
     * @return void
     */
    protected function render(string $template, array $context = []): void {

        // Merge context with common variables available to all templates
        $default_context = [
            'base_url'         => $this->base_url,
            'api_url'          => $this->api_url,
            'is_logged_in'     => $this->isLoggedIn(),
            'current_username' => $this->getCurrentUsername(),
            'csrf_token'       => CsrfToken::get(),
        ];

        $merged_context = array_merge($default_context, $context);

        // Render template and output
        echo $this->twig->render($template, $merged_context);
    }

    /**
     * Renders an error page with appropriate HTTP status code
     *
     * Displays a user-friendly error message and sets the proper
     * HTTP response code. Used for handling 404s, 500s, and other
     * error conditions.
     *
     * ## Usage
     *
     * ```php
     * $this->renderError(404, 'Phlag not found');
     * ```
     *
     * @param int    $code    HTTP status code (e.g., 404, 500)
     * @param string $message Error message to display to user
     *
     * @return void
     */
    public function renderError(int $code, string $message): void {

        // Set HTTP response code
        http_response_code($code);

        // Render error template if it exists, otherwise render inline
        $template_file = 'error.html.twig';
        $template_path = $this->template_dir . '/' . $template_file;

        if (file_exists($template_path)) {
            $this->render($template_file, [
                'error_code'    => $code,
                'error_message' => $message,
            ]);
        } else {
            // Fallback error display if template doesn't exist
            $this->renderInlineError($code, $message);
        }
    }

    /**
     * Renders a simple inline error when template is unavailable
     *
     * Provides basic HTML error output as a fallback when the
     * error template file cannot be loaded.
     *
     * @param int    $code    HTTP status code
     * @param string $message Error message
     *
     * @return void
     */
    protected function renderInlineError(int $code, string $message): void {

        echo '<!DOCTYPE html>';
        echo '<html lang="en">';
        echo '<head>';
        echo '<meta charset="UTF-8">';
        echo '<title>Error ' . htmlspecialchars((string)$code) . '</title>';
        echo '<style>body { font-family: sans-serif; margin: 40px; }</style>';
        echo '</head>';
        echo '<body>';
        echo '<h1>Error ' . htmlspecialchars((string)$code) . '</h1>';
        echo '<p>' . htmlspecialchars($message) . '</p>';
        echo '<p><a href="' . htmlspecialchars($this->base_url) . '">Return Home</a></p>';
        echo '</body>';
        echo '</html>';
    }

    /**
     * Determines the base URL from the current request
     *
     * Detects whether the request is over HTTP or HTTPS and
     * constructs the base URL using the Host header. Falls back
     * to localhost if no Host header is present.
     *
     * ## Configuration
     *
     * The base URL can be customized using the `phlag.base_url_path`
     * configuration value. If set, this path is appended to the base URL.
     * This is useful when Phlag is installed in a subdirectory.
     *
     * ## Usage
     *
     * ```php
     * // Default behavior (no base_url_path configured)
     * $url = $this->determineBaseUrl();
     * // Returns: "https://example.com"
     *
     * // With base_url_path = "/phlag"
     * $url = $this->determineBaseUrl();
     * // Returns: "https://example.com/phlag"
     * ```
     *
     * Heads-up: The base_url_path should include a leading slash but
     * no trailing slash for proper URL construction.
     *
     * @return string Base URL (e.g., "https://example.com" or "https://example.com/phlag")
     */
    protected function determineBaseUrl(): string {

        $base_url = '';

        if (!empty($_SERVER['HTTP_HOST'])) {
            if(getenv('FORCE_HTTPS')) {
                $protocol = 'https';
            } else {
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            }
            $base_url = $protocol . '://' . $_SERVER['HTTP_HOST'];
        } else {
            // Fallback for CLI or testing environments
            $base_url = 'http://localhost';
        }

        // Append configured base URL path if present
        $config =   GetConfig::init();
        $base_url_path = $config->get('phlag.base_url_path');
        if (!empty($base_url_path)) {
            $base_url .= $base_url_path;
        }

        return $base_url;
    }

    /**
     * Redirects to a different URL
     *
     * Sends a Location header and exits. Use this for post-action
     * redirects (e.g., after form submission).
     *
     * ## POST-Redirect-GET Pattern
     *
     * Uses HTTP 303 (See Other) status code to ensure browsers always
     * make a GET request after a redirect, preventing form resubmission
     * and infinite POST loops. This is critical for proper handling of
     * failed login attempts and other form validation errors.
     *
     * ## Usage
     *
     * ```php
     * $this->redirect('/flags');
     * ```
     *
     * @param string $url URL to redirect to (relative or absolute)
     *
     * @return void
     */
    protected function redirect(string $url): void {

        // Make relative URLs absolute
        if (strpos($url, 'http') !== 0) {
            $url = $this->base_url . $url;
        }

        http_response_code(303);
        header('Location: ' . $url);
        exit;
    }

    /**
     * Checks if a user is currently logged in
     *
     * Verifies that the session contains a valid user_id and that the
     * session hasn't timed out due to inactivity. Sessions are managed
     * via SessionManager which tracks activity and enforces timeouts.
     *
     * ## How It Works
     *
     * - Checks if $_SESSION['user_id'] is set and non-empty
     * - Validates session hasn't exceeded timeout duration
     * - Returns false if session has timed out
     * - Does not verify user still exists in database
     *
     * ## Session Timeout
     *
     * Sessions expire after 30 minutes of inactivity by default.
     * Configure via SESSION_TIMEOUT environment variable.
     *
     * ## Usage
     *
     * ```php
     * if (!$this->isLoggedIn()) {
     *     $this->redirect('/login');
     * }
     * ```
     *
     * @return bool Returns true if user is logged in and session is active
     */
    protected function isLoggedIn(): bool {

        return SessionManager::isActive();
    }

    /**
     * Requires user to be logged in or redirects to login page
     *
     * Convenience method that checks authentication status and session
     * timeout, then redirects to the login page if the user is not
     * authenticated or the session has expired. Updates session activity
     * timestamp if the session is valid. Use this at the start of
     * protected controller actions.
     *
     * ## Session Timeout Handling
     *
     * If the session has timed out, redirects to login with a timeout
     * parameter that can be used to display a timeout message to the user.
     *
     * ## Usage
     *
     * ```php
     * public function list(): void {
     *     $this->requireLogin();
     *     // Rest of method only executes if logged in
     * }
     * ```
     *
     * @return void
     */
    protected function requireLogin(): void {

        if (!$this->isLoggedIn()) {
            $this->redirect('/login?timeout=1');
        }

        // Update activity timestamp to extend session
        SessionManager::touch();
    }

    /**
     * Gets the currently logged in user's ID
     *
     * Returns the user_id from the session if a user is logged in.
     * Returns null if no user is authenticated.
     *
     * ## Usage
     *
     * ```php
     * $user_id = $this->getCurrentUserId();
     * if ($user_id) {
     *     // Load user data
     * }
     * ```
     *
     * @return ?int User ID if logged in, null otherwise
     */
    protected function getCurrentUserId(): ?int {

        $user_id = null;

        if (!empty($_SESSION['user_id'])) {
            $user_id = (int)$_SESSION['user_id'];
        }

        return $user_id;
    }

    /**
     * Gets the currently logged in user's username
     *
     * Returns the username from the session if a user is logged in.
     * Returns null if no user is authenticated.
     *
     * ## Usage
     *
     * ```php
     * $username = $this->getCurrentUsername();
     * ```
     *
     * @return ?string Username if logged in, null otherwise
     */
    protected function getCurrentUsername(): ?string {

        $username = null;

        if (!empty($_SESSION['username'])) {
            $username = $_SESSION['username'];
        }

        return $username;
    }

    /**
     * Logs in a user by setting session variables
     *
     * Sets the user_id and username in the session to establish
     * an authenticated session and initializes session timeout
     * tracking. This should be called after successful credential
     * verification.
     *
     * ## Usage
     *
     * ```php
     * // After verifying password
     * $this->loginUser($user->phlag_user_id, $user->username);
     * $this->redirect('/');
     * ```
     *
     * ## Security Considerations
     *
     * - Regenerates session ID to prevent session fixation attacks
     * - Initializes activity timestamp for timeout tracking
     * - Stores minimal user data in session (just ID and username)
     * - Does not verify credentials - caller must do that first
     *
     * @param int    $user_id  User's database ID
     * @param string $username User's username
     *
     * @return void
     */
    protected function loginUser(int $user_id, string $username): void {

        // Regenerate session ID to prevent session fixation
        session_regenerate_id(true);

        // Store user data in session
        $_SESSION['user_id']  = $user_id;
        $_SESSION['username'] = $username;

        // Initialize session activity timestamp
        SessionManager::touch();
    }

    /**
     * Logs out the current user
     *
     * Clears all session data and destroys the session via
     * SessionManager, effectively logging out the user. After
     * calling this, redirect to the login page or public area.
     *
     * ## Usage
     *
     * ```php
     * public function logout(): void {
     *     $this->logoutUser();
     *     $this->redirect('/login');
     * }
     * ```
     *
     * @return void
     */
    protected function logoutUser(): void {

        // Destroy session including CSRF token
        SessionManager::destroy();
    }

    /**
     * Validates CSRF token from request data
     *
     * Checks the provided token against the stored session token to
     * protect against CSRF attacks. Returns true if valid, false otherwise.
     * Use this in POST/PUT/DELETE handlers before processing the request.
     *
     * ## Usage
     *
     * ```php
     * if (!$this->validateCsrfToken($_POST['csrf_token'])) {
     *     $this->renderError(403, 'Invalid CSRF token');
     *     return;
     * }
     * ```
     *
     * @param string|null $token CSRF token from form submission
     *
     * @return bool True if token is valid, false otherwise
     */
    protected function validateCsrfToken(?string $token): bool {

        return CsrfToken::validate($token);
    }
}
