<?php

namespace Moonspot\Phlag\Data;

/**
 * PhlagWebhook Value Object
 *
 * Represents a webhook configuration for flag change notifications. Webhooks
 * send HTTP POST requests to configured endpoints when flags are created,
 * updated, or deleted.
 *
 * ## Properties
 *
 * - **phlag_webhook_id**: Unique identifier for this webhook
 * - **name**: User-friendly name for this webhook
 * - **url**: Target endpoint URL (HTTPS required, except localhost)
 * - **is_active**: Enable/disable webhook without deleting it
 * - **headers_json**: Custom HTTP headers as JSON object
 * - **payload_template**: Twig template for payload customization
 * - **event_types_json**: Array of event types to trigger on
 * - **include_environment_changes**: Track environment value changes
 * - **create_datetime**: When this webhook was created
 * - **update_datetime**: When this webhook was last updated
 *
 * ## Usage
 *
 * ```php
 * $webhook = new PhlagWebhook();
 * $webhook->name = "Slack Notifications";
 * $webhook->url = "https://hooks.slack.com/services/xxx";
 * $webhook->is_active = true;
 * $webhook->event_types_json = json_encode(['created', 'updated']);
 * ```
 *
 * ## Edge Cases
 *
 * - URL must be HTTPS (except http://localhost or http://127.0.0.1)
 * - Private IP ranges are blocked (10.*, 192.168.*, 172.16-31.*)
 * - At least one event type must be enabled
 * - Headers must be valid JSON object
 *
 * @package Moonspot\Phlag
 */
class PhlagWebhook extends \Moonspot\ValueObjects\ValueObject {

    /**
     * Unique identifier for this webhook
     *
     * Auto-incremented primary key from the database.
     *
     * @var int
     */
    public int $phlag_webhook_id = 0;

    /**
     * User-friendly name for this webhook
     *
     * Used to identify the webhook in the admin UI.
     *
     * @var string
     */
    public string $name = '';

    /**
     * Target endpoint URL
     *
     * Must be HTTPS (except localhost for development). Private
     * IP ranges are blocked for security.
     *
     * @var string
     */
    public string $url = '';

    /**
     * Active status
     *
     * When false, webhook will not fire. Allows temporary
     * disabling without deletion.
     *
     * @var bool
     */
    public bool $is_active = true;

    /**
     * Custom HTTP headers as JSON
     *
     * JSON object where keys are header names and values are
     * header values. Example: {"Authorization": "Bearer xyz"}
     *
     * @var ?string
     */
    public ?string $headers_json = null;

    /**
     * Twig template for payload customization
     *
     * When null, uses default JSON payload format. Supports all
     * Twig syntax for conditionals and loops.
     *
     * @var ?string
     */
    public ?string $payload_template = null;

    /**
     * Event types to trigger on
     *
     * JSON array of event type strings. Valid values:
     * 'created', 'updated', 'deleted', 'environment_value_updated'
     *
     * @var string
     */
    public string $event_types_json = '';

    /**
     * Include environment value changes
     *
     * When true, webhook fires when environment-specific values
     * change, not just base flag changes.
     *
     * @var bool
     */
    public bool $include_environment_changes = false;

    /**
     * When this webhook was created
     *
     * Set automatically by the database.
     *
     * @var string
     */
    public string $create_datetime = '';

    /**
     * When this webhook was last updated
     *
     * Set automatically by the database on update.
     *
     * @var ?string
     */
    public ?string $update_datetime = null;
}
