<?php

namespace Moonspot\Phlag\Tests\Unit\Action;

use Moonspot\Phlag\Action\FlagValueTrait;
use Moonspot\Phlag\Data\Phlag;
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
             * Expose protected isPhlagActive for testing
             */
            public function testIsPhlagActive(Phlag $phlag, string $now): bool {
                return $this->isPhlagActive($phlag, $now);
            }

            /**
             * Expose protected castValue for testing
             */
            public function testCastValue(?string $value, ?string $type): mixed {
                return $this->castValue($value, $type);
            }
        };
    }

    /**
     * Creates a test phlag object
     *
     * @param ?string $start_datetime Start datetime or null
     * @param ?string $end_datetime   End datetime or null
     *
     * @return Phlag Test phlag object
     */
    protected function createPhlag(
        ?string $start_datetime = null,
        ?string $end_datetime = null
    ): Phlag {
        $phlag = new Phlag();
        $phlag->phlag_id = 1;
        $phlag->name = 'test_flag';
        $phlag->type = 'SWITCH';
        $phlag->value = 'true';
        $phlag->start_datetime = $start_datetime;
        $phlag->end_datetime = $end_datetime;

        return $phlag;
    }

    /**
     * Tests that a flag with no temporal constraints is always active
     */
    public function testIsPhlagActiveNoConstraints(): void {
        $trait_user = $this->getTraitUser();
        $phlag = $this->createPhlag();
        $now = '2024-06-15 12:00:00';

        $result = $trait_user->testIsPhlagActive($phlag, $now);

        $this->assertTrue($result, 'Flag with no temporal constraints should be active');
    }

    /**
     * Tests that a flag is active when current time is after start date
     */
    public function testIsPhlagActiveAfterStartDate(): void {
        $trait_user = $this->getTraitUser();
        $phlag = $this->createPhlag('2024-01-01 00:00:00', null);
        $now = '2024-06-15 12:00:00';

        $result = $trait_user->testIsPhlagActive($phlag, $now);

        $this->assertTrue($result, 'Flag should be active when current time is after start date');
    }

    /**
     * Tests that a flag is inactive when current time is before start date
     */
    public function testIsPhlagActiveBeforeStartDate(): void {
        $trait_user = $this->getTraitUser();
        $phlag = $this->createPhlag('2024-12-01 00:00:00', null);
        $now = '2024-06-15 12:00:00';

        $result = $trait_user->testIsPhlagActive($phlag, $now);

        $this->assertFalse($result, 'Flag should be inactive when current time is before start date');
    }

    /**
     * Tests that a flag is active when current time is before end date
     */
    public function testIsPhlagActiveBeforeEndDate(): void {
        $trait_user = $this->getTraitUser();
        $phlag = $this->createPhlag(null, '2024-12-31 23:59:59');
        $now = '2024-06-15 12:00:00';

        $result = $trait_user->testIsPhlagActive($phlag, $now);

        $this->assertTrue($result, 'Flag should be active when current time is before end date');
    }

    /**
     * Tests that a flag is inactive when current time is after end date
     */
    public function testIsPhlagActiveAfterEndDate(): void {
        $trait_user = $this->getTraitUser();
        $phlag = $this->createPhlag(null, '2024-01-31 23:59:59');
        $now = '2024-06-15 12:00:00';

        $result = $trait_user->testIsPhlagActive($phlag, $now);

        $this->assertFalse($result, 'Flag should be inactive when current time is after end date');
    }

    /**
     * Tests that a flag is active when current time is within date range
     */
    public function testIsPhlagActiveWithinDateRange(): void {
        $trait_user = $this->getTraitUser();
        $phlag = $this->createPhlag('2024-01-01 00:00:00', '2024-12-31 23:59:59');
        $now = '2024-06-15 12:00:00';

        $result = $trait_user->testIsPhlagActive($phlag, $now);

        $this->assertTrue($result, 'Flag should be active when current time is within date range');
    }

    /**
     * Tests that a flag is active at exact start datetime
     */
    public function testIsPhlagActiveAtStartDatetime(): void {
        $trait_user = $this->getTraitUser();
        $phlag = $this->createPhlag('2024-06-15 12:00:00', null);
        $now = '2024-06-15 12:00:00';

        $result = $trait_user->testIsPhlagActive($phlag, $now);

        $this->assertTrue($result, 'Flag should be active at exact start datetime');
    }

    /**
     * Tests that a flag is active at exact end datetime
     */
    public function testIsPhlagActiveAtEndDatetime(): void {
        $trait_user = $this->getTraitUser();
        $phlag = $this->createPhlag(null, '2024-06-15 12:00:00');
        $now = '2024-06-15 12:00:00';

        $result = $trait_user->testIsPhlagActive($phlag, $now);

        $this->assertTrue($result, 'Flag should be active at exact end datetime');
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
     * Tests casting with null type returns value as-is
     */
    public function testCastValueNullType(): void {
        $trait_user = $this->getTraitUser();

        $result = $trait_user->testCastValue('test', null);

        $this->assertSame('test', $result);
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
