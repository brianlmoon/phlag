<?php

namespace Moonspot\Phlag\Web\Controller;

use Moonspot\Phlag\Data\Repository;
use Moonspot\Phlag\Data\PhlagUser;
use Moonspot\Phlag\Data\PasswordResetToken;
use Moonspot\Phlag\Web\Service\EmailService;

/**
 * Authentication Controller
 *
 * Handles user authentication including login, logout, and first-time
 * user setup. Manages session-based authentication for the Phlag web
 * interface.
 *
 * ## Responsibilities
 *
 * - Display login form
 * - Authenticate user credentials
 * - Handle first user creation on fresh installs
 * - Manage logout process
 * - Detect if any users exist in the system
 *
 * ## First User Flow
 *
 * On a fresh Phlag installation with no users, the system automatically
 * redirects to the first user creation form. After the first user is
 * created, the normal login process is used.
 *
 * ## Usage
 *
 * Controllers are invoked by the router in public/index.php:
 *
 * ```php
 * $controller = new AuthController();
 * $controller->login();
 * ```
 *
 * @package Moonspot\Phlag\Web\Controller
 */
class AuthController extends BaseController {

    /**
     * Repository instance for database operations
     *
     * @var Repository
     */
    protected Repository $repository;

    /**
     * Email service for sending transactional emails
     *
     * @var ?EmailService
     */
    protected ?EmailService $email_service = null;

    /**
     * Creates the auth controller and initializes repository
     *
     * Extends the base controller constructor to also initialize
     * the repository for user database operations and the email
     * service for sending transactional emails.
     *
     * Heads-up: Email service initialization may fail if email
     * configuration is missing. This is caught and logged, allowing
     * the application to function without email (showing tokens
     * on screen instead).
     */
    public function __construct() {

        parent::__construct();

        $this->repository = Repository::init();

        // Initialize email service (may fail if not configured)
        try {
            $this->email_service = new EmailService();
        } catch (\Throwable $e) {
            error_log("Email service unavailable: " . $e->getMessage());
            $this->email_service = null;
        }
    }

    /**
     * Displays the login form or redirects to first user setup
     *
     * Checks if any users exist in the system. If no users exist,
     * redirects to the first user creation form. Otherwise, displays
     * the login form.
     *
     * ## Edge Cases
     *
     * - If already logged in, redirects to dashboard
     * - If no users exist, redirects to first user setup
     * - If timeout parameter present, shows session timeout message
     *
     * @return void
     */
    public function login(): void {

        // Already logged in? Redirect to dashboard
        if ($this->isLoggedIn()) {
            $this->redirect('/');
        }

        // No users exist? Redirect to first user setup
        if (!$this->usersExist()) {
            $this->redirect('/first-user');
        }

        // Check for timeout parameter
        $timeout_message = null;
        if (!empty($_GET['timeout'])) {
            $timeout_message = 'Your session has expired due to inactivity. Please log in again.';
        }

        // Show login form
        $this->render('auth/login.html.twig', [
            'title'           => 'Login',
            'error'           => $_SESSION['login_error'] ?? null,
            'timeout_message' => $timeout_message,
        ]);

        // Clear any error message from session
        unset($_SESSION['login_error']);
    }

    /**
     * Processes login form submission and authenticates user
     *
     * Validates submitted credentials against the database. On success,
     * logs the user in and redirects to the dashboard. On failure,
     * stores an error message and redirects back to the login form.
     *
     * ## Security Considerations
     *
     * - Uses password_verify() for secure password checking
     * - Regenerates session ID on successful login
     * - Generic error messages to prevent username enumeration
     *
     * ## Usage
     *
     * This method expects POST data with:
     * - username: User's username
     * - password: User's plaintext password
     *
     * @return void
     */
    public function authenticate(): void {

        // Validate CSRF token
        if (!$this->validateCsrfToken($_POST['csrf_token'] ?? null)) {
            $_SESSION['login_error'] = 'Invalid security token. Please try again.';
            $this->redirect('/login');
        }

        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        // Validate required fields
        if (empty($username) || empty($password)) {
            $_SESSION['login_error'] = 'Username and password are required';
            $this->redirect('/login');
        }

        // Find user by username
        $users = $this->repository->find('PhlagUser', ['username' => $username]);

        if (empty($users)) {
            $_SESSION['login_error'] = 'Invalid username or password';
            $this->redirect('/login');
        }

        $user = reset($users);

        // Verify user object is valid and has a valid ID
        if (
            !$user instanceof PhlagUser || 
            !isset($user->phlag_user_id) ||
            empty($user->phlag_user_id)
        ) {
            $_SESSION['login_error'] = 'Invalid username or password';
            $this->redirect('/login');
        }

        // Verify password
        if (!password_verify($password, $user->password)) {
            $_SESSION['login_error'] = 'Invalid username or password';
            $this->redirect('/login');
        }

        // Login successful - set session and redirect
        $this->loginUser($user->phlag_user_id, $user->username);
        $this->redirect('/');
    }

    /**
     * Logs out the current user and redirects to login
     *
     * Clears all session data and destroys the session, then
     * redirects to the login page.
     *
     * @return void
     */
    public function logout(): void {

        $this->logoutUser();
        $this->redirect('/login');
    }

    /**
     * Displays the first user creation form
     *
     * Shows a form for creating the initial user account on a fresh
     * Phlag installation. If users already exist, redirects to the
     * normal login page instead.
     *
     * ## Edge Cases
     *
     * - If already logged in, redirects to dashboard
     * - If users already exist, redirects to login page
     *
     * @return void
     */
    public function firstUser(): void {

        // Already logged in? Redirect to dashboard
        if ($this->isLoggedIn()) {
            $this->redirect('/');
        }

        // Users already exist? Redirect to login
        if ($this->usersExist()) {
            $this->redirect('/login');
        }

        // Show first user form
        $this->render('auth/first_user.html.twig', [
            'title' => 'Create First User',
            'error' => $_SESSION['first_user_error'] ?? null,
        ]);

        // Clear any error message from session
        unset($_SESSION['first_user_error']);
    }

    /**
     * Processes first user creation form submission
     *
     * Creates the initial user account on a fresh Phlag installation.
     * Validates the input, creates the user record, automatically logs
     * them in, and redirects to the dashboard.
     *
     * ## Security Considerations
     *
     * - Only works if no users exist
     * - Password is automatically hashed by the PhlagUser mapper
     * - Validates password confirmation match
     * - Auto-login after creation
     *
     * ## Usage
     *
     * This method expects POST data with:
     * - username: Desired username
     * - full_name: User's full name
     * - password: Desired password
     * - password_confirm: Password confirmation
     *
     * @return void
     */
    public function createFirstUser(): void {

        // Users already exist? Prevent creation
        if ($this->usersExist()) {
            $_SESSION['first_user_error'] = 'Users already exist. Please log in.';
            $this->redirect('/login');
        }

        // Validate CSRF token
        if (!$this->validateCsrfToken($_POST['csrf_token'] ?? null)) {
            $_SESSION['first_user_error'] = 'Invalid security token. Please try again.';
            $this->redirect('/first-user');
        }

        $username         = $_POST['username'] ?? '';
        $full_name        = $_POST['full_name'] ?? '';
        $email            = $_POST['email'] ?? '';
        $password         = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';

        // Validate required fields
        if (empty($username) || empty($full_name) || empty($email) || empty($password)) {
            $_SESSION['first_user_error'] = 'All fields are required';
            $this->redirect('/first-user');
        }

        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['first_user_error'] = 'Invalid email address';
            $this->redirect('/first-user');
        }

        // Validate password confirmation
        if ($password !== $password_confirm) {
            $_SESSION['first_user_error'] = 'Passwords do not match';
            $this->redirect('/first-user');
        }

        // Validate password length
        if (strlen($password) < 8) {
            $_SESSION['first_user_error'] = 'Password must be at least 8 characters';
            $this->redirect('/first-user');
        }

        // Create the first user
        $user = $this->repository->new('PhlagUser');
        $user->username  = $username;
        $user->full_name = $full_name;
        $user->email     = $email;
        $user->password  = $password; // Will be hashed by mapper

        $saved_user = $this->repository->save('PhlagUser', $user);

        // Auto-login the first user and redirect to dashboard
        $this->loginUser($saved_user->phlag_user_id, $saved_user->username);
        $this->redirect('/');
    }

    /**
     * Checks if any users exist in the database
     *
     * Queries the phlag_users table to determine if any user accounts
     * have been created. Used to detect fresh installations that need
     * the first user setup flow.
     *
     * ## How It Works
     *
     * Performs a find operation with no criteria and checks if any
     * results are returned. This is more efficient than counting.
     *
     * @return bool Returns true if at least one user exists
     */
    protected function usersExist(): bool {

        $users = $this->repository->find('PhlagUser', []);

        return !empty($users);
    }

    /**
     * Displays the password reset request form
     *
     * Shows a form where users can enter their username to request
     * a password reset. A reset token will be generated and the user
     * will be redirected to a confirmation page with the reset URL.
     *
     * Note: In a production environment, this should send an email
     * instead of displaying the token directly.
     *
     * ## Edge Cases
     *
     * - If already logged in, redirects to dashboard
     *
     * @return void
     */
    public function forgotPassword(): void {

        // Already logged in? Redirect to dashboard
        if ($this->isLoggedIn()) {
            $this->redirect('/');
        }

        // Show forgot password form
        $this->render('auth/forgot_password.html.twig', [
            'title' => 'Reset Password',
            'error' => $_SESSION['forgot_password_error'] ?? null,
        ]);

        // Clear any error message from session
        unset($_SESSION['forgot_password_error']);
    }

    /**
     * Processes password reset request form submission
     *
     * Validates the submitted username, generates a reset token, and
     * displays the token to the user. In production, this should send
     * an email with the reset link instead.
     *
     * ## Security Considerations
     *
     * - Generic messages prevent username enumeration
     * - Tokens expire after 1 hour
     * - Old unused tokens are cleaned up
     * - Token can only be used once
     *
     * ## Usage
     *
     * This method expects POST data with:
     * - username: User's username
     *
     * @return void
     */
    public function sendResetToken(): void {

        // Validate CSRF token
        if (!$this->validateCsrfToken($_POST['csrf_token'] ?? null)) {
            $_SESSION['forgot_password_error'] = 'Invalid security token. Please try again.';
            $this->redirect('/forgot-password');
        }

        $username = $_POST['username'] ?? '';

        // Validate required field
        if (empty($username)) {
            $_SESSION['forgot_password_error'] = 'Username is required';
            $this->redirect('/forgot-password');
        }

        // Find user by username
        $users = $this->repository->find('PhlagUser', ['username' => $username]);

        // Security: Use generic message to prevent username enumeration
        if (empty($users)) {
            // Still show success message even if user doesn't exist
            $this->render('auth/reset_token_sent.html.twig', [
                'title'   => 'Reset Token Generated',
                'message' => 'If a user with that username exists, a reset token has been generated.',
            ]);
            return;
        }

        $user = reset($users);

        // Verify user object is valid and has a valid ID
        if (
            !$user instanceof PhlagUser || 
            !isset($user->phlag_user_id) ||
            empty($user->phlag_user_id)
        ) {
            $this->render('auth/reset_token_sent.html.twig', [
                'title'   => 'Reset Token Generated',
                'message' => 'If a user with that username exists, a reset token has been generated.',
            ]);
            return;
        }

        // Clean up old unused tokens for this user
        $this->cleanupOldTokens($user->phlag_user_id);

        // Generate new reset token
        $reset_token                 = $this->repository->new('PasswordResetToken');
        $reset_token->phlag_user_id = $user->phlag_user_id;
        $saved_token                 = $this->repository->save('PasswordResetToken', $reset_token);

        $reset_url = $this->base_url . '/reset-password?token=' . $saved_token->token;

        // Send email if service is configured
        $email_sent = false;
        if ($this->email_service !== null && !empty($user->email)) {
            $email_sent = $this->email_service->sendPasswordReset(
                $user->email,
                $reset_url,
                $saved_token->expires_at
            );

            if (!$email_sent) {
                error_log('Failed to send password reset email to: ' . $user->email);
            }
        }

        // Display appropriate message based on email status
        if ($email_sent) {
            // Email sent - show generic success message
            $this->render('auth/reset_token_sent.html.twig', [
                'title'      => 'Reset Token Sent',
                'message'    => 'If a user with that username exists, a password reset email has been sent.',
                'email_sent' => true,
            ]);
        } else {
            // Email not sent - show token on screen (development mode)
            $this->render('auth/reset_token_sent.html.twig', [
                'title'      => 'Reset Token Generated',
                'reset_url'  => $reset_url,
                'token'      => $saved_token->token,
                'expires_at' => $saved_token->expires_at,
                'email_sent' => false,
            ]);
        }
    }

    /**
     * Displays the password reset form
     *
     * Shows a form where users can enter a new password using a valid
     * reset token. Validates that the token exists, hasn't expired,
     * and hasn't been used.
     *
     * ## Edge Cases
     *
     * - If already logged in, redirects to dashboard
     * - If token is invalid/expired/used, shows error
     *
     * @return void
     */
    public function resetPassword(): void {

        // Already logged in? Redirect to dashboard
        if ($this->isLoggedIn()) {
            $this->redirect('/');
        }

        $token_string = $_GET['token'] ?? '';

        // Validate token is provided
        if (empty($token_string)) {
            $this->renderError('Invalid reset token', 400);
            return;
        }

        // Find the token
        $tokens = $this->repository->find('PasswordResetToken', ['token' => $token_string]);

        if (empty($tokens)) {
            $this->renderError('Invalid reset token', 400);
            return;
        }

        $token = reset($tokens);

        // Verify token object is valid and has a valid user ID (resetPassword method)
        if (
            !$token instanceof PasswordResetToken ||
            !isset($token->phlag_user_id) ||
            empty($token->phlag_user_id)
        ) {
            $this->renderError('Invalid reset token', 400);
            return;
        }

        // Check if token has been used
        if ($token->used) {
            $this->renderError('This reset token has already been used', 400);
            return;
        }

        // Check if token has expired
        if (strtotime($token->expires_at) < time()) {
            $this->renderError('This reset token has expired', 400);
            return;
        }

        // Show reset password form
        $this->render('auth/reset_password.html.twig', [
            'title' => 'Reset Password',
            'token' => $token_string,
            'error' => $_SESSION['reset_password_error'] ?? null,
        ]);

        // Clear any error message from session
        unset($_SESSION['reset_password_error']);
    }

    /**
     * Processes password reset form submission
     *
     * Validates the token and new password, updates the user's password,
     * marks the token as used, and logs the user in automatically.
     *
     * ## Security Considerations
     *
     * - Token must be valid, not expired, and not used
     * - Password must meet minimum length requirement
     * - Password confirmation must match
     * - Password is automatically hashed by PhlagUser mapper
     * - Token is marked as used to prevent reuse
     * - Auto-login after successful reset
     *
     * ## Usage
     *
     * This method expects POST data with:
     * - token: Reset token string
     * - password: New password
     * - password_confirm: Password confirmation
     *
     * @return void
     */
    public function updatePassword(): void {

        // Validate CSRF token
        if (!$this->validateCsrfToken($_POST['csrf_token'] ?? null)) {
            $_SESSION['reset_password_error'] = 'Invalid security token. Please try again.';
            $this->redirect('/reset-password?token=' . ($_POST['token'] ?? ''));
        }

        $token_string     = $_POST['token'] ?? '';
        $password         = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';

        // Validate required fields
        if (empty($token_string) || empty($password)) {
            $_SESSION['reset_password_error'] = 'All fields are required';
            $this->redirect('/reset-password?token=' . $token_string);
        }

        // Validate password confirmation
        if ($password !== $password_confirm) {
            $_SESSION['reset_password_error'] = 'Passwords do not match';
            $this->redirect('/reset-password?token=' . $token_string);
        }

        // Validate password length
        if (strlen($password) < 8) {
            $_SESSION['reset_password_error'] = 'Password must be at least 8 characters';
            $this->redirect('/reset-password?token=' . $token_string);
        }

        // Find the token
        $tokens = $this->repository->find('PasswordResetToken', ['token' => $token_string]);

        if (empty($tokens)) {
            $this->renderError('Invalid reset token', 400);
            return;
        }

        $token = reset($tokens);

        // Verify token object is valid and has a valid user ID (updatePassword method)
        if (
            !$token instanceof PasswordResetToken ||
            !isset($token->phlag_user_id) ||
            empty($token->phlag_user_id)
        ) {
            $this->renderError('Invalid reset token', 400);
            return;
        }

        // Check if token has been used
        if ($token->used) {
            $this->renderError('This reset token has already been used', 400);
            return;
        }

        // Check if token has expired
        if (strtotime($token->expires_at) < time()) {
            $this->renderError('This reset token has expired', 400);
            return;
        }

        // Get the user
        $user = $this->repository->get('PhlagUser', $token->phlag_user_id);

        if (!$user) {
            $this->renderError('User not found', 404);
            return;
        }

        // Update password
        $user->password  = $password; // Will be hashed by mapper
        $updated_user    = $this->repository->save('PhlagUser', $user);

        // Mark token as used
        $token->used = true;
        $this->repository->save('PasswordResetToken', $token);

        // Clean up any other unused tokens for this user
        $this->cleanupOldTokens($user->phlag_user_id);

        // Auto-login and redirect to dashboard
        $this->loginUser($updated_user->phlag_user_id, $updated_user->username);
        $_SESSION['password_reset_success'] = 'Password successfully reset';
        $this->redirect('/');
    }

    /**
     * Cleans up old unused reset tokens for a user
     *
     * Marks all existing unused tokens for a user as used. This prevents
     * old tokens from being used after a password has been reset or a new
     * token has been requested.
     *
     * ## How It Works
     *
     * Finds all tokens for the user where used = false and marks them
     * as used = true. This is called before generating a new token and
     * after successfully resetting a password.
     *
     * ## Edge Cases
     *
     * - If user_id is 0 or invalid, method returns early without error
     * - Handles case where no old tokens exist (normal for first-time reset)
     *
     * @param int $user_id The phlag_user_id to clean up tokens for
     *
     * @return void
     */
    protected function cleanupOldTokens(int $user_id): void {

        // Guard against invalid user IDs
        if ($user_id <= 0) {
            error_log("cleanupOldTokens called with invalid user_id: {$user_id}");
            return;
        }

        // Find all unused tokens for this user
        $old_tokens = $this->repository->find('PasswordResetToken', [
            'phlag_user_id' => $user_id,
            'used'          => 0,
        ]);

        // Mark them all as used
        foreach ($old_tokens as $old_token) {
            $old_token->used = true;
            $this->repository->save('PasswordResetToken', $old_token);
        }
    }
}
