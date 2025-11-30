<?php

namespace Moonspot\Phlag\Action;

use DealNews\DataMapperAPI\Action\Base;

/**
 * Gets complete details for all phlags with environment-specific values
 *
 * This action retrieves all phlags and their environment-specific values,
 * returning a JSON array where each element contains complete flag details:
 * name, type, evaluated value, and temporal constraints (start/end datetimes).
 *
 * ## Breaking Change (v2.0)
 *
 * Environment parameter is now REQUIRED. The old `/get-flags` endpoint
 * no longer exists. All requests must specify the environment:
 * `/get-flags/{environment}`
 *
 * Heads-up: This class uses the FlagValueTrait for shared temporal logic
 * and type casting functionality, ensuring consistency with the other
 * flag endpoints.
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
 * Returns a JSON array (not object) with complete flag details:
 * ```json
 * [
 *   {
 *     "name": "feature_checkout",
 *     "type": "SWITCH",
 *     "value": true,
 *     "start_datetime": null,
 *     "end_datetime": null
 *   },
 *   {
 *     "name": "max_items",
 *     "type": "INTEGER",
 *     "value": 100,
 *     "start_datetime": "2024-01-01T00:00:00-05:00",
 *     "end_datetime": "2025-12-31T23:59:59-05:00"
 *   }
 * ]
 * ```
 *
 * ## Comparison with Other Endpoints
 *
 * This endpoint differs from the related flag endpoints in important ways:
 *
 * - `/flag/{environment}/{name}`: Returns single typed value for one flag
 * - `/all-flags/{environment}`: Returns object of flag names → values only
 * - `/get-flags/{environment}` (this): Returns array with complete flag details
 *
 * Use this endpoint when you need:
 * - Full flag inventory with metadata for an environment
 * - Administration and debugging information
 * - Temporal constraint visibility per environment
 * - Type information for each flag
 *
 * ## Temporal Logic
 *
 * An environment value is considered active when:
 * - start_datetime is NULL or in the past/present
 * - end_datetime is NULL or in the future/present
 *
 * Flags without environment values or with inactive values are included:
 * - No environment value: `null` (not configured)
 * - NULL value in database: Inactive value (explicitly disabled)
 * - Outside temporal window: Inactive value (scheduled/expired)
 *
 * Inactive values by type:
 * - SWITCH: `false`
 * - INTEGER/FLOAT/STRING: `null`
 *
 * ## Type Casting
 *
 * Active flag values are cast based on the phlag type:
 * - SWITCH: boolean (true/false)
 * - INTEGER: integer
 * - FLOAT: float
 * - STRING: string (no casting)
 *
 * ## Usage
 *
 * ```php
 * // GET /get-flags/production
 * // Returns: [{"name": "...", "type": "...", "value": ..., ...}, ...]
 *
 * // GET /get-flags/staging
 * // Returns different values for the same flags in staging environment
 * ```
 *
 * ## Edge Cases
 *
 * - Empty database returns empty array: `[]`
 * - Returns 404 if environment doesn't exist
 * - Flags not configured for environment show null values
 * - All flags are included regardless of configuration status
 * - Datetime fields are converted to ISO 8601 format with timezone
 * - Invalid datetime values in database return null for those fields
 *
 * @package Moonspot\Phlag\Action
 */
class GetFlags extends Base {

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
     * Loads all phlags and returns their complete environment-specific details
     *
     * This method performs the following steps:
     * 1. Authenticate API key (returns 401 if invalid)
     * 2. Find environment by name (404 if not found)
     * 3. Retrieve all flags
     * 4. For each flag, find its environment value
     * 5. Check temporal constraints and build detailed result
     *
     * The response is structured as a JSON array. An empty database returns
     * an empty array which will be encoded as `[]`.
     *
     * Heads-up: We use the FlagValueTrait methods to check temporal
     * constraints and cast values, ensuring complete consistency with
     * the other flag endpoints.
     *
     * Heads-up: This method requires Bearer token authentication via the
     * Authorization header. If authentication fails, a 401 response array
     * is returned.
     *
     * ## Response Structure
     *
     * Each array element contains exactly five fields:
     * - `name`: Flag name (string)
     * - `type`: Flag type (string: SWITCH, INTEGER, FLOAT, STRING)
     * - `value`: Evaluated and typed value (bool, int, float, string, or null)
     * - `start_datetime`: Start constraint (string in ISO 8601 format or null)
     * - `end_datetime`: End constraint (string in ISO 8601 format or null)
     *
     * @return array Response data with all flags and their complete details
     */
    public function loadData(): array {

        $auth_error = $this->authenticateApiKey($this->environment);
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

        // Step 4: Build result array with complete flag details
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

            $value          = null;
            $start_datetime = null;
            $end_datetime   = null;

            // No environment value = not configured
            if (empty($env_values)) {
                $value = null;
            } else {
                $env_value = reset($env_values);

                // NULL value = explicitly disabled
                if ($env_value->value === null) {
                    $value = $this->getInactiveValue($phlag->type);
                } elseif (!$this->isValueActive($env_value, $now)) {
                    // Outside temporal window
                    $value = $this->getInactiveValue($phlag->type);
                } else {
                    // Cast and use active value
                    $value = $this->castValue($env_value->value, $phlag->type);
                }

                // Convert datetime fields to ISO 8601 format
                $start_datetime = $this->formatDatetimeIso8601($env_value->start_datetime);
                $end_datetime   = $this->formatDatetimeIso8601($env_value->end_datetime);
            }

            // Add complete flag details to result array
            $flags_data[] = [
                'name'           => $phlag->name,
                'type'           => $phlag->type,
                'value'          => $value,
                'start_datetime' => $start_datetime,
                'end_datetime'   => $end_datetime,
            ];
        }

        return [
            'http_status' => 200,
            '__raw_value' => $flags_data,
        ];
    }

    /**
     * Standard JSON response
     *
     * Overrides the base respond method to handle raw array output.
     * When the data array contains a __raw_value key, it outputs only
     * that value as JSON. Otherwise, delegates to parent respond method.
     *
     * Heads-up: We use array_key_exists instead of isset because isset
     * returns false when the value is null, which is a valid return value
     * for individual flag values within the array.
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

    /**
     * Formats a datetime string to ISO 8601 format
     *
     * Converts SQL datetime format (Y-m-d H:i:s) to ISO 8601 format
     * (Y-m-d\TH:i:sP). Null values are returned as-is.
     *
     * Heads-up: This assumes the input datetime is in the server's local
     * timezone. The output will include the timezone offset.
     *
     * @param  ?string $datetime Datetime string in SQL format or null
     *
     * @return ?string Datetime string in ISO 8601 format or null
     */
    protected function formatDatetimeIso8601(?string $datetime): ?string {
        $ret = null;

        if ($datetime !== null) {
            $date_obj = \DateTime::createFromFormat('Y-m-d H:i:s', $datetime);
            if ($date_obj !== false) {
                $ret = $date_obj->format('c');
            }
        }

        return $ret;
    }
}
