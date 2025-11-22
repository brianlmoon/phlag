<?php

namespace Moonspot\Phlag\Action;

use DealNews\DataMapperAPI\Action\Base;

/**
 * Gets the current state of all phlags for a specific environment
 *
 * This action retrieves all phlags and their environment-specific values,
 * returning a JSON object with flag names as keys and their current typed
 * values. Each flag's value is evaluated based on temporal constraints and
 * cast to the appropriate type.
 *
 * ## Breaking Change (v2.0)
 *
 * Environment parameter is now REQUIRED. The old `/all-flags` endpoint
 * no longer exists. All requests must specify the environment:
 * `/all-flags/{environment}`
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
 * ## Response Format
 *
 * Returns a JSON object (not array) with flag names as keys:
 * ```json
 * {
 *   "feature_checkout": true,
 *   "max_items": 100,
 *   "price_multiplier": 1.5,
 *   "welcome_message": "Hello"
 * }
 * ```
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
 * ## Inactive Flag Behavior
 *
 * Flags without environment values or with inactive values are included:
 * - No environment value: Returns null (not configured)
 * - NULL value in database: Returns inactive value (explicitly disabled)
 * - Outside temporal window: Returns inactive value (scheduled/expired)
 *
 * Inactive values by type:
 * - SWITCH: `false`
 * - INTEGER/FLOAT/STRING: `null`
 *
 * ## Usage
 *
 * ```php
 * // GET /all-flags/production
 * // Returns: {"feature_checkout": true, "max_items": 100, ...}
 *
 * // GET /all-flags/staging
 * // Returns: {"feature_checkout": false, "max_items": 50, ...}
 * ```
 *
 * ## Edge Cases
 *
 * - Empty database returns empty object: `{}`
 * - Returns 404 if environment doesn't exist
 * - Flags not configured for environment return null
 * - All flags are included regardless of configuration status
 *
 * @package Moonspot\Phlag\Action
 */
class GetAllFlags extends Base {

    use FlagValueTrait;
    use ApiKeyAuthTrait;

    /**
     * Environment name (REQUIRED)
     *
     * Specifies which environment's values to retrieve. Common values
     * include "production", "staging", "development", etc.
     *
     * @var string
     */
    public string $environment;

    /**
     * Loads all phlags and returns their environment-specific typed values
     *
     * This method performs the following steps:
     * 1. Authenticate API key (returns 401 if invalid)
     * 2. Find environment by name (404 if not found)
     * 3. Retrieve all flags
     * 4. For each flag, find its environment value
     * 5. Check temporal constraints and build result
     *
     * The response is structured as an associative array with flag names
     * as keys and their typed values. Flags without environment values
     * return null (not configured for this environment).
     *
     * Heads-up: We use the FlagValueTrait methods to check temporal
     * constraints and cast values, ensuring consistency with the single
     * flag endpoint.
     *
     * Heads-up: This method requires Bearer token authentication via the
     * Authorization header. If authentication fails, a 401 response array
     * is returned.
     *
     * @return array Response data with all flag names and typed values
     */
    public function loadData(): array {

        $auth_error = $this->authenticateApiKey();
        if ($auth_error !== null) {
            return $auth_error;
        }

        // Step 1: Find environment by name
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

        // Step 2: Retrieve all phlags
        $phlags = $this->repository->find('Phlag', []);

        if (empty($phlags)) {
            return [
                'http_status' => 200,
                '__raw_value' => [],
            ];
        }

        // Step 3: Get current datetime for temporal checks
        $now = date('Y-m-d H:i:s');

        // Step 4: Build result array with flag names as keys
        $flags_data = [];

        foreach ($phlags as $phlag) {
            // Find environment value for this flag
            $env_values = $this->repository->find(
                'PhlagEnvironmentValue',
                [
                    'phlag_id'             => $phlag->phlag_id,
                    'phlag_environment_id' => $env->phlag_environment_id,
                ]
            );

            // No environment value = not configured
            if (empty($env_values)) {
                $flags_data[$phlag->name] = null;
                continue;
            }

            $env_value = reset($env_values);

            // NULL value = explicitly disabled
            if ($env_value->value === null) {
                $flags_data[$phlag->name] = $this->getInactiveValue($phlag->type);
                continue;
            }

            // Check temporal constraints
            if (!$this->isValueActive($env_value, $now)) {
                $flags_data[$phlag->name] = $this->getInactiveValue($phlag->type);
                continue;
            }

            // Cast and add active value
            $flags_data[$phlag->name] = $this->castValue($env_value->value, $phlag->type);
        }

        return [
            'http_status' => 200,
            '__raw_value' => $flags_data,
        ];
    }

    /**
     * Standard JSON response
     *
     * Overrides the base respond method to handle raw scalar values and
     * objects. When the data array contains a __raw_value key, it outputs
     * only that value as JSON. Otherwise, delegates to parent respond method.
     *
     * Heads-up: We use array_key_exists instead of isset because isset
     * returns false when the value is null, which is a valid return value.
     * However, for this endpoint we're returning an object/array, so this
     * is less critical than in GetPhlagState.
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
