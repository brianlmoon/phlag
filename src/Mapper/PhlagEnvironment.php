<?php

namespace Moonspot\Phlag\Mapper;

/**
 * PhlagEnvironment Mapper
 *
 * Maps PhlagEnvironment value objects to the phlag_environments database table.
 * Provides standard CRUD operations for managing deployment environments.
 *
 * ## Usage
 *
 * Environments represent different deployment contexts where feature flags
 * are managed, such as development, staging, and production.
 *
 * @package Moonspot\Phlag\Mapper
 */
class PhlagEnvironment extends \DealNews\DB\AbstractMapper {

    /**
     * Database name
     */
    public const DATABASE_NAME = 'phlag';

    /**
     * Table name
     */
    public const TABLE = 'phlag_environments';

    /**
     * Table primary key column name
     */
    public const PRIMARY_KEY = 'phlag_environment_id';

    /**
     * Name of the class the mapper is mapping
     */
    public const MAPPED_CLASS = \Moonspot\Phlag\Data\PhlagEnvironment::class;

    /**
     * Defines the properties that are mapped and any
     * additional information needed to map them.
     */
    public const MAPPING = [
        'phlag_environment_id' => [],
        'name'                 => [],
        'sort_order'           => [],
        'create_datetime'      => [
            'read_only' => true,
        ],
        'update_datetime'      => [
            'read_only' => true,
        ],
    ];
}
