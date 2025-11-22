<?php

namespace Moonspot\Phlag\Mapper;

/**
 * PhlagUser Mapper
 *
 * Maps PhlagUser value objects to the phlag_users database table.
 * Handles user authentication data including username, full name,
 * and automatic password hashing.
 *
 * ## Automatic Password Hashing
 *
 * The mapper automatically detects unhashed passwords and hashes them
 * using bcrypt before saving to the database. You can pass either a
 * plaintext password or a pre-hashed password - the mapper handles both.
 *
 * Detection works by checking if the password starts with the bcrypt
 * identifier ($2y$). If it doesn't, the password gets hashed automatically.
 *
 * ## Usage
 *
 * ```php
 * // Plaintext password - will be auto-hashed
 * $user = new PhlagUser();
 * $user->username = 'jdoe';
 * $user->full_name = 'John Doe';
 * $user->password = 'secret';
 *
 * $mapper = new PhlagUserMapper();
 * $saved = $mapper->save($user);
 * // $saved->password now contains bcrypt hash
 *
 * // Pre-hashed password - will not be re-hashed
 * $user->password = password_hash('secret', PASSWORD_BCRYPT);
 * $saved = $mapper->save($user);
 * ```
 *
 * ## Security Considerations
 *
 * - Uses PASSWORD_BCRYPT algorithm for strong password protection
 * - Detects already-hashed passwords to prevent double-hashing
 * - Empty passwords are not hashed (allows password updates without changing)
 *
 * @package Moonspot\Phlag\Mapper
 */
class PhlagUser extends \DealNews\DB\AbstractMapper {

    /**
     * Database name
     */
    public const DATABASE_NAME = 'phlag';

    /**
     * Table name
     */
    public const TABLE = 'phlag_users';

    /**
     * Table primary key column name
     */
    public const PRIMARY_KEY = 'phlag_user_id';

    /**
     * Name of the class the mapper is mapping
     */
    public const MAPPED_CLASS = \Moonspot\Phlag\Data\PhlagUser::class;

    /**
     * Defines the properties that are mapped and any
     * additional information needed to map them.
     */
    public const MAPPING = [
        'phlag_user_id'   => [],
        'username'        => [],
        'full_name'       => [],
        'email'           => [],
        'password'        => [],
        'create_datetime' => [
            'read_only' => true,
        ],
        'update_datetime' => [
            'read_only' => true,
        ],
    ];

    /**
     * Saves a PhlagUser object to the database
     *
     * Overrides the parent save method to automatically hash plaintext
     * passwords before storage. The method detects whether a password
     * is already hashed by checking for the bcrypt identifier ($2y$).
     *
     * ## Password Hashing Logic
     *
     * - If password is empty: No hashing occurs (allows updates without password change)
     * - If password starts with $2y$: Assumes already hashed, no re-hashing
     * - Otherwise: Hashes using PASSWORD_BCRYPT algorithm
     *
     * ## Usage
     *
     * ```php
     * $user = new PhlagUser();
     * $user->username = 'jdoe';
     * $user->password = 'plaintext'; // Will be hashed
     *
     * $mapper = new PhlagUserMapper();
     * $saved = $mapper->save($user);
     * // $saved->password is now a bcrypt hash
     * ```
     *
     * ## Edge Cases
     *
     * - Empty passwords are preserved (useful for password-less updates)
     * - Already-hashed passwords are not re-hashed
     * - Uses bcrypt specifically, not PASSWORD_DEFAULT
     *
     * @param object $object PhlagUser object to save
     *
     * @return object Saved PhlagUser with hashed password
     */
    public function save($object): object {

        // Hash password if it's not empty and not already hashed
        if (!empty($object->password) && !$this->isPasswordHashed($object->password)) {
            $object->password = $this->hashPassword($object->password);
        }

        // Call parent save method
        return parent::save($object);
    }

    /**
     * Checks if a password is already hashed using bcrypt
     *
     * Detects bcrypt hashes by looking for the $2y$ identifier at the
     * start of the password string. Bcrypt hashes always begin with this
     * marker followed by the cost parameter.
     *
     * ## How It Works
     *
     * Bcrypt hashes follow this format:
     * - $2y$10$... (where 10 is the cost factor)
     * - Always starts with $2y$ on modern PHP versions
     *
     * @param string $password Password string to check
     *
     * @return bool Returns true if password appears to be hashed
     */
    protected function isPasswordHashed(string $password): bool {

        // Bcrypt hashes start with $2y$ (PHP's bcrypt identifier)
        return str_starts_with($password, '$2y$');
    }

    /**
     * Hashes a plaintext password using bcrypt
     *
     * Uses PHP's password_hash() function with the PASSWORD_BCRYPT
     * algorithm. Bcrypt provides strong, adaptive hashing suitable
     * for password storage.
     *
     * ## Security
     *
     * - Uses bcrypt algorithm (Blowfish-based)
     * - Automatically includes salt generation
     * - Uses PHP's default cost factor (currently 10)
     * - Future-proof against algorithm changes
     *
     * @param string $password Plaintext password to hash
     *
     * @return string Bcrypt hash of the password
     */
    protected function hashPassword(string $password): string {

        return password_hash($password, PASSWORD_BCRYPT);
    }
}
