<?php

namespace Moonspot\Phlag\Action;

use DealNews\DataMapperAPI\Action\Base;

/**
 * Gets the current state of a phlag by environment and name
 *
 * This action retrieves a phlag by its name and environment, returning the
 * current value if active based on temporal constraints. The value is cast
 * to the appropriate type based on the phlag's type field.
 *
 * ## Breaking Change (v2.0)
 *
 * Environment parameter is now REQUIRED. The old `/flag/{name}` endpoint
 * no longer exists. All requests must specify the environment:
 * `/flag/{environment}/{name}`
 *
 * Heads-up: This class uses the FlagValueTrait for shared temporal logic
 * and type casting functionality.
 *
 * ## Authentication
 *
 * This endpoint requires Bearer token authentication. Include a valid API
 * key from the phlag_api_keys table in the Authorization header:
 *
 * ```
 * Authorization: Bearer <api_key>
 * ```
 *
 * Returns 401 Unauthorized if authentication fails.
 *
 * ## Empty Value Handling
 *
 * Different scenarios return different values:
 * - **No environment value row**: Returns null (flag not configured for environment)
 * - **Row with NULL value**: Returns inactive value (flag explicitly disabled)
 * - **Row outside temporal window**: Returns inactive value (flag scheduled/expired)
 *
 * Inactive values by type:
 * - SWITCH: `false`
 * - INTEGER/FLOAT/STRING: `null`
 *
 * ## Temporal Logic
 *
 * An environment value is considered active when:
 * - start_datetime is NULL or in the past/present
 * - end_datetime is NULL or in the future/present
 *
 * ## Type Casting
 *
 * Values are cast based on the phlag type:
 * - SWITCH: boolean (true/false)
 * - INTEGER: integer
 * - FLOAT: float
 * - STRING: string (no casting)
 *
 * ## Usage
 *
 * ```php
 * // GET /flag/production/feature_checkout
 * // Headers: Authorization: Bearer abc123...
 * // Returns: true (for SWITCH type in production)
 *
 * // GET /flag/staging/max_items
 * // Headers: Authorization: Bearer abc123...
 * // Returns: 50 (for INTEGER type in staging)
 *
 * // GET /flag/production/unconfigured_flag
 * // Headers: Authorization: Bearer abc123...
 * // Returns: null (no environment value exists)
 * ```
 *
 * ## Edge Cases
 *
 * - Returns null if phlag name doesn't exist
 * - Returns 404 if environment doesn't exist
 * - Returns null if phlag exists but not configured for environment
 * - Returns false if SWITCH phlag is disabled or inactive
 * - Returns null if non-SWITCH phlag is disabled or inactive
 *
 * @package Moonspot\Phlag\Action
 */
class GetPhlagState extends Base {

    use FlagValueTrait;
    use ApiKeyAuthTrait;

    /**
     * Environment name (REQUIRED)
     *
     * Specifies which environment's value to retrieve. Common values
     * include "production", "staging", "development", etc.
     *
     * @var string
     */
    public string $environment;

    /**
     * Name of the phlag to retrieve
     *
     * @var string
     */
    public string $name;

    /**
     * Loads the phlag environment value and returns its typed value
     *
     * This method performs the following steps:
     * 1. Authenticate API key (returns 401 if invalid)
     * 2. Find flag by name
     * 3. Find environment by name (404 if not found)
     * 4. Find environment value for this flag/environment combination
     * 5. Check temporal constraints
     * 6. Cast and return the value
     *
     * When returning the value, it wraps it in a special array structure
     * that signals to the respond method to output only the value.
     *
     * Heads-up: This method requires Bearer token authentication via the
     * Authorization header. If authentication fails, a 401 response array
     * is returned.
     *
     * ## Return Value Scenarios
     *
     * - **Flag doesn't exist**: Returns null
     * - **Environment doesn't exist**: Returns 404 error
     * - **No environment value row**: Returns null (not configured)
     * - **NULL value in database**: Returns inactive value (explicitly disabled)
     * - **Outside temporal window**: Returns inactive value (scheduled/expired)
     * - **Active value**: Returns typed value
     *
     * @return array Response data with typed value or error information
     */
    public function loadData(): array {

        $auth_error = $this->authenticateApiKey();
        if ($auth_error !== null) {
            return $auth_error;
        }

        // Step 1: Find flag by name
        $flags = $this->repository->find('Phlag', ['name' => $this->name]);

        if (empty($flags)) {
            return [
                'http_status' => 200,
                '__raw_value' => null,
            ];
        }

        $phlag = reset($flags);

        // Step 2: Find environment by name
        $environments = $this->repository->find(
            'PhlagEnvironment',
            ['name' => $this->environment]
        );

        if (empty($environments)) {
            return [
                'http_status' => 404,
                'error'       => 'Environment not found',
                'message'     => sprintf('Environment "%s" does not exist', $this->environment),
            ];
        }

        $env = reset($environments);

        // Step 3: Find environment value for this flag/environment combination
        $env_values = $this->repository->find(
            'PhlagEnvironmentValue',
            [
                'phlag_id'             => $phlag->phlag_id,
                'phlag_environment_id' => $env->phlag_environment_id,
            ]
        );

        // No row = flag not configured for this environment
        if (empty($env_values)) {
            return [
                'http_status' => 200,
                '__raw_value' => null,
            ];
        }

        $env_value = reset($env_values);

        // NULL value = flag explicitly disabled in this environment
        if ($env_value->value === null) {
            return [
                'http_status' => 200,
                '__raw_value' => $this->getInactiveValue($phlag->type),
            ];
        }

        // Step 4: Check temporal constraints
        $now = date('Y-m-d H:i:s');

        if (!$this->isValueActive($env_value, $now)) {
            return [
                'http_status' => 200,
                '__raw_value' => $this->getInactiveValue($phlag->type),
            ];
        }

        // Step 5: Cast and return active value
        $typed_value = $this->castValue($env_value->value, $phlag->type);

        return [
            'http_status' => 200,
            '__raw_value' => $typed_value,
        ];
    }

    /**
     * Standard JSON response
     *
     * Overrides the base respond method to handle raw scalar values.
     * When the data array contains a __raw_value key, it outputs only
     * that value as JSON. Otherwise, delegates to parent respond method.
     *
     * Heads-up: We use array_key_exists instead of isset because isset
     * returns false when the value is null, which is a valid return value.
     *
     * @param array $data The data to return
     */
    public function respond(array $data): void {
        if (array_key_exists('__raw_value', $data)) {
            // Extract and output only the raw value
            $raw_value = $data['__raw_value'];
            
            // Set HTTP status code if present
            if (!empty($data['http_status']) && !empty($_SERVER['REQUEST_URI'])) {
                http_response_code($data['http_status']);
            }
            
            echo json_encode($raw_value);
        } else {
            parent::respond($data);
        }
    }
}
