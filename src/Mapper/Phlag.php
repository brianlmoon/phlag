<?php

namespace Moonspot\Phlag\Mapper;

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
}
