<?php

namespace Moonspot\Phlag\Data;

/**
 * PhlagApiKeyEnvironment Value Object
 *
 * Represents an assignment of an API key to a specific environment.
 * API keys can be restricted to one or more environments for security
 * and access control purposes.
 *
 * ## Purpose
 *
 * This value object implements environment-scoped access control for API keys.
 * When API keys are assigned to specific environments, they can only be used
 * to query flag values for those environments. This supports:
 *
 * - Production-only API keys (preventing accidental staging/dev queries)
 * - Environment-specific keys for different teams
 * - Limiting blast radius if a key is compromised
 * - Multi-tenancy use cases
 *
 * ## Access Control Logic
 *
 * - **No assignments** → Unrestricted access to all environments (default)
 * - **One or more assignments** → Restricted to only those environments
 *
 * ## Properties
 *
 * - **phlag_api_key_environment_id**: Unique identifier for this assignment
 * - **plag_api_key_id**: Foreign key to phlag_api_keys table
 * - **phlag_environment_id**: Foreign key to phlag_environments table
 * - **create_datetime**: When this assignment was created
 *
 * ## Usage
 *
 * ```php
 * // Restrict an API key to production environment only
 * $assignment = new PhlagApiKeyEnvironment();
 * $assignment->plag_api_key_id = 5;
 * $assignment->phlag_environment_id = 1; // production
 * $repository->save('PhlagApiKeyEnvironment', $assignment);
 * ```
 *
 * ## Edge Cases
 *
 * - Duplicate assignments are prevented by unique constraint
 * - Deleting API key cascades to remove all assignments
 * - Deleting environment cascades to remove all assignments
 *
 * @package Moonspot\Phlag\Data
 */
class PhlagApiKeyEnvironment extends \Moonspot\ValueObjects\ValueObject {

    /**
     * Unique identifier for this assignment
     *
     * Auto-incremented primary key from the database.
     *
     * @var int
     */
    public int $phlag_api_key_environment_id = 0;

    /**
     * API key ID
     *
     * References the phlag_api_keys table. This is the key being
     * restricted to specific environments.
     *
     * @var int
     */
    public int $plag_api_key_id = 0;

    /**
     * Environment ID
     *
     * References the phlag_environments table. This is the environment
     * that the API key is allowed to access.
     *
     * @var int
     */
    public int $phlag_environment_id = 0;

    /**
     * Creation timestamp
     *
     * Records when this environment assignment was created. This is
     * read-only and automatically populated by the database.
     *
     * @var string
     */
    public string $create_datetime = '';
}
