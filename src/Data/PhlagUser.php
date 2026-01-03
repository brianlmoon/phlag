<?php

namespace Moonspot\Phlag\Data;

/**
 * Value object representing a Phlag user account.
 *
 * This object holds user authentication and profile data for the Phlag system.
 * Passwords are expected to be hashed before storage using password_hash().
 *
 * Usage:
 * ```php
 * $user = new PhlagUser();
 * $user->username = 'jdoe';
 * $user->full_name = 'John Doe';
 * $user->email = 'jdoe@example.com';
 * $user->password = password_hash('secret', PASSWORD_DEFAULT);
 * ```
 *
 * Edge Cases:
 * - The password field stores hashed values; never assign plaintext passwords
 * - Username must be unique across all users
 * - Email must be unique across all users
 * - Email should be validated before assignment
 * - Timestamps are managed automatically by the database
 *
 * @package Moonspot\Phlag
 */
class PhlagUser extends \Moonspot\ValueObjects\ValueObject {

    /**
     * Primary key identifier for the user
     *
     * @var int
     */
    public int $phlag_user_id = 0;

    /**
     * Unique username for authentication
     *
     * @var string
     */
    public string $username = '';

    /**
     * User's full display name
     *
     * @var string
     */
    public string $full_name = '';

    /**
     * User's email address
     *
     * Used for password reset notifications and other system communications.
     * Must be unique across all users.
     *
     * Note: Nullable to support existing databases that haven't been migrated
     * to include the email column. New installations should have email as NOT NULL.
     *
     * @var ?string
     */
    public ?string $email = null;

    /**
     * Hashed password for authentication.
     *
     * Heads-up: This should always contain a hashed password using
     * password_hash(), never plaintext.
     *
     * @var string
     */
    public string $password = '';

    /**
     * Google OAuth unique identifier
     *
     * Stores Google's unique user ID for OAuth-linked accounts. This allows
     * users to log in with Google without needing a password. Nullable because
     * not all users will have linked their Google account.
     *
     * Edge Cases:
     * - NULL for users who have never authenticated via Google
     * - Must be unique across all users to prevent duplicate linking
     * - Once set, cannot be changed (immutable linking)
     *
     * @var ?string
     */
    public ?string $google_id = null;

    /**
     * Timestamp when the user record was created
     *
     * @var string
     */
    public string $create_datetime = '';

    /**
     * Timestamp when the user record was last updated
     *
     * @var ?string
     */
    public ?string $update_datetime = '';
}
