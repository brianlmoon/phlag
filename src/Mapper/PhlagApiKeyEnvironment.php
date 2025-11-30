<?php

namespace Moonspot\Phlag\Mapper;

/**
 * PhlagApiKeyEnvironment Mapper
 *
 * Maps PhlagApiKeyEnvironment value objects to the phlag_api_key_environments
 * junction table. This mapper enables environment-scoped access control for
 * API keys.
 *
 * ## Responsibilities
 *
 * - Maps between PhlagApiKeyEnvironment objects and database records
 * - Enforces unique constraint (one assignment per key/environment pair)
 * - Handles cascading deletes when keys or environments are removed
 *
 * ## Access Control Model
 *
 * API keys can be restricted to specific environments by creating assignment
 * records through this mapper. The authentication layer queries these
 * assignments to determine if an API key can access a given environment.
 *
 * **Empty assignments** = Unrestricted access (backward compatible)
 * **One or more assignments** = Restricted to those environments only
 *
 * ## Usage
 *
 * ```php
 * $mapper = new PhlagApiKeyEnvironmentMapper();
 *
 * // Create a new assignment
 * $assignment = new PhlagApiKeyEnvironment();
 * $assignment->plag_api_key_id = 5;
 * $assignment->phlag_environment_id = 1;
 * $mapper->save($assignment);
 *
 * // Find all environments for a key
 * $assignments = $repository->find('PhlagApiKeyEnvironment', [
 *     'plag_api_key_id' => 5
 * ]);
 * ```
 *
 * ## Edge Cases
 *
 * - Duplicate assignments are prevented by database unique constraint
 * - Deleting API key or environment cascades to remove assignments
 * - Invalid foreign keys will cause database constraint violations
 *
 * @package Moonspot\Phlag\Mapper
 */
class PhlagApiKeyEnvironment extends \DealNews\DB\AbstractMapper {

    /**
     * Database name
     */
    public const DATABASE_NAME = 'phlag';

    /**
     * Table name
     */
    public const TABLE = 'phlag_api_key_environments';

    /**
     * Table primary key column name
     */
    public const PRIMARY_KEY = 'phlag_api_key_environment_id';

    /**
     * Name of the class the mapper is mapping
     */
    public const MAPPED_CLASS = \Moonspot\Phlag\Data\PhlagApiKeyEnvironment::class;

    /**
     * Defines the properties that are mapped and any
     * additional information needed to map them.
     */
    public const MAPPING = [
        'phlag_api_key_environment_id' => [],
        'plag_api_key_id'              => [],
        'phlag_environment_id'         => [],
        'create_datetime'              => [
            'read_only' => true,
        ],
    ];
}
