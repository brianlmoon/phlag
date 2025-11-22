<?php

namespace Moonspot\Phlag\Mapper;

/**
 * Password Reset Token Mapper
 *
 * Maps the PasswordResetToken value object to the phlag_password_reset_tokens
 * database table. Handles CRUD operations and automatic token generation.
 *
 * ## Token Generation
 *
 * The mapper automatically generates cryptographically secure tokens when
 * saving a new PasswordResetToken with an empty token property. Tokens are
 * 64-character URL-safe strings that are safe to include in URLs and emails.
 *
 * ## Automatic Expiration
 *
 * If no expires_at value is set when creating a token, the mapper automatically
 * sets it to 1 hour from creation time. This can be customized by explicitly
 * setting the expires_at property before saving.
 *
 * ## Usage
 *
 * ```php
 * $repository = \Moonspot\Phlag\Data\Repository::init();
 *
 * // Create a new reset token
 * $token = $repository->new('PasswordResetToken');
 * $token->phlag_user_id = 123;
 * // Token and expires_at will be auto-generated
 * $saved_token = $repository->save('PasswordResetToken', $token);
 *
 * // Find valid tokens for a user
 * $tokens = $repository->find('PasswordResetToken', [
 *     'phlag_user_id' => 123,
 *     'used'          => 0
 * ]);
 *
 * // Mark token as used
 * $token->used = true;
 * $repository->save('PasswordResetToken', $token);
 * ```
 *
 * @package Moonspot\Phlag\Mapper
 */
class PasswordResetToken extends \DealNews\DB\AbstractMapper {

    /**
     * Database name
     */
    public const DATABASE_NAME = 'phlag';

    /**
     * Database table name
     */
    public const TABLE = 'phlag_password_reset_tokens';

    /**
     * Primary key column name
     */
    public const PRIMARY_KEY = 'phlag_password_reset_token_id';

    /**
     * Name of the class the mapper is mapping
     */
    public const MAPPED_CLASS = \Moonspot\Phlag\Data\PasswordResetToken::class;

    /**
     * Database table configuration
     *
     * Maps value object properties to database columns.
     * The create_datetime is managed automatically by the database.
     *
     * @var array
     */
    public const MAPPING = [
        'phlag_password_reset_token_id' => [],
        'phlag_user_id'                 => [],
        'token'                         => [],
        'expires_at'                    => [],
        'used'                          => [],
        'create_datetime'               => [
            'read_only' => true,
        ],
    ];

    /**
     * Saves a password reset token with auto-generation
     *
     * Extends the parent save() method to automatically generate:
     * - A cryptographically secure 64-character token if not set
     * - An expiration datetime 1 hour in the future if not set
     *
     * ## Token Generation Process
     *
     * 1. Check if token property is empty
     * 2. Generate 48 random bytes using random_bytes()
     * 3. Base64 encode and make URL-safe (no +, /, or =)
     * 4. Result is exactly 64 characters long
     *
     * ## Security
     *
     * The token uses PHP's random_bytes() for cryptographically secure
     * randomness, making tokens practically impossible to guess or brute-force.
     *
     * @param mixed $object The PasswordResetToken object to save
     *
     * @return object The saved token object with generated values populated
     */
    public function save($object): object {

        // Auto-generate token if not set
        if (empty($object->token)) {
            $object->token = $this->generateToken();
        }

        // Auto-set expiration to 1 hour from now if not set
        if (empty($object->expires_at)) {
            $object->expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
        }

        return parent::save($object);
    }

    /**
     * Generates a cryptographically secure reset token
     *
     * Creates a 64-character URL-safe string suitable for use in password
     * reset URLs and email links.
     *
     * ## How It Works
     *
     * 1. Generate 48 bytes of cryptographic randomness
     * 2. Base64 encode to string (creates 64 characters)
     * 3. Replace URL-unsafe characters:
     *    - Replace + with - (minus)
     *    - Replace / with _ (underscore)
     *    - Remove = padding characters
     *
     * The result is a 64-character string using only:
     * - Uppercase letters (A-Z)
     * - Lowercase letters (a-z)
     * - Digits (0-9)
     * - Minus (-) and underscore (_)
     *
     * ## Edge Cases
     *
     * This method will never return a duplicate token in practice due to
     * the enormous keyspace (256^48 possible values). The database enforces
     * uniqueness as a safety measure.
     *
     * @return string A 64-character URL-safe token string
     */
    protected function generateToken(): string {

        $bytes = random_bytes(48);
        $token = base64_encode($bytes);

        // Make URL-safe
        $token = str_replace(['+', '/', '='], ['-', '_', ''], $token);

        return $token;
    }
}
