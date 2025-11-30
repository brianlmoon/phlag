<?php

namespace Moonspot\Phlag\Data;

/**
 * Phlag Data Repository
 *
 * Extends the DataMapper Repository to automatically register all
 * Phlag-related mappers on instantiation. This centralizes mapper
 * configuration and reduces boilerplate when using the repository.
 *
 * ## Usage
 *
 * ```php
 * $repository = new \Moonspot\Phlag\Data\Repository();
 *
 * // Retrieve a single Phlag by ID
 * $phlag = $repository->get('Phlag', 1);
 *
 * // Find multiple Phlags matching criteria
 * $phlags = $repository->find('Phlag', ['type' => 'SWITCH']);
 *
 * // Create a new Phlag
 * $new_phlag = $repository->new('Phlag');
 * $new_phlag->name = 'feature_x';
 * $repository->save($new_phlag);
 *
 * // Work with API keys
 * $api_key = $repository->get('PhlagApiKey', 1);
 * ```
 *
 * @package Moonspot\Phlag\Data
 */
class Repository extends \DealNews\DataMapper\Repository {

    /**
     * Singleton instance
     *
     * @var ?Repository
     */
    protected static ?Repository $instance = null;

    /**
     * Returns the singleton instance of the Repository
     *
     * This method implements the singleton pattern to ensure only one
     * instance of the repository exists throughout the application lifecycle.
     * On first call, it instantiates the repository with default mappers.
     * Subsequent calls return the same instance.
     *
     * ## Usage
     *
     * ```php
     * $repository = \Moonspot\Phlag\Data\Repository::init();
     * $phlag = $repository->get('Phlag', 1);
     * ```
     *
     * @return Repository The singleton repository instance
     */
    public static function init(): Repository {

        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }
    /**
     * Creates the repository and registers all Phlag mappers
     *
     * Automatically instantiates and registers the following mappers:
     * - Phlag: Maps to the phlags table
     * - PhlagApiKey: Maps to the phlag_api_keys table
     * - PhlagApiKeyEnvironment: Maps to the phlag_api_key_environments table
     * - PhlagUser: Maps to the phlag_users table
     * - PhlagEnvironment: Maps to the phlag_environments table
     * - PhlagEnvironmentValue: Maps to the phlag_environment_values table
     * - PasswordResetToken: Maps to the phlag_password_reset_tokens table
     *
     * @param array $mappers Optional additional mappers to register beyond
     *                       the default Phlag mappers. Format is the same
     *                       as the parent Repository class.
     */
    public function __construct(array $mappers = []) {

        $default_mappers = [
            'Phlag'                    => new \Moonspot\Phlag\Mapper\Phlag(),
            'PhlagApiKey'              => new \Moonspot\Phlag\Mapper\PhlagApiKey(),
            'PhlagApiKeyEnvironment'   => new \Moonspot\Phlag\Mapper\PhlagApiKeyEnvironment(),
            'PhlagUser'                => new \Moonspot\Phlag\Mapper\PhlagUser(),
            'PhlagEnvironment'         => new \Moonspot\Phlag\Mapper\PhlagEnvironment(),
            'PhlagEnvironmentValue'    => new \Moonspot\Phlag\Mapper\PhlagEnvironmentValue(),
            'PasswordResetToken'       => new \Moonspot\Phlag\Mapper\PasswordResetToken(),
        ];

        $all_mappers = array_merge($default_mappers, $mappers);

        parent::__construct($all_mappers);
    }
}
