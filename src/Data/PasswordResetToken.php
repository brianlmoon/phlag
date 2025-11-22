<?php

namespace Moonspot\Phlag\Data;

use Moonspot\ValueObjects\ValueObject;

/**
 * Password Reset Token Value Object
 *
 * Represents a password reset token record in the phlag_password_reset_tokens
 * table. Tokens are cryptographically secure random strings used for verifying
 * password reset requests.
 *
 * ## Token Lifecycle
 *
 * 1. User requests password reset
 * 2. Token is generated and saved with expiration time
 * 3. Token is sent to user (typically via email)
 * 4. User clicks link with token to reset password
 * 5. Token is validated (not expired, not used)
 * 6. Password is updated and token is marked as used
 *
 * ## Security Considerations
 *
 * - Tokens are 64-character URL-safe strings
 * - Tokens expire after a configurable time period (default 1 hour)
 * - Tokens can only be used once
 * - All unused tokens for a user are invalidated when password is reset
 *
 * @package Moonspot\Phlag\Data
 */
class PasswordResetToken extends ValueObject {

    /**
     * Primary key identifier
     *
     * @var ?int
     */
    public ?int $phlag_password_reset_token_id = null;

    /**
     * Foreign key to phlag_users table
     *
     * Links this token to a specific user account. When the user is deleted,
     * their reset tokens are automatically cascade deleted.
     *
     * @var ?int
     */
    public ?int $phlag_user_id = null;

    /**
     * The cryptographically secure reset token
     *
     * 64-character URL-safe string generated using random_bytes().
     * This token is sent to the user and used to verify their identity
     * when resetting their password.
     *
     * @var ?string
     */
    public ?string $token = null;

    /**
     * Token expiration datetime
     *
     * ISO 8601 formatted datetime string indicating when this token expires.
     * Tokens are typically valid for 1 hour after creation. Expired tokens
     * cannot be used to reset passwords.
     *
     * @var ?string
     */
    public ?string $expires_at = null;

    /**
     * Whether this token has been used
     *
     * Tokens can only be used once. After a successful password reset,
     * this flag is set to true to prevent token reuse.
     *
     * @var bool
     */
    public bool $used = false;

    /**
     * When this token was created
     *
     * ISO 8601 formatted datetime string. Automatically set by the database
     * on insert. This is a readonly property managed by the mapper.
     *
     * @var ?string
     */
    public ?string $create_datetime = null;
}
