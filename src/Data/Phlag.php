<?php

namespace Moonspot\Phlag\Data;

/**
 * Phlag Value Object
 *
 * Represents a feature flag definition. Values are stored per-environment
 * in the phlag_environment_values table via the PhlagEnvironmentValue object.
 *
 * ## Breaking Change (v2.0)
 *
 * The `value`, `start_datetime`, and `end_datetime` properties have been removed.
 * These are now managed per-environment via PhlagEnvironmentValue.
 *
 * ## Properties
 *
 * - **phlag_id**: Unique identifier for this flag
 * - **name**: Unique name used to reference this flag in API calls
 * - **description**: Optional human-readable description
 * - **type**: Flag type (SWITCH, INTEGER, FLOAT, STRING) - REQUIRED
 * - **create_datetime**: When this flag was created
 * - **update_datetime**: When this flag was last updated
 *
 * ## Usage
 *
 * ```php
 * $phlag = new Phlag();
 * $phlag->name = "feature_checkout";
 * $phlag->type = "SWITCH";
 * $phlag->description = "Enable new checkout flow";
 * ```
 *
 * ## Edge Cases
 *
 * - Type is REQUIRED and cannot be NULL
 * - Name must be unique across all flags
 * - Name must match pattern: [a-zA-Z0-9_-]+
 *
 * @package Moonspot\Phlag
 */
class Phlag extends \Moonspot\ValueObjects\ValueObject {

    /**
     * Unique identifier for this flag
     *
     * Auto-incremented primary key from the database.
     *
     * @var int
     */
    public int $phlag_id = 0;

    /**
     * Unique name for this flag
     *
     * Used in API calls to reference this flag. Must match
     * pattern: [a-zA-Z0-9_-]+
     *
     * @var string
     */
    public string $name = '';

    /**
     * Human-readable description of this flag
     *
     * Optional field to document the flag's purpose and usage.
     *
     * @var ?string
     */
    public ?string $description = null;

    /**
     * Flag type
     *
     * Determines how the value is interpreted and cast.
     * Valid values: SWITCH, INTEGER, FLOAT, STRING
     * This field is REQUIRED (NOT NULL).
     *
     * @var string
     */
    public string $type = '';

    /**
     * When this flag was created
     *
     * Set automatically by the database.
     *
     * @var string
     */
    public string $create_datetime = '';

    /**
     * When this flag was last updated
     *
     * Set automatically by the database on update.
     *
     * @var ?string
     */
    public ?string $update_datetime = null;
}
