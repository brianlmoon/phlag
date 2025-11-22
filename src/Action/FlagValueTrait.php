<?php

namespace Moonspot\Phlag\Action;

/**
 * Provides shared logic for flag value evaluation and type casting
 *
 * This trait extracts common functionality used by actions that need to
 * evaluate environment-specific flag states and cast their values based on
 * temporal constraints and type definitions.
 *
 * ## Breaking Change (v2.0)
 *
 * This trait now works with PhlagEnvironmentValue objects instead of Phlag
 * objects for temporal evaluation. The `isPhlagActive()` method has been
 * renamed to `isValueActive()` to reflect this change.
 *
 * ## Core Methods
 *
 * 1. **Temporal logic**: Determining if an environment value is currently active
 * 2. **Type casting**: Converting stored string values to typed PHP values
 * 3. **Inactive values**: Getting the appropriate return value for inactive flags
 *
 * ## When to Use This Trait
 *
 * Use this trait in any action class that needs to:
 * - Check if environment values are active based on temporal constraints
 * - Cast flag values from database strings to typed PHP values
 * - Handle inactive flags (SWITCH returns false, others return null)
 *
 * ## Temporal Logic Rules
 *
 * An environment value is considered active when both conditions are met:
 * - start_datetime is NULL or <= current time
 * - end_datetime is NULL or >= current time
 *
 * If either condition fails, the value is inactive.
 *
 * ## Type Casting Rules
 *
 * Values are cast based on the flag type:
 * - SWITCH: boolean (handles "true"/"false" strings and "0"/"1")
 * - INTEGER: integer
 * - FLOAT: float
 * - STRING: no casting (returned as-is)
 *
 * ## Inactive Value Rules
 *
 * When a flag is inactive (no value, NULL value, or outside temporal window):
 * - SWITCH: Returns `false`
 * - INTEGER/FLOAT/STRING: Returns `null`
 *
 * ## Usage Example
 *
 * ```php
 * class MyFlagAction extends Base {
 *     use FlagValueTrait;
 *
 *     public function loadData(): array {
 *         $phlag     = // ... load flag from repository
 *         $env_value = // ... load environment value from repository
 *         $now       = date('Y-m-d H:i:s');
 *
 *         if (!$env_value || $env_value->value === null) {
 *             return ['value' => $this->getInactiveValue($phlag->type)];
 *         }
 *
 *         if (!$this->isValueActive($env_value, $now)) {
 *             return ['value' => $this->getInactiveValue($phlag->type)];
 *         }
 *
 *         $value = $this->castValue($env_value->value, $phlag->type);
 *         return ['value' => $value];
 *     }
 * }
 * ```
 *
 * ## Edge Cases
 *
 * - Null values are always returned as null (no type casting applied)
 * - Missing start_datetime means "active from the beginning of time"
 * - Missing end_datetime means "active until the end of time"
 * - SWITCH values handle both string ("true"/"false") and numeric ("0"/"1") formats
 * - No environment value row = flag not configured (return null)
 * - NULL value in database = flag explicitly disabled (return inactive value)
 *
 * @package Moonspot\Phlag\Action
 */
trait FlagValueTrait {

    /**
     * Checks if an environment value is currently active based on temporal constraints
     *
     * An environment value is active when:
     * - start_datetime is NULL or <= current time
     * - end_datetime is NULL or >= current time
     *
     * Heads-up: Both constraints must pass for the value to be active.
     * If start_datetime is in the future, the value is scheduled but not active.
     * If end_datetime is in the past, the value has expired.
     *
     * ## Breaking Change (v2.0)
     *
     * This method was renamed from `isPhlagActive()` and now accepts
     * PhlagEnvironmentValue instead of Phlag objects.
     *
     * @param  \Moonspot\Phlag\Data\PhlagEnvironmentValue $env_value The environment value to check
     * @param  string                                     $now       Current datetime (Y-m-d H:i:s format)
     *
     * @return bool True if environment value is active, false otherwise
     */
    protected function isValueActive(\Moonspot\Phlag\Data\PhlagEnvironmentValue $env_value, string $now): bool {
        $ret = true;

        if ($env_value->start_datetime !== null && $env_value->start_datetime > $now) {
            $ret = false;
        }

        if ($ret && $env_value->end_datetime !== null && $env_value->end_datetime < $now) {
            $ret = false;
        }

        return $ret;
    }

    /**
     * Casts a phlag value to its appropriate type
     *
     * This method handles type casting based on the phlag type field:
     * - SWITCH: Converts to boolean (handles "true"/"false" strings and numeric values)
     * - INTEGER: Converts to integer
     * - FLOAT: Converts to float
     * - STRING: Returns as-is (no casting needed)
     *
     * Heads-up: Null values are returned as-is without casting. This is important
     * because null is a valid value that indicates "no value set" which is different
     * from an empty string or zero.
     *
     * ## SWITCH Type Casting Details
     *
     * For SWITCH types, we handle multiple string formats:
     * - "true" or "1" → true
     * - "false" or "0" → false
     * - Any other value → PHP boolean casting (empty strings become false)
     *
     * @param  ?string $value The raw value from the database
     * @param  string  $type  The phlag type (SWITCH, INTEGER, FLOAT, STRING)
     *
     * @return mixed The value cast to the appropriate type
     */
    protected function castValue(?string $value, string $type): mixed {
        $ret = $value;

        if ($value === null) {
            return $ret;
        }

        switch ($type) {
            case 'SWITCH':
                if ($value === 'true' || $value === '1') {
                    $ret = true;
                } elseif ($value === 'false' || $value === '0') {
                    $ret = false;
                } else {
                    $ret = (bool)$value;
                }
                break;

            case 'INTEGER':
                $ret = (int)$value;
                break;

            case 'FLOAT':
                $ret = (float)$value;
                break;

            case 'STRING':
            default:
                break;
        }

        return $ret;
    }

    /**
     * Gets the inactive value for a flag type
     *
     * When a flag is inactive (no environment value, NULL value in database,
     * or outside temporal window), this method determines what value to return.
     *
     * ## Return Values by Type
     *
     * - **SWITCH**: Returns `false` (indicates feature is off)
     * - **INTEGER**: Returns `null` (indicates no value available)
     * - **FLOAT**: Returns `null` (indicates no value available)
     * - **STRING**: Returns `null` (indicates no value available)
     *
     * ## Why SWITCH Returns False
     *
     * SWITCH flags represent boolean feature toggles. When a feature is not
     * configured or disabled, the most intuitive behavior is to return `false`
     * rather than `null`, as consumer code typically checks `if ($flag)` without
     * null-checking. This prevents undefined behavior in conditionals.
     *
     * ## Usage Example
     *
     * ```php
     * if (!$env_value || $env_value->value === null) {
     *     return ['value' => $this->getInactiveValue($phlag->type)];
     * }
     * ```
     *
     * @param  string $type The phlag type (SWITCH, INTEGER, FLOAT, STRING)
     *
     * @return mixed False for SWITCH, null for all other types
     */
    protected function getInactiveValue(string $type): mixed {
        $ret = null;

        if ($type === 'SWITCH') {
            $ret = false;
        }

        return $ret;
    }
}
