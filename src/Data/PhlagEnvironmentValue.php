<?php

namespace Moonspot\Phlag\Data;

/**
 * PhlagEnvironmentValue Value Object
 *
 * Represents a flag's value within a specific environment. Each flag can
 * have different values across multiple environments, enabling gradual
 * rollouts and environment-specific feature toggling.
 *
 * ## Null Value Semantics
 *
 * The `value` property uses NULL to indicate different states:
 *
 * - **NULL value in database**: Flag explicitly disabled in this environment
 * - **No row in database**: Flag not configured for this environment
 * - **Empty string**: Valid value (different from NULL)
 *
 * When a flag is disabled (NULL value):
 * - SWITCH type returns `false`
 * - Other types return `null`
 *
 * ## Temporal Scheduling
 *
 * Environment values support temporal constraints through `start_datetime`
 * and `end_datetime` properties. These work independently per environment,
 * allowing a flag to be active in Production while scheduled for future
 * activation in Staging.
 *
 * ## Properties
 *
 * - **phlag_environment_value_id**: Unique identifier
 * - **phlag_id**: Foreign key to phlags table
 * - **phlag_environment_id**: Foreign key to phlag_environments table
 * - **value**: The flag value (NULL = disabled)
 * - **start_datetime**: When this value becomes active (NULL = immediate)
 * - **end_datetime**: When this value expires (NULL = never)
 * - **create_datetime**: When this record was created
 * - **update_datetime**: When this record was last updated
 *
 * ## Usage
 *
 * ```php
 * $env_value = new PhlagEnvironmentValue();
 * $env_value->phlag_id = 1;
 * $env_value->phlag_environment_id = 2;
 * $env_value->value = "true";
 * $env_value->start_datetime = "2025-01-01 00:00:00";
 * ```
 *
 * ## Edge Cases
 *
 * - Setting `value` to empty string is valid for STRING type flags
 * - Both temporal fields NULL = always active
 * - Only start_datetime set = active from that point forward
 * - Only end_datetime set = active until that point
 *
 * @package Moonspot\Phlag\Data
 */
class PhlagEnvironmentValue extends \Moonspot\ValueObjects\ValueObject {

    /**
     * Unique identifier for this environment value
     *
     * Auto-incremented primary key from the database.
     *
     * @var int
     */
    public int $phlag_environment_value_id = 0;

    /**
     * Foreign key to the phlags table
     *
     * Links this value to a specific feature flag.
     *
     * @var int
     */
    public int $phlag_id = 0;

    /**
     * Foreign key to the phlag_environments table
     *
     * Links this value to a specific environment context.
     *
     * @var int
     */
    public int $phlag_environment_id = 0;

    /**
     * The flag value for this environment
     *
     * NULL indicates the flag is explicitly disabled in this environment.
     * For SWITCH flags, this should be "true" or "false".
     * For INTEGER/FLOAT flags, this should be a numeric string.
     * For STRING flags, this can be any string value.
     *
     * @var ?string
     */
    public ?string $value = null;

    /**
     * When this value becomes active
     *
     * If NULL, the value is active immediately.
     * Format: Y-m-d H:i:s
     *
     * @var ?string
     */
    public ?string $start_datetime = null;

    /**
     * When this value expires
     *
     * If NULL, the value never expires.
     * Format: Y-m-d H:i:s
     *
     * @var ?string
     */
    public ?string $end_datetime = null;

    /**
     * When this record was created
     *
     * Set automatically by the database.
     *
     * @var string
     */
    public string $create_datetime = '';

    /**
     * When this record was last updated
     *
     * Set automatically by the database on update.
     *
     * @var ?string
     */
    public ?string $update_datetime = null;
}
