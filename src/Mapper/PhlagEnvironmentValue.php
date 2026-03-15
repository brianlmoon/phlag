<?php

namespace Moonspot\Phlag\Mapper;

use Moonspot\Phlag\Data\Repository;
use Moonspot\Phlag\Web\Service\WebhookDispatcher;

/**
 * PhlagEnvironmentValue Mapper
 *
 * Maps environment-specific flag values to the database. This mapper handles
 * the relationship between flags and their values across different environments.
 * Dispatches webhooks when environment values change (if configured).
 *
 * ## Unique Constraint
 *
 * The database enforces a UNIQUE constraint on (`phlag_id`, `phlag_environment_id`),
 * ensuring that each flag can have only one value per environment. Attempting to
 * insert a duplicate will result in a database error.
 *
 * ## Cascade Behavior
 *
 * Foreign key constraints enable automatic cleanup:
 *
 * - **Delete flag**: Cascades to all environment values for that flag
 * - **Delete environment**: Cascades to all flag values in that environment
 *
 * This prevents orphaned records and maintains referential integrity.
 *
 * ## Webhook Integration
 *
 * Dispatches 'environment_value_updated' events to webhooks that have
 * include_environment_changes enabled. Webhook failures never block save
 * operations (fail-safe).
 *
 * ## Usage
 *
 * ```php
 * $mapper = new PhlagEnvironmentValue();
 * $env_value = new \Moonspot\Phlag\Data\PhlagEnvironmentValue();
 * $env_value->phlag_id = 1;
 * $env_value->phlag_environment_id = 2;
 * $env_value->value = "true";
 *
 * $mapper->save($env_value); // Webhooks dispatched automatically
 * ```
 *
 * ## Edge Cases
 *
 * - Saving a NULL value is valid (represents disabled flag)
 * - Both `start_datetime` and `end_datetime` can be NULL
 * - The mapper does not validate temporal logic (handled by actions)
 *
 * @package Moonspot\Phlag\Mapper
 */
class PhlagEnvironmentValue extends \DealNews\DB\AbstractMapper {

    /**
     * Database name
     */
    public const DATABASE_NAME = 'phlag';

    /**
     * Table name
     */
    public const TABLE = 'phlag_environment_values';

    /**
     * Table primary key column name
     */
    public const PRIMARY_KEY = 'phlag_environment_value_id';

    /**
     * Name of the class the mapper is mapping
     */
    public const MAPPED_CLASS = \Moonspot\Phlag\Data\PhlagEnvironmentValue::class;

    /**
     * Defines the properties that are mapped and any
     * additional information needed to map them.
     */
    public const MAPPING = [
        'phlag_environment_value_id' => [],
        'phlag_id'                   => [],
        'phlag_environment_id'       => [],
        'value'                      => [],
        'start_datetime'             => [],
        'end_datetime'               => [],
        'create_datetime'            => [
            'read_only' => true,
        ],
        'update_datetime'            => [
            'read_only' => true,
        ],
    ];

    /**
     * Saves an environment value and dispatches webhooks.
     *
     * Validates JSON type values before saving. Only dispatches webhooks
     * to those with include_environment_changes enabled. Webhook failures
     * are logged but never block the save.
     *
     * @param object $object PhlagEnvironmentValue object to save
     * @return object Saved PhlagEnvironmentValue object
     * @throws \RuntimeException If JSON validation fails
     */
    public function save($object): object {

        // Validate JSON type flags
        if ($object->value !== null && $object->phlag_id > 0) {
            $this->validateJsonIfNeeded($object);
        }

        // Call parent save method
        $saved = parent::save($object);

        // Dispatch webhooks for environment changes (non-blocking)
        try {
            $dispatcher = new WebhookDispatcher();
            $dispatcher->dispatchEnvironmentChange(
                'environment_value_updated',
                $saved
            );
        } catch (\Throwable $e) {
            error_log(sprintf(
                "Webhook dispatch failed for environment value %s: %s",
                $saved->phlag_environment_value_id,
                $e->getMessage()
            ));
        }

        return $saved;
    }

    /**
     * Validates JSON flag values
     *
     * Checks that JSON type flags have valid JSON values that are
     * objects or arrays (not primitives). Throws exception if invalid.
     *
     * @param object $object PhlagEnvironmentValue object to validate
     * @param ?Repository $repository Optional repository for testing
     * @return void
     * @throws \RuntimeException If JSON is invalid or is a primitive
     */
    protected function validateJsonIfNeeded(
        object $object,
        ?Repository $repository = null
    ): void {

        // Get repository (allow injection for testing)
        $repository = $repository ?? Repository::init();
        $phlag = $repository->get('Phlag', $object->phlag_id);

        if ($phlag === null || $phlag->type !== 'JSON') {
            return;
        }

        // Decode JSON
        $decoded = json_decode($object->value);

        // Check for JSON errors
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException(
                'Invalid JSON format: ' . json_last_error_msg()
            );
        }

        // Ensure it's an object or array (not primitive)
        if (!is_object($decoded) && !is_array($decoded)) {
            throw new \RuntimeException(
                'Invalid JSON format: JSON must be an object or array'
            );
        }
    }

    /**
     * Deletes an environment value and dispatches webhooks.
     *
     * Fetches the object before deletion to dispatch webhooks with full
     * context. Only dispatches to webhooks with include_environment_changes
     * enabled. Webhook failures are logged but never block the delete.
     *
     * @param int $id PhlagEnvironmentValue ID to delete
     * @return bool True on successful deletion
     */
    public function delete($id): bool {

        // Fetch object before deletion for webhook context
        try {
            $object = $this->get($id);
            if ($object !== null) {
                $dispatcher = new WebhookDispatcher();
                $dispatcher->dispatchEnvironmentChange(
                    'environment_value_updated',
                    $object
                );
            }
        } catch (\Throwable $e) {
            error_log(sprintf(
                "Webhook dispatch failed for environment value %s: %s",
                $id,
                $e->getMessage()
            ));
        }

        // Call parent delete method
        return parent::delete($id);
    }
}
