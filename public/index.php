<?php

/**
 * Phlag Application Entry Point
 *
 * This file serves as the main entry point for both the Phlag API and
 * web administration interface. It bootstraps the application, configures
 * routing using PageMill Router, and dispatches requests to the appropriate
 * handlers (API actions or web controllers).
 *
 * ## Architecture
 *
 * The router integrates four key components:
 * - **PageMill Router**: Matches incoming HTTP requests to routes
 * - **DataMapper API**: Provides RESTful CRUD operations for data objects
 * - **Phlag Repository**: Auto-registers Phlag, PhlagApiKey, and PhlagUser mappers
 * - **Web Controllers**: Render Twig templates for the admin interface
 *
 * ## Authentication Model
 *
 * The application uses two distinct authentication mechanisms:
 *
 * ### Session-Based Authentication (Internal Admin API)
 *
 * All `/api/*` CRUD endpoints require session authentication:
 * - User must be logged in via the web UI (`/login`)
 * - Session is validated by checking `$_SESSION['user_id']`
 * - Returns 401 Unauthorized if no valid session exists
 * - JavaScript from authenticated pages automatically sends session cookies
 *
 * **Why session auth?** These endpoints serve the web UI's JavaScript code
 * for administrative operations. Session cookies are sent automatically by
 * the browser, providing seamless integration without requiring users to
 * manage API keys for internal operations.
 *
 * ### Bearer Token Authentication (External Flag API)
 *
 * Flag state endpoints use API key Bearer tokens:
 * - `/flag/{name}` - Get single flag value
 * - `/all-flags` - Get all flag values as key-value object
 * - `/get-flags` - Get all flags with complete metadata
 *
 * These endpoints require `Authorization: Bearer <api_key>` header and are
 * designed for external application access to retrieve flag values.
 *
 * ## Available Endpoints
 *
 * ### API Routes (Session Authentication Required)
 *
 * All `/api/*` endpoints require trailing slashes and active user session:
 *
 * **Phlag Endpoints:**
 * - `GET /api/Phlag/` - List all phlags
 * - `GET /api/Phlag/{id}/` - Get a specific phlag
 * - `POST /api/Phlag/_search/` - Search phlags with criteria
 * - `POST /api/Phlag/` - Create a new phlag
 * - `PUT /api/Phlag/{id}/` - Update an existing phlag
 * - `DELETE /api/Phlag/{id}/` - Delete a phlag
 *
 * **PhlagApiKey Endpoints:**
 * - `GET /api/PhlagApiKey/` - List all API keys
 * - `GET /api/PhlagApiKey/{id}/` - Get a specific API key
 * - `POST /api/PhlagApiKey/` - Create API key (auto-generates 64-char key)
 * - `PUT /api/PhlagApiKey/{id}/` - Update API key (description only)
 * - `DELETE /api/PhlagApiKey/{id}/` - Delete an API key
 *
 * **PhlagUser Endpoints:**
 * - `GET /api/PhlagUser/` - List all users
 * - `GET /api/PhlagUser/{id}/` - Get a specific user
 * - `POST /api/PhlagUser/` - Create a new user
 * - `PUT /api/PhlagUser/{id}/` - Update an existing user
 * - `DELETE /api/PhlagUser/{id}/` - Delete a user
 *
 * **PhlagEnvironment Endpoints:**
 * - `GET /api/PhlagEnvironment/` - List all environments
 * - `GET /api/PhlagEnvironment/{id}/` - Get a specific environment
 * - `POST /api/PhlagEnvironment/` - Create a new environment
 * - `PUT /api/PhlagEnvironment/{id}/` - Update an existing environment
 * - `DELETE /api/PhlagEnvironment/{id}/` - Delete an environment
 *
 * ### Flag State Endpoints (Bearer Token Authentication)
 *
 * These endpoints are public but require API key authentication.
 * All endpoints now REQUIRE an environment parameter (v2.0 breaking change):
 *
 * - `GET /flag/{environment}/{name}` - Get current value of a specific flag in an environment
 * - `GET /all-flags/{environment}` - Get all flag values as object for an environment
 * - `GET /get-flags/{environment}` - Get all flags with metadata for an environment
 *
 * ## Breaking Change (v2.0)
 *
 * The old endpoints without environment parameters have been removed:
 * - ❌ `/flag/{name}` (use `/flag/{environment}/{name}` instead)
 * - ❌ `/all-flags` (use `/all-flags/{environment}` instead)
 * - ❌ `/get-flags` (use `/get-flags/{environment}` instead)
 *
 * ### Web UI Routes (Session Authentication Required)
 *
 * All web routes except `/login`, `/logout`, and `/first-user` require authentication:
 * - `GET /` - Dashboard home page
 * - `GET /flags` - List phlags (web UI)
 * - `GET /flags/create` - Create phlag form
 * - `GET /flags/{id}` - View phlag details
 * - `GET /flags/{id}/edit` - Edit phlag form
 * - `GET /api-keys` - List API keys (web UI)
 * - `GET /api-keys/create` - Create API key form
 * - `GET /api-keys/{id}` - View API key details
 * - `GET /api-keys/{id}/edit` - Edit API key form
 * - `GET /users` - List users (web UI)
 * - `GET /users/create` - Create user form
 * - `GET /users/{id}` - View user details
 * - `GET /users/{id}/edit` - Edit user form
 * - `GET /environments` - List environments (web UI)
 * - `GET /environments/create` - Create environment form
 * - `GET /environments/{id}` - View environment details
 * - `GET /environments/{id}/edit` - Edit environment form
 *
 * ### Public Routes (No Authentication)
 *
 * - `GET /login` - Login form
 * - `POST /login` - Process login
 * - `GET /logout` - Logout and destroy session
 * - `GET /first-user` - First user creation form (only shown if no users exist)
 * - `POST /first-user` - Create first user account
 * - `GET /assets/*` - Static files (CSS, JS, images)
 *
 * ## Security Features
 *
 * - **CSRF Protection**: Login and first-user forms use CSRF tokens
 * - **Session Security**: Session IDs regenerated on login, destroyed on logout
 * - **Password Security**: Bcrypt hashing with auto-detection of existing hashes
 * - **API Authentication**: Session validation for admin API, Bearer tokens for flag API
 * - **XSS Prevention**: HTML escaping in Twig templates
 *
 * ## Error Handling
 *
 * API requests receive JSON error responses with appropriate HTTP status codes:
 * - 200: Success
 * - 400: Bad Request (validation errors)
 * - 401: Unauthorized (authentication required)
 * - 404: Not Found
 * - 500: Internal Server Error
 *
 * Web requests receive rendered error pages via BaseController::renderError().
 *
 * ## Heads-up: Authentication Flow
 *
 * The session authentication check happens early in the routing process,
 * before the DataMapper API executes actions. This means unauthorized
 * requests never reach the business logic, improving security and
 * reducing attack surface.
 *
 * @package Moonspot\Phlag
 */

// Composer autoloader
use DealNews\DataMapperAPI\API;
// Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Configure error handling for production
 *
 * Display errors are disabled to prevent leaking sensitive information.
 * All errors should be logged via error_log() or a logging framework.
 */
ini_set('display_errors', '0');
error_reporting(E_ALL);

/**
 * Main application execution
 *
 * Wrapped in try/catch to handle any unexpected errors gracefully
 * and return proper responses (JSON for API, HTML for web).
 */
try {

    // Start session with timeout tracking
    \Moonspot\Phlag\Web\Security\SessionManager::start();

    /**
     * Initialize the Phlag Repository singleton
     *
     * This auto-registers the following mappers with the repository:
     * - Phlag - Feature flag data mapper
     * - PhlagApiKey - API key data mapper (with auto-generation)
     * - PhlagUser - User data mapper (with password hashing)
     */
    $repository = \Moonspot\Phlag\Data\Repository::init();

    /**
     * Create DataMapper API instance
     *
     * The DataMapper API provides automatic RESTful CRUD operations
     * for registered data mappers. It generates route definitions that
     * are compatible with PageMill Router.
     */
    $api = new API();

    /**
     * Get all API routes with /api prefix
     *
     * This returns route definitions for all registered mappers:
     * - GET /api/Phlag/ - List all phlags
     * - GET /api/Phlag/{id}/ - Get single phlag
     * - POST /api/Phlag/ - Create phlag
     * - PUT /api/Phlag/{id}/ - Update phlag
     * - DELETE /api/Phlag/{id}/ - Delete phlag
     * - POST /api/Phlag/_search/ - Search phlags
     *
     * Same patterns apply to PhlagApiKey, PhlagUser, and PhlagEnvironment endpoints.
     *
     * Heads-up: All API endpoints require session authentication.
     * The authentication check happens later in the routing process
     * before executing the DataMapper API actions.
     */
    $config = \DealNews\GetConfig\GetConfig::init();
    $base_url_path = $config->get('phlag.base_url_path') ?? '';
    
    // Determine API route prefix (includes base URL path if configured)
    $api_prefix = $base_url_path . '/api';
    $api_routes = $api->getAllRoutes($api_prefix);

    /**
     * Define web interface routes
     *
     * These routes render Twig templates for the admin interface.
     * Most routes require authentication (checked in controllers),
     * except for login, logout, and first-user creation.
     */
    $web_routes = [
        // Authentication routes (public)
        [
            'type'    => 'exact',
            'pattern' => '/login',
            'action'  => 'login',
        ],
        [
            'type'    => 'exact',
            'pattern' => '/logout',
            'action'  => 'logout',
        ],
        [
            'type'    => 'exact',
            'pattern' => '/first-user',
            'action'  => 'first_user',
        ],
        [
            'type'    => 'exact',
            'pattern' => '/forgot-password',
            'action'  => 'forgot_password',
        ],
        [
            'type'    => 'exact',
            'pattern' => '/reset-password',
            'action'  => 'reset_password',
        ],

        // Dashboard (protected)
        [
            'type'    => 'exact',
            'pattern' => '/',
            'action'  => 'home',
        ],

        // Phlag routes
        [
            'type'    => 'exact',
            'pattern' => '/flags',
            'action'  => 'phlag_list',
        ],
        [
            'type'    => 'exact',
            'pattern' => '/flags/create',
            'action'  => 'phlag_create',
        ],
        [
            'type'    => 'regex',
            'pattern' => '!^/flags/(\d+)/edit$!',
            'action'  => 'phlag_edit',
            'tokens'  => ['id'],
        ],
        [
            'type'    => 'regex',
            'pattern' => '!^/flags/(\d+)$!',
            'action'  => 'phlag_view',
            'tokens'  => ['id'],
        ],

        // API Key routes
        [
            'type'    => 'exact',
            'pattern' => '/api-keys',
            'action'  => 'api_key_list',
        ],
        [
            'type'    => 'exact',
            'pattern' => '/api-keys/create',
            'action'  => 'api_key_create',
        ],
        [
            'type'    => 'regex',
            'pattern' => '!^/api-keys/(\d+)/edit$!',
            'action'  => 'api_key_edit',
            'tokens'  => ['id'],
        ],
        [
            'type'    => 'regex',
            'pattern' => '!^/api-keys/(\d+)$!',
            'action'  => 'api_key_view',
            'tokens'  => ['id'],
        ],

        // User routes
        [
            'type'    => 'exact',
            'pattern' => '/users',
            'action'  => 'user_list',
        ],
        [
            'type'    => 'exact',
            'pattern' => '/users/create',
            'action'  => 'user_create',
        ],
        [
            'type'    => 'regex',
            'pattern' => '!^/users/(\d+)/edit$!',
            'action'  => 'user_edit',
            'tokens'  => ['id'],
        ],
        [
            'type'    => 'regex',
            'pattern' => '!^/users/(\d+)$!',
            'action'  => 'user_view',
            'tokens'  => ['id'],
        ],

        // Environment routes
        [
            'type'    => 'exact',
            'pattern' => '/environments',
            'action'  => 'environment_list',
        ],
        [
            'type'    => 'exact',
            'pattern' => '/environments/create',
            'action'  => 'environment_create',
        ],
        [
            'type'    => 'regex',
            'pattern' => '!^/environments/(\d+)/edit$!',
            'action'  => 'environment_edit',
            'tokens'  => ['id'],
        ],
        [
            'type'    => 'regex',
            'pattern' => '!^/environments/(\d+)$!',
            'action'  => 'environment_view',
            'tokens'  => ['id'],
        ],

        // Flag state endpoints (v2.0 - environment required)
        // All flags endpoint (must come before single flag endpoint)
        [
            'type'    => 'regex',
            'pattern' => '!^/all-flags/([a-zA-Z0-9_-]+)/?$!',
            'action'  => 'all_flags',
            'tokens'  => ['environment'],
        ],

        // Get flags with details endpoint (must come before single flag endpoint)
        [
            'type'    => 'regex',
            'pattern' => '!^/get-flags/([a-zA-Z0-9_-]+)/?$!',
            'action'  => 'get_flags',
            'tokens'  => ['environment'],
        ],

        // Single flag state endpoint (by environment and name)
        [
            'type'    => 'regex',
            'pattern' => '!^/flag/([a-zA-Z0-9_-]+)/([a-zA-Z0-9_-]+)/?$!',
            'action'  => 'phlag_state',
            'tokens'  => ['environment', 'name'],
        ],

        // Default 404
        [
            'type'    => 'default',
            'action'  => 'not_found',
        ],
    ];

    /**
     * Prepend base URL path to web routes if configured
     *
     * When Phlag is installed in a subdirectory, all route patterns need
     * to include the base path prefix. This function modifies route patterns
     * to include the configured base URL path.
     *
     * ## How It Works
     *
     * - Exact routes: Prepends base path to pattern
     * - Regex routes: Injects base path after ^ anchor
     * - Starts_with routes: Prepends base path to pattern
     * - Default routes: No modification needed
     *
     * ## Examples
     *
     * With base_url_path = "/phlag":
     * - "/login" becomes "/phlag/login"
     * - "!^/flags/(\d+)$!" becomes "!^/phlag/flags/(\d+)$!"
     * - "/assets/" becomes "/phlag/assets/"
     *
     * @param array  $routes        Array of route definitions
     * @param string $base_url_path Base URL path to prepend
     *
     * @return array Modified route definitions
     */
    $prepend_base_path = function (array $routes, string $base_url_path): array {
        
        if (empty($base_url_path)) {
            return $routes;
        }
        
        foreach ($routes as &$route) {
            if (!isset($route['pattern'])) {
                continue;
            }
            
            $type = $route['type'] ?? '';
            
            if ($type === 'exact' || $type === 'starts_with') {
                // For exact and starts_with routes, simply prepend the base path
                $route['pattern'] = $base_url_path . $route['pattern'];
            } elseif ($type === 'regex') {
                // For regex routes, inject base path after the opening delimiter and ^
                // Pattern format: !^/path/pattern$!
                $pattern = $route['pattern'];
                $delimiter = $pattern[0];
                
                // Check if pattern starts with ^ anchor
                if (isset($pattern[1]) && $pattern[1] === '^') {
                    // Inject base path after ^
                    $route['pattern'] = $delimiter . '^' . $base_url_path . substr($pattern, 2);
                } else {
                    // No anchor, prepend after delimiter
                    $route['pattern'] = $delimiter . $base_url_path . substr($pattern, 1);
                }
            }
            // Default type doesn't need pattern modification
        }
        
        return $routes;
    };
    
    // Apply base path to web routes
    $web_routes = $prepend_base_path($web_routes, $base_url_path);

    /**
     * Combine API routes with web routes
     *
     * API routes are wrapped in an array because PageMill Router expects
     * nested route definitions from the DataMapper API.
     */
    $all_routes = array_merge([$api_routes], $web_routes);

    /**
     * Initialize PageMill Router with all route definitions
     *
     * The router will match incoming requests against these patterns
     * and return the associated action for execution.
     */
    $router = new \PageMill\Router\Router($all_routes);

    /**
     * Get the request URI and normalize it
     *
     * The request URI may include query strings which need to be
     * removed before routing, as the router only matches paths.
     */
    $request_uri = $_SERVER['REQUEST_URI'] ?? '/';

    /**
     * Remove query string from request URI for routing
     *
     * Query strings start with '?' and should not be part of the
     * route matching process. They are available via $_GET.
     */
    $request_path = $request_uri;
    if (($query_pos = strpos($request_uri, '?')) !== false) {
        $request_path = substr($request_uri, 0, $query_pos);
    }

    /**
     * Match the request path to a route
     *
     * Returns an array with 'action' key and optional 'tokens' for
     * route parameters (e.g., {id} in the route pattern).
     */
    $route = $router->match($request_path);

    /**
     * Determine the base URL for API responses
     *
     * The base URL is used by DataMapper API to generate links in
     * responses (e.g., pagination links, resource URLs).
     *
     * ## Configuration
     *
     * The base URL can be customized using the `phlag.base_url_path`
     * configuration value. If set, this path is appended to the base URL.
     *
     * ## Usage
     *
     * ```php
     * // Default behavior (no base_url_path configured)
     * // Base URL: https://example.com
     *
     * // With base_url_path = "/phlag"
     * // Base URL: https://example.com/phlag
     * ```
     *
     * Heads-up: The base_url_path should include a leading slash but
     * no trailing slash for proper URL construction.
     */
    $base_url = '';
    if (!empty($_SERVER['HTTP_HOST'])) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $base_url = $protocol . '://' . $_SERVER['HTTP_HOST'];
        
        // Append configured base URL path (already retrieved above)
        if (!empty($base_url_path)) {
            $base_url .= $base_url_path;
        }
    }

    /**
     * Execute the matched route
     *
     * Actions are either:
     * - DataMapper API class names (start with \DealNews\DataMapperAPI\Action\)
     * - String identifiers for web controllers (e.g., 'home', 'phlag_list')
     * - String identifiers for custom actions (e.g., 'phlag_state', 'all_flags')
     */
    $action = $route['action'] ?? '';

    /**
     * Check if this is a DataMapper API action
     *
     * DataMapper API actions are identified by their namespace prefix.
     * These handle CRUD operations for Phlag, PhlagApiKey, and PhlagUser.
     *
     * ## Session Authentication Check
     *
     * All API endpoints require session authentication. This check happens
     * here, before the DataMapper API executes any business logic.
     *
     * ### Why session authentication?
     *
     * API endpoints serve the web UI's JavaScript code for administrative
     * operations. Session-based auth provides:
     * - Seamless integration (browsers send session cookies automatically)
     * - No need for users to manage API keys for internal operations
     * - Clear separation: session auth for admin, Bearer tokens for flag retrieval
     *
     * ### Security implications
     *
     * - Unauthenticated requests never reach business logic
     * - Returns 401 immediately if no valid session exists
     * - JavaScript AJAX calls work seamlessly (cookies sent automatically)
     * - External API access blocked (no session cookie)
     *
     * ### Error response format
     *
     * Returns JSON with:
     * - HTTP Status: 401 Unauthorized
     * - error: "Unauthorized"
     * - message: "Authentication required. Please log in."
     *
     * Heads-up: This authentication check does NOT apply to flag state
     * endpoints (/flag/{name}, /all-flags, /get-flags) which use Bearer
     * token authentication instead. Those are handled separately below.
     */
    if (strpos($action, '\\DealNews\\DataMapperAPI\\Action\\') === 0) {

        /**
         * API endpoints require authentication
         *
         * Validates that a user session exists by checking for user_id
         * in the session. If no session exists, returns 401 Unauthorized.
         *
         * This prevents unauthorized access to CRUD operations while
         * allowing the web UI to function seamlessly (session cookies
         * are sent automatically by the browser).
         */
        if (empty($_SESSION['user_id'])) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode([
                'error'   => 'Unauthorized',
                'message' => 'Authentication required. Please log in.',
            ], JSON_PRETTY_PRINT);
            exit;
        }

        /**
         * Set content type for API responses
         *
         * All API responses are JSON, so we set the header early.
         */
        header('Content-Type: application/json');

        /**
         * Execute DataMapper API action
         *
         * The DataMapper API handles:
         * - Parsing request method (GET, POST, PUT, DELETE)
         * - Extracting JSON request body
         * - Validating input data
         * - Executing the appropriate CRUD operation
         * - Formatting response as JSON
         * - Setting appropriate HTTP status codes
         *
         * @param string $action Fully qualified action class name
         * @param array $tokens Route parameters (e.g., ['id' => '123'])
         * @param string $base_url Base URL for generating resource links
         * @param Repository $repository Repository instance with registered mappers
         */
        $api->executeAction(
            $action,
            $route['tokens'] ?? [],
            $base_url,
            $repository
        );

    } else {

        // Handle web interface routes
        switch ($action) {

            // Authentication routes (public - no login required)
            case 'login':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $controller = new \Moonspot\Phlag\Web\Controller\AuthController();
                    $controller->authenticate();
                } else {
                    $controller = new \Moonspot\Phlag\Web\Controller\AuthController();
                    $controller->login();
                }
                break;

            case 'logout':
                $controller = new \Moonspot\Phlag\Web\Controller\AuthController();
                $controller->logout();
                break;

            case 'first_user':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $controller = new \Moonspot\Phlag\Web\Controller\AuthController();
                    $controller->createFirstUser();
                } else {
                    $controller = new \Moonspot\Phlag\Web\Controller\AuthController();
                    $controller->firstUser();
                }
                break;

            case 'forgot_password':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $controller = new \Moonspot\Phlag\Web\Controller\AuthController();
                    $controller->sendResetToken();
                } else {
                    $controller = new \Moonspot\Phlag\Web\Controller\AuthController();
                    $controller->forgotPassword();
                }
                break;

            case 'reset_password':
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $controller = new \Moonspot\Phlag\Web\Controller\AuthController();
                    $controller->updatePassword();
                } else {
                    $controller = new \Moonspot\Phlag\Web\Controller\AuthController();
                    $controller->resetPassword();
                }
                break;

            /**
             * Protected routes (login required)
             *
             * All routes below require authentication. Controllers check
             * authentication by calling $this->requireLogin() which redirects
             * to /login if no valid session exists.
             */

            case 'home':
                $controller = new \Moonspot\Phlag\Web\Controller\HomeController();
                $controller->index();
                break;

            case 'phlag_list':
                $controller = new \Moonspot\Phlag\Web\Controller\PhlagController();
                $controller->list();
                break;

            case 'phlag_create':
                $controller = new \Moonspot\Phlag\Web\Controller\PhlagController();
                $controller->create();
                break;

            case 'phlag_edit':
                $controller = new \Moonspot\Phlag\Web\Controller\PhlagController();
                $phlag_id = (int)($route['tokens']['id'] ?? 0);
                $controller->edit($phlag_id);
                break;

            case 'phlag_view':
                $controller = new \Moonspot\Phlag\Web\Controller\PhlagController();
                $phlag_id = (int)($route['tokens']['id'] ?? 0);
                $controller->view($phlag_id);
                break;

            case 'api_key_list':
                $controller = new \Moonspot\Phlag\Web\Controller\PhlagApiKeyController();
                $controller->list();
                break;

            case 'api_key_create':
                $controller = new \Moonspot\Phlag\Web\Controller\PhlagApiKeyController();
                $controller->create();
                break;

            case 'api_key_edit':
                $controller = new \Moonspot\Phlag\Web\Controller\PhlagApiKeyController();
                $api_key_id = (int)($route['tokens']['id'] ?? 0);
                $controller->edit($api_key_id);
                break;

            case 'api_key_view':
                $controller = new \Moonspot\Phlag\Web\Controller\PhlagApiKeyController();
                $api_key_id = (int)($route['tokens']['id'] ?? 0);
                $controller->view($api_key_id);
                break;

            case 'user_list':
                $controller = new \Moonspot\Phlag\Web\Controller\PhlagUserController();
                $controller->list();
                break;

            case 'user_create':
                $controller = new \Moonspot\Phlag\Web\Controller\PhlagUserController();
                $controller->create();
                break;

            case 'user_edit':
                $controller = new \Moonspot\Phlag\Web\Controller\PhlagUserController();
                $user_id = (int)($route['tokens']['id'] ?? 0);
                $controller->edit($user_id);
                break;

            case 'user_view':
                $controller = new \Moonspot\Phlag\Web\Controller\PhlagUserController();
                $user_id = (int)($route['tokens']['id'] ?? 0);
                $controller->view($user_id);
                break;

            case 'environment_list':
                $controller = new \Moonspot\Phlag\Web\Controller\PhlagEnvironmentController();
                $controller->list();
                break;

            case 'environment_create':
                $controller = new \Moonspot\Phlag\Web\Controller\PhlagEnvironmentController();
                $controller->create();
                break;

            case 'environment_edit':
                $controller = new \Moonspot\Phlag\Web\Controller\PhlagEnvironmentController();
                $environment_id = (int)($route['tokens']['id'] ?? 0);
                $controller->edit($environment_id);
                break;

            case 'environment_view':
                $controller = new \Moonspot\Phlag\Web\Controller\PhlagEnvironmentController();
                $environment_id = (int)($route['tokens']['id'] ?? 0);
                $controller->view($environment_id);
                break;

            /**
             * Custom flag state endpoints (Bearer Token Authentication)
             *
             * These endpoints provide external access to flag values for
             * applications that need to check flag states. Unlike the admin
             * API endpoints above, these use Bearer token authentication
             * instead of session cookies.
             *
             * Authentication is handled inside the action classes via
             * ApiKeyAuthTrait, which validates the Authorization header.
             *
             * Returns 401 Unauthorized if:
             * - Authorization header is missing
             * - Header format is invalid (not "Bearer <token>")
             * - API key is not found in database
             */

            case 'phlag_state':
                /**
                 * Get single flag value by environment and name
                 *
                 * Returns the current evaluated value of a flag in a specific
                 * environment based on temporal constraints and type casting.
                 *
                 * ## Breaking Change (v2.0)
                 *
                 * Environment parameter is now REQUIRED. The old /flag/{name}
                 * endpoint no longer exists. Use /flag/{environment}/{name}.
                 *
                 * Response format: Scalar value (not wrapped in object)
                 * - SWITCH: boolean (true/false)
                 * - INTEGER: integer (e.g., 100)
                 * - FLOAT: float (e.g., 3.14)
                 * - STRING: string (e.g., "hello")
                 * - Inactive flags: false (SWITCH) or null (other types)
                 * - Non-existent flags: null
                 * - Non-existent environment: 404 error
                 */
                header('Content-Type: application/json');
                $action = new \Moonspot\Phlag\Action\GetPhlagState();
                $action->environment = $route['tokens']['environment'] ?? '';
                $action->name = $route['tokens']['name'] ?? '';
                $action(
                    [
                        'environment' => $action->environment,
                        'name'        => $action->name,
                    ],
                    $repository
                );
                break;

            case 'all_flags':
                /**
                 * Get all flags as key-value object for a specific environment
                 *
                 * Returns all flags with their current evaluated values in the
                 * specified environment in a simple object format for efficient lookup.
                 *
                 * ## Breaking Change (v2.0)
                 *
                 * Environment parameter is now REQUIRED. The old /all-flags
                 * endpoint no longer exists. Use /all-flags/{environment}.
                 *
                 * Response format: JSON object
                 * {
                 *   "feature_checkout": true,
                 *   "max_items": 100,
                 *   "price_multiplier": 1.5,
                 *   "welcome_message": "Hello"
                 * }
                 *
                 * Use cases:
                 * - Application initialization per environment
                 * - Bulk flag value lookup for specific deployment
                 * - Client-side flag caching by environment
                 */
                header('Content-Type: application/json');
                $action = new \Moonspot\Phlag\Action\GetAllFlags();
                $action->environment = $route['tokens']['environment'] ?? '';
                $action(
                    ['environment' => $action->environment],
                    $repository
                );
                break;

            case 'get_flags':
                /**
                 * Get all flags with complete metadata for a specific environment
                 *
                 * Returns all flags with their environment-specific values plus
                 * additional information like type, start_datetime, end_datetime.
                 *
                 * ## Breaking Change (v2.0)
                 *
                 * Environment parameter is now REQUIRED. The old /get-flags
                 * endpoint no longer exists. Use /get-flags/{environment}.
                 *
                 * Response format: JSON array of objects
                 * [
                 *   {
                 *     "name": "feature_checkout",
                 *     "type": "SWITCH",
                 *     "value": true,
                 *     "start_datetime": null,
                 *     "end_datetime": "2025-12-31T23:59:59-05:00"
                 *   }
                 * ]
                 *
                 * Use cases:
                 * - Administration and monitoring per environment
                 * - Debugging flag configurations by environment
                 * - Documentation generation
                 * - Full flag inventory inspection per deployment
                 */
                header('Content-Type: application/json');
                $action = new \Moonspot\Phlag\Action\GetFlags();
                $action->environment = $route['tokens']['environment'] ?? '';
                $action(
                    ['environment' => $action->environment],
                    $repository
                );
                break;

            case 'not_found':
            default:
                /**
                 * Handle static files and 404 Not Found errors
                 *
                 * First checks if a static file exists in the public directory.
                 * If found, serves it with the appropriate Content-Type header.
                 * If not found, returns a 404 error (JSON for API, HTML for web).
                 *
                 * ## Static File Handling
                 *
                 * Serves files from the public directory (CSS, JS, images, etc.)
                 * with proper MIME type headers based on file extension.
                 *
                 * ## 404 Error Handling
                 *
                 * API requests are identified by:
                 * - Path starts with /api/
                 * - Accept header includes application/json
                 *
                 * Web requests receive an HTML error page for consistent UX.
                 */
                $file_path = __DIR__ . $request_path;
                
                if (file_exists($file_path) && is_file($file_path)) {
                    /**
                     * Serve static file with appropriate Content-Type
                     *
                     * Determines MIME type based on file extension and serves
                     * the file. Common web asset types are supported.
                     */
                    $extension = pathinfo($file_path, PATHINFO_EXTENSION);
                    $content_types = [
                        'css'  => 'text/css',
                        'js'   => 'application/javascript',
                        'json' => 'application/json',
                        'png'  => 'image/png',
                        'jpg'  => 'image/jpeg',
                        'jpeg' => 'image/jpeg',
                        'gif'  => 'image/gif',
                        'svg'  => 'image/svg+xml',
                    ];
                    $content_type = $content_types[$extension] ?? 'application/octet-stream';
                    header('Content-Type: ' . $content_type);
                    readfile($file_path);
                } else {
                    /**
                     * Return 404 error response
                     *
                     * Detects request type and returns appropriate format.
                     * API requests get JSON, web requests get HTML.
                     */
                    $api_check_path = $base_url_path . '/api/';
                    $is_api_request = strpos($request_path, $api_check_path) === 0 ||
                                      (isset($_SERVER['HTTP_ACCEPT']) &&
                                       strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

                    if ($is_api_request) {
                        /**
                         * Return JSON error for API requests
                         *
                         * Provides structured error information for programmatic
                         * consumption by API clients.
                         */
                        header('Content-Type: application/json');
                        http_response_code(404);
                        echo json_encode([
                            'error'   => 'Not Found',
                            'message' => 'The requested resource does not exist',
                            'path'    => $request_path,
                        ], JSON_PRETTY_PRINT);
                    } else {
                        /**
                         * Return HTML error page for web requests
                         *
                         * Renders a user-friendly error page using the
                         * BaseController's error template.
                         */
                        $controller = new \Moonspot\Phlag\Web\Controller\HomeController();
                        $controller->renderError(404, 'The requested page does not exist');
                    }
                }
                break;
        }
    }

} catch (\RuntimeException $e) {

    /**
     * Handle RuntimeException from DataMapper API
     *
     * The DataMapper API throws RuntimeException for validation errors,
     * not found errors, and other API-specific failures. The exception
     * code contains the HTTP status code to return.
     *
     * Common status codes:
     * - 400: Bad Request (validation failed)
     * - 404: Not Found (resource doesn't exist)
     * - 500: Internal Server Error (unexpected error)
     *
     * Heads-up: Status codes are validated to ensure they're in the
     * valid HTTP range (100-599). Invalid codes default to 500.
     */
    $status_code = $e->getCode();
    if ($status_code < 100 || $status_code >= 600) {
        $status_code = 500;
    }

    header('Content-Type: application/json');
    http_response_code($status_code);
    echo json_encode([
        'error'   => 'Request Failed',
        'message' => $e->getMessage(),
    ], JSON_PRETTY_PRINT);

    /**
     * Log the error for debugging
     *
     * All API errors are logged with full context including
     * status code, message, file, and line number.
     */
    error_log(sprintf(
        'Phlag API Error [%d]: %s in %s:%d',
        $e->getCode(),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
    ));

} catch (\Throwable $e) {

    /**
     * Handle all other unexpected errors
     *
     * This catch block handles any errors not caught by the RuntimeException
     * handler above, including:
     * - PHP errors (Error class in PHP 7+)
     * - Uncaught exceptions from any source
     * - Fatal errors
     * - Type errors
     *
     * ## Why catch Throwable?
     *
     * In PHP 7+, not all errors are Exceptions. The Throwable interface
     * covers both Exception and Error classes, ensuring we catch everything.
     *
     * ## Error Response
     *
     * Returns a generic error message to avoid leaking sensitive information
     * (like stack traces or database details) to API consumers.
     *
     * ## Logging
     *
     * Full error details including stack trace are logged via error_log()
     * for debugging by developers while keeping the API response clean.
     *
     * Heads-up: In production, ensure error_log is configured to write to
     * a file that's monitored. Never display detailed errors to end users.
     */
    http_response_code(500);
    echo json_encode([
        'error'   => 'Internal Server Error',
        'message' => 'An unexpected error occurred',
    ], JSON_PRETTY_PRINT);

    /**
     * Log the full error details for debugging
     *
     * Logs include:
     * - Error message
     * - File and line number where error occurred
     * - Full stack trace
     *
     * This information is critical for debugging but should never
     * be exposed to API consumers.
     */
    error_log(sprintf(
        'Phlag Fatal Error: %s in %s:%d',
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
    ));
    error_log($e->getTraceAsString());
}
