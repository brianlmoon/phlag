<?php

namespace Moonspot\Phlag\Mapper;

use Moonspot\Phlag\Web\Service\WebhookDispatcher;

/**
 * Phlag Mapper
 *
 * Maps Phlag value objects to/from the phlags table. Dispatches webhooks
 * on save operations to notify external systems of flag changes.
 *
 * ## Webhook Integration
 *
 * Automatically dispatches webhooks after successful save operations:
 * - Creates: Sends 'created' event
 * - Updates: Sends 'updated' event with previous state
 * - Webhook failures never block flag operations (fail-safe)
 *
 * ## Usage
 *
 * ```php
 * $mapper = new Phlag();
 * $flag = new \Moonspot\Phlag\Data\Phlag();
 * $flag->name = "feature_checkout";
 * $flag->type = "SWITCH";
 * $saved = $mapper->save($flag); // Webhooks dispatched automatically
 * ```
 *
 * @package Moonspot\Phlag\Mapper
 */
class Phlag extends \DealNews\DB\AbstractMapper {

    /**
     * Database name
     */
    public const DATABASE_NAME = 'phlag';

    /**
     * Table name
     */
    public const TABLE = 'phlags';

    /**
     * Table primary key column name
     */
    public const PRIMARY_KEY = 'phlag_id';

    /**
     * Name of the class the mapper is mapping
     */
    public const MAPPED_CLASS = \Moonspot\Phlag\Data\Phlag::class;

    /**
     * Defines the properties that are mapped and any
     * additional information needed to map them.
     */
    public const MAPPING = [
        'phlag_id'        => [],
        'name'            => [],
        'description'     => [],
        'type'            => [],
        'create_datetime' => [
            'read_only' => true,
        ],
        'update_datetime' => [
            'read_only' => true,
        ]
    ];

    /**
     * Saves a flag and dispatches webhooks.
     *
     * Detects if this is a create or update operation, preserves the old
     * flag state for updates, saves the flag, then dispatches webhooks
     * with appropriate event type.
     *
     * ## Webhook Behavior
     *
     * - Creates: Dispatches 'created' event
     * - Updates: Dispatches 'updated' event with old_flag context
     * - Webhook failures are logged but never block the save
     * - Webhooks are skipped if webhooks.enabled config is false
     *
     * @param object $object Phlag object to save
     * @return object Saved Phlag object
     */
    public function save($object): object {

        $is_new = empty($object->phlag_id);

        // For updates, fetch old value first
        $old_flag = null;
        if (!$is_new) {
            $old_flag = $this->load($object->phlag_id);
        }

        // Call parent save method
        $saved = parent::save($object);

        // Dispatch webhooks (non-blocking)
        try {
            $dispatcher = new WebhookDispatcher();
            $event_type = $is_new ? 'created' : 'updated';
            $dispatcher->dispatch($event_type, $saved, $old_flag);
        } catch (\Throwable $e) {
            error_log(sprintf(
                "Webhook dispatch failed for flag '%s': %s",
                $saved->name,
                $e->getMessage()
            ));
        }

        return $saved;
    }
}
