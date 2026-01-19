<?php

namespace Moonspot\Phlag\Mapper;

use Moonspot\Phlag\Data\PhlagWebhook as PhlagWebhookData;

/**
 * PhlagWebhook Mapper
 *
 * Maps PhlagWebhook value objects to/from the phlag_webhooks table.
 * Validates URL and event_types_json before saving.
 *
 * ## Validation Rules
 *
 * - URL must start with https:// (except http://localhost or http://127.0.0.1)
 * - URL must not target private IP ranges
 * - event_types_json must be valid JSON array
 * - At least one event type must be specified
 *
 * ## Usage
 *
 * ```php
 * $mapper = new PhlagWebhook();
 * $webhook = new \Moonspot\Phlag\Data\PhlagWebhook();
 * $webhook->name = "Production Alerts";
 * $webhook->url = "https://example.com/webhook";
 * $webhook->event_types_json = json_encode(['created']);
 * $saved = $mapper->save($webhook);
 * ```
 *
 * @package Moonspot\Phlag
 */
class PhlagWebhook extends \DealNews\DB\AbstractMapper {

    /**
     * Database name
     */
    public const DATABASE_NAME = 'phlag';

    /**
     * Table name
     */
    public const TABLE = 'phlag_webhooks';

    /**
     * Table primary key column name
     */
    public const PRIMARY_KEY = 'phlag_webhook_id';

    /**
     * Name of the class the mapper is mapping
     */
    public const MAPPED_CLASS = \Moonspot\Phlag\Data\PhlagWebhook::class;

    /**
     * Defines the properties that are mapped and any
     * additional information needed to map them.
     */
    public const MAPPING = [
        'phlag_webhook_id'             => [],
        'name'                         => [],
        'url'                          => [],
        'is_active'                    => [],
        'headers_json'                 => [],
        'payload_template'             => [],
        'event_types_json'             => [],
        'include_environment_changes'  => [],
        'create_datetime'              => [
            'read_only' => true,
        ],
        'update_datetime'              => [
            'read_only' => true,
        ],
    ];

    /**
     * Validates webhook configuration before saving.
     *
     * Checks URL format, security constraints, and event types.
     * Throws InvalidArgumentException if validation fails.
     *
     * @param object $object PhlagWebhook to validate
     * @return bool True if valid
     * @throws \InvalidArgumentException If validation fails
     */
    protected function validate(object $object): bool {

        if (!($object instanceof PhlagWebhookData)) {
            throw new \InvalidArgumentException(
                'Object must be instance of PhlagWebhook'
            );
        }

        // Validate URL format
        if (empty($object->url)) {
            throw new \InvalidArgumentException('URL is required');
        }

        if (!filter_var($object->url, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('URL is not valid');
        }

        // Require HTTPS except for localhost
        $parsed = parse_url($object->url);
        $is_localhost = in_array(
            $parsed['host'] ?? '',
            ['localhost', '127.0.0.1', '::1']
        );

        if (
            $parsed['scheme'] !== 'https' &&
            !($parsed['scheme'] === 'http' && $is_localhost)
        ) {
            throw new \InvalidArgumentException(
                'URL must use HTTPS (except localhost)'
            );
        }

        // Block private IP ranges
        if (!$is_localhost && isset($parsed['host'])) {
            $ip = gethostbyname($parsed['host']);
            if ($this->isPrivateIp($ip)) {
                throw new \InvalidArgumentException(
                    'URL cannot target private IP ranges'
                );
            }
        }

        // Validate event types JSON
        if (empty($object->event_types_json)) {
            throw new \InvalidArgumentException(
                'At least one event type is required'
            );
        }

        $event_types = json_decode($object->event_types_json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException(
                'event_types_json must be valid JSON'
            );
        }

        if (!is_array($event_types) || empty($event_types)) {
            throw new \InvalidArgumentException(
                'At least one event type must be specified'
            );
        }

        // Validate headers JSON if present
        if ($object->headers_json !== null) {
            $headers = json_decode($object->headers_json, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \InvalidArgumentException(
                    'headers_json must be valid JSON'
                );
            }
            if (!is_array($headers)) {
                throw new \InvalidArgumentException(
                    'headers_json must be a JSON object'
                );
            }
        }

        return true;
    }

    /**
     * Checks if an IP address is in a private range.
     *
     * Blocks RFC1918 private networks and loopback addresses.
     *
     * @param string $ip IP address to check
     * @return bool True if IP is private
     */
    protected function isPrivateIp(string $ip): bool {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
    }
}
