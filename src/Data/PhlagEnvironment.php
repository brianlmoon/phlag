<?php

namespace Moonspot\Phlag\Data;

/**
 * PhlagEnvironment Value Object
 *
 * Represents an environment where feature flags can be deployed and managed.
 * Environments are used to organize flags across different deployment contexts
 * such as development, staging, production, etc.
 *
 * ## Properties
 *
 * - **phlag_environment_id**: Unique identifier for this environment
 * - **name**: Human-readable name of the environment
 * - **create_datetime**: When this environment was created
 * - **update_datetime**: When this environment was last updated
 *
 * ## Usage
 *
 * ```php
 * $environment = new PhlagEnvironment();
 * $environment->name = "Production";
 * ```
 *
 * @package Moonspot\Phlag\Data
 */
class PhlagEnvironment extends \Moonspot\ValueObjects\ValueObject {

    /**
     * Unique identifier for this environment
     *
     * Auto-incremented primary key from the database.
     *
     * @var int
     */
    public int $phlag_environment_id = 0;

    /**
     * Human-readable name of this environment
     *
     * Used to identify the deployment context. Examples: "Production",
     * "Staging", "Development", "QA", etc.
     *
     * @var string
     */
    public string $name = '';

    /**
     * Sort order for display
     *
     * Controls the display order of environments in the UI. Lower numbers
     * appear first. When sort_order values are equal, environments are
     * ordered alphabetically by name.
     *
     * @var int
     */
    public int $sort_order = 0;

    /**
     * When this environment was created
     *
     * Set automatically by the database.
     *
     * @var string
     */
    public string $create_datetime = '';

    /**
     * When this environment was last updated
     *
     * Set automatically by the database on update.
     *
     * @var ?string
     */
    public ?string $update_datetime = null;
}
