<?php

namespace Moonspot\Phlag\Data;

/**
 * PhlagApiKey Value Object
 *
 * Represents an API key used for authenticating requests to the Phlag API.
 * API keys are automatically generated as cryptographically secure 64-character
 * strings when created through the mapper.
 *
 * ## Properties
 *
 * - **plag_api_key_id**: Unique identifier for this API key
 * - **description**: Human-readable description to identify the key's purpose
 * - **api_key**: The actual API key value (64 characters, auto-generated)
 *
 * ## Security
 *
 * API keys should be treated as sensitive credentials:
 * - Only displayed in full once after creation
 * - Masked in UI displays (first 4 + asterisks + last 4 characters)
 * - Cannot be changed after creation (delete and recreate instead)
 *
 * ## Usage
 *
 * ```php
 * $api_key = new PhlagApiKey();
 * $api_key->description = "Production Mobile App Key";
 * // api_key will be auto-generated on save
 * ```
 *
 * @package Moonspot\Phlag\Data
 */
class PhlagApiKey extends \Moonspot\ValueObjects\ValueObject {

    /**
     * Unique identifier for this API key
     *
     * Auto-incremented primary key from the database.
     *
     * @var int
     */
    public int $plag_api_key_id = 0;

    /**
     * Human-readable description of this API key
     *
     * Used to identify the purpose or application using this key.
     * Examples: "Production Mobile App", "Staging Environment", etc.
     *
     * @var string
     */
    public string $description = '';

    /**
     * The actual API key value
     *
     * A cryptographically secure 64-character string automatically
     * generated when the object is saved. Uses URL-safe base64 encoding.
     *
     * This value is only shown in full once after creation. After that,
     * it should be masked in all displays for security.
     *
     * @var string
     */
    public string $api_key = '';

    /**
     * @var string
     */
    public string $create_datetime = '';
}
