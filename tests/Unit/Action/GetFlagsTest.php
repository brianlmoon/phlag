<?php

namespace Moonspot\Phlag\Tests\Unit\Action;

use Moonspot\Phlag\Action\GetFlags;
use Moonspot\Phlag\Data\Phlag;
use Moonspot\Phlag\Data\Repository;
use PHPUnit\Framework\TestCase;

/**
 * Tests the GetFlags action class
 *
 * This test suite verifies the behavior of the GetFlags action which
 * retrieves all flags with complete details including name, type, evaluated
 * value, and temporal constraints in ISO 8601 format.
 *
 * @package Moonspot\Phlag\Tests\Unit\Action
 */
class GetFlagsTest extends TestCase {

    /**
     * Creates a test phlag object
     *
     * @param int     $phlag_id        Phlag ID
     * @param string  $name            Flag name
     * @param string  $type            Flag type
     * @param ?string $value           Flag value
     * @param ?string $start_datetime  Start datetime or null
     * @param ?string $end_datetime    End datetime or null
     *
     * @return Phlag Test phlag object
     */
    protected function createPhlag(
        int $phlag_id,
        string $name,
        string $type = 'SWITCH',
        ?string $value = 'true',
        ?string $start_datetime = null,
        ?string $end_datetime = null
    ): Phlag {
        $phlag = new Phlag();
        $phlag->phlag_id = $phlag_id;
        $phlag->name = $name;
        $phlag->type = $type;
        $phlag->value = $value;
        $phlag->start_datetime = $start_datetime;
        $phlag->end_datetime = $end_datetime;

        return $phlag;
    }

    /**
     * Creates a mock repository
     *
     * @param array $results Results to return from find()
     *
     * @return Repository Mock repository
     */
    protected function createMockRepository(array $results): Repository {
        $repository = $this->createMock(Repository::class);
        $repository->method('find')
            ->willReturn($results);

        return $repository;
    }

    /**
     * Tests loading data when no flags exist returns empty array
     */
    public function testLoadDataNoFlagsReturnsEmptyArray(): void {
        $action = new GetFlags();
        $repository = $this->createMockRepository([]);

        $reflection = new \ReflectionClass($action);
        $property = $reflection->getProperty('repository');
        $property->setAccessible(true);
        $property->setValue($action, $repository);

        $result = $action->loadData();

        $this->assertSame(200, $result['http_status']);
        $this->assertIsArray($result['__raw_value']);
        $this->assertEmpty($result['__raw_value']);
    }

    /**
     * Tests loading single active flag returns complete details
     */
    public function testLoadDataSingleFlagWithDetails(): void {
        $phlag = $this->createPhlag(
            1,
            'feature_checkout',
            'SWITCH',
            'true',
            '2024-01-01 00:00:00',
            '2099-12-31 23:59:59'
        );

        $action = new GetFlags();
        $repository = $this->createMockRepository([1 => $phlag]);

        $reflection = new \ReflectionClass($action);
        $property = $reflection->getProperty('repository');
        $property->setAccessible(true);
        $property->setValue($action, $repository);

        $result = $action->loadData();

        $this->assertSame(200, $result['http_status']);
        $this->assertCount(1, $result['__raw_value']);

        $flag = $result['__raw_value'][0];
        $this->assertArrayHasKey('name', $flag);
        $this->assertArrayHasKey('type', $flag);
        $this->assertArrayHasKey('value', $flag);
        $this->assertArrayHasKey('start_datetime', $flag);
        $this->assertArrayHasKey('end_datetime', $flag);

        $this->assertSame('feature_checkout', $flag['name']);
        $this->assertSame('SWITCH', $flag['type']);
        $this->assertTrue($flag['value']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $flag['start_datetime']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $flag['end_datetime']);
    }

    /**
     * Tests loading multiple flags of all types
     */
    public function testLoadDataMultipleFlagsAllTypes(): void {
        $phlags = [
            1 => $this->createPhlag(1, 'feature_flag', 'SWITCH', 'true'),
            2 => $this->createPhlag(2, 'max_items', 'INTEGER', '100'),
            3 => $this->createPhlag(3, 'multiplier', 'FLOAT', '1.5'),
            4 => $this->createPhlag(4, 'message', 'STRING', 'Hello'),
        ];

        $action = new GetFlags();
        $repository = $this->createMockRepository($phlags);

        $reflection = new \ReflectionClass($action);
        $property = $reflection->getProperty('repository');
        $property->setAccessible(true);
        $property->setValue($action, $repository);

        $result = $action->loadData();

        $this->assertSame(200, $result['http_status']);
        $this->assertCount(4, $result['__raw_value']);

        $flags_by_name = [];
        foreach ($result['__raw_value'] as $flag) {
            $flags_by_name[$flag['name']] = $flag;
        }

        $this->assertTrue($flags_by_name['feature_flag']['value']);
        $this->assertSame('SWITCH', $flags_by_name['feature_flag']['type']);

        $this->assertSame(100, $flags_by_name['max_items']['value']);
        $this->assertSame('INTEGER', $flags_by_name['max_items']['type']);

        $this->assertSame(1.5, $flags_by_name['multiplier']['value']);
        $this->assertSame('FLOAT', $flags_by_name['multiplier']['type']);

        $this->assertSame('Hello', $flags_by_name['message']['value']);
        $this->assertSame('STRING', $flags_by_name['message']['type']);
    }

    /**
     * Tests inactive SWITCH flag returns false
     */
    public function testLoadDataInactiveSwitchReturnsFalse(): void {
        $phlag = $this->createPhlag(
            1,
            'scheduled_flag',
            'SWITCH',
            'true',
            '2099-01-01 00:00:00',
            null
        );

        $action = new GetFlags();
        $repository = $this->createMockRepository([1 => $phlag]);

        $reflection = new \ReflectionClass($action);
        $property = $reflection->getProperty('repository');
        $property->setAccessible(true);
        $property->setValue($action, $repository);

        $result = $action->loadData();

        $this->assertSame(200, $result['http_status']);
        $flag = $result['__raw_value'][0];
        $this->assertFalse($flag['value']);
        $this->assertSame('SWITCH', $flag['type']);
    }

    /**
     * Tests inactive INTEGER flag returns null
     */
    public function testLoadDataInactiveIntegerReturnsNull(): void {
        $phlag = $this->createPhlag(
            1,
            'old_config',
            'INTEGER',
            '50',
            null,
            '2020-01-01 00:00:00'
        );

        $action = new GetFlags();
        $repository = $this->createMockRepository([1 => $phlag]);

        $reflection = new \ReflectionClass($action);
        $property = $reflection->getProperty('repository');
        $property->setAccessible(true);
        $property->setValue($action, $repository);

        $result = $action->loadData();

        $this->assertSame(200, $result['http_status']);
        $flag = $result['__raw_value'][0];
        $this->assertNull($flag['value']);
        $this->assertSame('INTEGER', $flag['type']);
    }

    /**
     * Tests null datetime fields are preserved as null
     */
    public function testLoadDataNullDatetimesPreserved(): void {
        $phlag = $this->createPhlag(1, 'no_dates', 'SWITCH', 'true', null, null);

        $action = new GetFlags();
        $repository = $this->createMockRepository([1 => $phlag]);

        $reflection = new \ReflectionClass($action);
        $property = $reflection->getProperty('repository');
        $property->setAccessible(true);
        $property->setValue($action, $repository);

        $result = $action->loadData();

        $flag = $result['__raw_value'][0];
        $this->assertNull($flag['start_datetime']);
        $this->assertNull($flag['end_datetime']);
    }

    /**
     * Tests datetime conversion to ISO 8601 format
     */
    public function testLoadDataDatetimeIso8601Format(): void {
        $phlag = $this->createPhlag(
            1,
            'dated_flag',
            'SWITCH',
            'true',
            '2024-06-15 12:30:45',
            null
        );

        $action = new GetFlags();
        $repository = $this->createMockRepository([1 => $phlag]);

        $reflection = new \ReflectionClass($action);
        $property = $reflection->getProperty('repository');
        $property->setAccessible(true);
        $property->setValue($action, $repository);

        $result = $action->loadData();

        $flag = $result['__raw_value'][0];

        // Verify ISO 8601 format with timezone
        $this->assertMatchesRegularExpression(
            '/^2024-06-15T12:30:45[+-]\d{2}:\d{2}$/',
            $flag['start_datetime']
        );
    }

    /**
     * Tests mix of active and inactive flags
     */
    public function testLoadDataMixedActiveInactive(): void {
        $phlags = [
            1 => $this->createPhlag(1, 'active_switch', 'SWITCH', 'true'),
            2 => $this->createPhlag(
                2,
                'inactive_switch',
                'SWITCH',
                'true',
                '2099-01-01 00:00:00',
                null
            ),
            3 => $this->createPhlag(3, 'active_int', 'INTEGER', '42'),
            4 => $this->createPhlag(
                4,
                'inactive_int',
                'INTEGER',
                '99',
                null,
                '2020-01-01 00:00:00'
            ),
        ];

        $action = new GetFlags();
        $repository = $this->createMockRepository($phlags);

        $reflection = new \ReflectionClass($action);
        $property = $reflection->getProperty('repository');
        $property->setAccessible(true);
        $property->setValue($action, $repository);

        $result = $action->loadData();

        $this->assertSame(200, $result['http_status']);
        $this->assertCount(4, $result['__raw_value']);

        $flags_by_name = [];
        foreach ($result['__raw_value'] as $flag) {
            $flags_by_name[$flag['name']] = $flag;
        }

        $this->assertTrue($flags_by_name['active_switch']['value']);
        $this->assertFalse($flags_by_name['inactive_switch']['value']);
        $this->assertSame(42, $flags_by_name['active_int']['value']);
        $this->assertNull($flags_by_name['inactive_int']['value']);
    }

    /**
     * Tests respond method outputs array as JSON
     */
    public function testRespondOutputsJsonArray(): void {
        $action = new GetFlags();

        $data = [
            'http_status' => 200,
            '__raw_value' => [
                [
                    'name' => 'flag1',
                    'type' => 'SWITCH',
                    'value' => true,
                    'start_datetime' => null,
                    'end_datetime' => null,
                ],
            ],
        ];

        ob_start();
        $action->respond($data);
        $output = ob_get_clean();

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
        $this->assertCount(1, $decoded);
        $this->assertSame('flag1', $decoded[0]['name']);
        $this->assertTrue($decoded[0]['value']);
    }

    /**
     * Tests respond method outputs empty array correctly
     */
    public function testRespondOutputsEmptyArray(): void {
        $action = new GetFlags();

        $data = [
            'http_status' => 200,
            '__raw_value' => [],
        ];

        ob_start();
        $action->respond($data);
        $output = ob_get_clean();

        $this->assertSame('[]', $output);
    }

    /**
     * Tests formatDatetimeIso8601 method with valid datetime
     */
    public function testFormatDatetimeIso8601ValidDatetime(): void {
        $action = new GetFlags();

        $reflection = new \ReflectionClass($action);
        $method = $reflection->getMethod('formatDatetimeIso8601');
        $method->setAccessible(true);

        $result = $method->invoke($action, '2024-06-15 12:30:45');

        $this->assertIsString($result);
        $this->assertMatchesRegularExpression(
            '/^2024-06-15T12:30:45[+-]\d{2}:\d{2}$/',
            $result
        );
    }

    /**
     * Tests formatDatetimeIso8601 method with null
     */
    public function testFormatDatetimeIso8601Null(): void {
        $action = new GetFlags();

        $reflection = new \ReflectionClass($action);
        $method = $reflection->getMethod('formatDatetimeIso8601');
        $method->setAccessible(true);

        $result = $method->invoke($action, null);

        $this->assertNull($result);
    }

    /**
     * Tests formatDatetimeIso8601 method with invalid datetime returns null
     */
    public function testFormatDatetimeIso8601InvalidDatetime(): void {
        $action = new GetFlags();

        $reflection = new \ReflectionClass($action);
        $method = $reflection->getMethod('formatDatetimeIso8601');
        $method->setAccessible(true);

        $result = $method->invoke($action, 'invalid-date');

        $this->assertNull($result);
    }

    /**
     * Tests that all flag objects have exactly 5 fields
     */
    public function testLoadDataFlagObjectsHaveFiveFields(): void {
        $phlag = $this->createPhlag(1, 'test_flag', 'SWITCH', 'true');

        $action = new GetFlags();
        $repository = $this->createMockRepository([1 => $phlag]);

        $reflection = new \ReflectionClass($action);
        $property = $reflection->getProperty('repository');
        $property->setAccessible(true);
        $property->setValue($action, $repository);

        $result = $action->loadData();

        $flag = $result['__raw_value'][0];
        $this->assertCount(5, $flag);
        $this->assertArrayHasKey('name', $flag);
        $this->assertArrayHasKey('type', $flag);
        $this->assertArrayHasKey('value', $flag);
        $this->assertArrayHasKey('start_datetime', $flag);
        $this->assertArrayHasKey('end_datetime', $flag);
    }
}
