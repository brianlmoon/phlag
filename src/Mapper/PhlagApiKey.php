<?php

namespace Moonspot\Phlag\Mapper;

/**
 * PhlagApiKey Mapper
 *
 * Maps PhlagApiKey value objects to the phlag_api_keys database table.
 * Automatically generates a secure 64-character API key on creation if
 * one is not provided.
 *
 * ## Auto-Generation
 *
 * When saving a PhlagApiKey with an empty api_key property, the mapper
 * will automatically generate a cryptographically secure random key using
 * random_bytes(). The key is URL-safe base64 encoded.
 *
 * @package Moonspot\Phlag\Mapper
 */
class PhlagApiKey extends \DealNews\DB\AbstractMapper {

    /**
     * Database name
     */
    public const DATABASE_NAME = 'phlag';

    /**
     * Table name
     */
    public const TABLE = 'phlag_api_keys';

    /**
     * Table primary key column name
     */
    public const PRIMARY_KEY = 'plag_api_key_id';

    /**
     * Name of the class the mapper is mapping
     */
    public const MAPPED_CLASS = \Moonspot\Phlag\Data\PhlagApiKey::class;

    /**
     * Defines the properties that are mapped and any
     * additional information needed to map them.
     */
    public const MAPPING = [
        'plag_api_key_id' => [],
        'description'     => [],
        'api_key'         => [],
        'create_datetime' => [
            'read_only' => true,
        ],
    ];

    /**
     * Saves a PhlagApiKey object to the database
     *
     * Overrides the parent save method to automatically generate a
     * cryptographically secure API key if one is not provided. The key
     * is 64 characters long and uses URL-safe base64 encoding.
     *
     * ## API Key Generation
     *
     * - Uses random_bytes() for cryptographic randomness
     * - Converts to URL-safe base64 (no +, /, or = characters)
     * - Always generates exactly 64 characters
     * - Only generates if api_key property is empty
     *
     * ## Usage
     *
     * ```php
     * $api_key = new PhlagApiKey();
     * $api_key->description = "Production API Key";
     * // api_key is empty, will be auto-generated
     *
     * $mapper = new PhlagApiKeyMapper();
     * $saved = $mapper->save($api_key);
     * // $saved->api_key now contains a 64-char random key
     * ```
     *
     * @param object $object PhlagApiKey object to save
     *
     * @return object Saved PhlagApiKey with generated api_key
     *
     * @throws \Exception If random_bytes() fails
     */
    public function save($object): object {

        // Generate API key if not provided
        if (empty($object->api_key)) {
            $object->api_key = $this->generateApiKey();
        }

        // Call parent save method
        return parent::save($object);
    }

    /**
     * Generates a cryptographically secure 64-character API key
     *
     * Uses PHP's random_bytes() function to generate cryptographically
     * secure random data, then converts it to URL-safe base64 encoding.
     * The resulting key is exactly 64 characters long.
     *
     * ## Implementation Details
     *
     * - Generates 48 random bytes (48 * 4/3 = 64 base64 chars)
     * - URL-safe encoding: replaces + with -, / with _, removes =
     * - Suitable for use in URLs and HTTP headers
     *
     * ## Security
     *
     * Uses random_bytes() which is cryptographically secure on all
     * modern PHP installations. Falls back to system entropy sources.
     *
     * @return string 64-character URL-safe base64 encoded API key
     *
     * @throws \Exception If random_bytes() cannot gather sufficient entropy
     */
    protected function generateApiKey(): string {

        // Generate 48 random bytes
        // 48 bytes * 4/3 (base64 ratio) = 64 characters
        $random_bytes = random_bytes(48);

        // Convert to base64 and make URL-safe
        $api_key = base64_encode($random_bytes);

        // Make URL-safe: replace + with -, / with _, remove =
        $api_key = str_replace(['+', '/', '='], ['-', '_', ''], $api_key);

        return $api_key;
    }
}
