<?php

namespace Moonspot\Phlag\Tests\Unit\Action;

use Moonspot\Phlag\Action\FlagValueTrait;
use Moonspot\Phlag\Data\Phlag;
use Moonspot\Phlag\Data\PhlagEnvironmentValue;
use PHPUnit\Framework\TestCase;

/**
 * Tests the FlagValueTrait shared logic for flag evaluation
 *
 * This test suite verifies the temporal constraint checking and type casting
 * logic that is shared across multiple action classes. We test both methods
 * provided by the trait: isPhlagActive() and castValue().
 *
 * @package Moonspot\Phlag\Tests\Unit\Action
 */
class FlagValueTraitTest extends TestCase {

    /**
     * Creates a test class that uses the trait
     *
     * We need to create a concrete class to test the trait since traits
     * cannot be instantiated directly.
     *
     * @return object Test class instance that uses FlagValueTrait
     */
    protected function getTraitUser(): object {
        return new class {
            use FlagValueTrait;

            /**
             * Expose protected isValueActive for testing
             */
            public function testIsValueActive(PhlagEnvironmentValue $env_value, string $now): bool {
                return $this->isValueActive($env_value, $now);
            }

            /**
             * Expose protected castValue for testing
             */
            public function testCastValue(?string $value, string $type): mixed {
                return $this->castValue($value, $type);
            }

            /**
             * Expose protected getInactiveValue for testing
             */
            public function testGetInactiveValue(string $type): mixed {
                return $this->getInactiveValue($type);
            }
        };
    }

    /**
     * Creates a test environment value object
     *
     * @param ?string $value          Flag value
     * @param ?string $start_datetime Start datetime or null
     * @param ?string $end_datetime   End datetime or null
     *
     * @return PhlagEnvironmentValue Test environment value object
     */
    protected function createEnvironmentValue(
        ?string $value = 'true',
        ?string $start_datetime = null,
        ?string $end_datetime = null
    ): PhlagEnvironmentValue {
        $env_value = new PhlagEnvironmentValue();
        $env_value->phlag_environment_value_id = 1;
        $env_value->phlag_id = 1;
        $env_value->phlag_environment_id = 1;
        $env_value->value = $value;
        $env_value->start_datetime = $start_datetime;
        $env_value->end_datetime = $end_datetime;

        return $env_value;
    }

    /**
     * Tests that a value with no temporal constraints is always active
     */
    public function testIsPhlagActiveNoConstraints(): void {
        $trait_user = $this->getTraitUser();
        $env_value = $this->createEnvironmentValue();
        $now = '2024-06-15 12:00:00';

        $result = $trait_user->testIsValueActive($env_value, $now);

        $this->assertTrue($result, 'Value with no temporal constraints should be active');
    }

    /**
     * Tests that a value is active when current time is after start date
     */
    public function testIsPhlagActiveAfterStartDate(): void {
        $trait_user = $this->getTraitUser();
        $env_value = $this->createEnvironmentValue('true', '2024-01-01 00:00:00', null);
        $now = '2024-06-15 12:00:00';

        $result = $trait_user->testIsValueActive($env_value, $now);

        $this->assertTrue($result, 'Value should be active when current time is after start date');
    }

    /**
     * Tests that a value is inactive when current time is before start date
     */
    public function testIsPhlagActiveBeforeStartDate(): void {
        $trait_user = $this->getTraitUser();
        $env_value = $this->createEnvironmentValue('true', '2099-01-01 00:00:00', null);
        $now = '2024-06-15 12:00:00';

        $result = $trait_user->testIsValueActive($env_value, $now);

        $this->assertFalse($result, 'Value should be inactive when current time is before start date');
    }

    /**
     * Tests that a value is active when current time is before end date
     */
    public function testIsPhlagActiveBeforeEndDate(): void {
        $trait_user = $this->getTraitUser();
        $env_value = $this->createEnvironmentValue('true', null, '2099-12-31 23:59:59');
        $now = '2024-06-15 12:00:00';

        $result = $trait_user->testIsValueActive($env_value, $now);

        $this->assertTrue($result, 'Value should be active when current time is before end date');
    }

    /**
     * Tests that a value is inactive when current time is after end date
     */
    public function testIsPhlagActiveAfterEndDate(): void {
        $trait_user = $this->getTraitUser();
        $env_value = $this->createEnvironmentValue('true', null, '2020-01-01 00:00:00');
        $now = '2024-06-15 12:00:00';

        $result = $trait_user->testIsValueActive($env_value, $now);

        $this->assertFalse($result, 'Value should be inactive when current time is after end date');
    }

    /**
     * Tests that a value is active when current time is within date range
     */
    public function testIsPhlagActiveWithinDateRange(): void {
        $trait_user = $this->getTraitUser();
        $env_value = $this->createEnvironmentValue('true', '2024-01-01 00:00:00', '2099-12-31 23:59:59');
        $now = '2024-06-15 12:00:00';

        $result = $trait_user->testIsValueActive($env_value, $now);

        $this->assertTrue($result, 'Value should be active when current time is within date range');
    }

    /**
     * Tests that a value is active exactly at the start datetime
     */
    public function testIsPhlagActiveAtStartDatetime(): void {
        $trait_user = $this->getTraitUser();
        $env_value = $this->createEnvironmentValue('true', '2024-06-15 12:00:00', null);
        $now = '2024-06-15 12:00:00';

        $result = $trait_user->testIsValueActive($env_value, $now);

        $this->assertTrue($result, 'Value should be active exactly at start datetime');
    }

    /**
     * Tests that a value is active exactly at the end datetime
     */
    public function testIsPhlagActiveAtEndDatetime(): void {
        $trait_user = $this->getTraitUser();
        $env_value = $this->createEnvironmentValue('true', null, '2024-06-15 12:00:00');
        $now = '2024-06-15 12:00:00';

        $result = $trait_user->testIsValueActive($env_value, $now);

        $this->assertTrue($result, 'Value should be active exactly at end datetime');
    }

    /**
     * Tests casting SWITCH type with string "true"
     */
    public function testCastValueSwitchTrue(): void {
        $trait_user = $this->getTraitUser();

        $result = $trait_user->testCastValue('true', 'SWITCH');

        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    /**
     * Tests casting SWITCH type with string "false"
     */
    public function testCastValueSwitchFalse(): void {
        $trait_user = $this->getTraitUser();

        $result = $trait_user->testCastValue('false', 'SWITCH');

        $this->assertIsBool($result);
        $this->assertFalse($result);
    }

    /**
     * Tests casting SWITCH type with string "1"
     */
    public function testCastValueSwitchOne(): void {
        $trait_user = $this->getTraitUser();

        $result = $trait_user->testCastValue('1', 'SWITCH');

        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    /**
     * Tests casting SWITCH type with string "0"
     */
    public function testCastValueSwitchZero(): void {
        $trait_user = $this->getTraitUser();

        $result = $trait_user->testCastValue('0', 'SWITCH');

        $this->assertIsBool($result);
        $this->assertFalse($result);
    }

    /**
     * Tests casting INTEGER type
     */
    public function testCastValueInteger(): void {
        $trait_user = $this->getTraitUser();

        $result = $trait_user->testCastValue('123', 'INTEGER');

        $this->assertIsInt($result);
        $this->assertSame(123, $result);
    }

    /**
     * Tests casting FLOAT type
     */
    public function testCastValueFloat(): void {
        $trait_user = $this->getTraitUser();

        $result = $trait_user->testCastValue('3.14', 'FLOAT');

        $this->assertIsFloat($result);
        $this->assertSame(3.14, $result);
    }

    /**
     * Tests casting STRING type
     */
    public function testCastValueString(): void {
        $trait_user = $this->getTraitUser();

        $result = $trait_user->testCastValue('hello', 'STRING');

        $this->assertIsString($result);
        $this->assertSame('hello', $result);
    }

    /**
     * Tests casting null value returns null regardless of type
     */
    public function testCastValueNull(): void {
        $trait_user = $this->getTraitUser();

        $result_switch = $trait_user->testCastValue(null, 'SWITCH');
        $result_int = $trait_user->testCastValue(null, 'INTEGER');
        $result_float = $trait_user->testCastValue(null, 'FLOAT');
        $result_string = $trait_user->testCastValue(null, 'STRING');

        $this->assertNull($result_switch);
        $this->assertNull($result_int);
        $this->assertNull($result_float);
        $this->assertNull($result_string);
    }

    /**
     * Tests casting negative integer
     */
    public function testCastValueNegativeInteger(): void {
        $trait_user = $this->getTraitUser();

        $result = $trait_user->testCastValue('-42', 'INTEGER');

        $this->assertIsInt($result);
        $this->assertSame(-42, $result);
    }

    /**
     * Tests casting negative float
     */
    public function testCastValueNegativeFloat(): void {
        $trait_user = $this->getTraitUser();

        $result = $trait_user->testCastValue('-2.5', 'FLOAT');

        $this->assertIsFloat($result);
        $this->assertSame(-2.5, $result);
    }

    /**
     * Tests casting empty string for SWITCH falls back to PHP boolean casting
     */
    public function testCastValueSwitchEmptyString(): void {
        $trait_user = $this->getTraitUser();

        $result = $trait_user->testCastValue('', 'SWITCH');

        $this->assertIsBool($result);
        $this->assertFalse($result);
    }

    /**
     * Tests casting unknown type returns value as-is
     */
    public function testCastValueUnknownType(): void {
        $trait_user = $this->getTraitUser();

        $result = $trait_user->testCastValue('test', 'UNKNOWN');

        $this->assertSame('test', $result);
    }
}
