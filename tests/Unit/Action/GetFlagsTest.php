<?php

namespace Moonspot\Phlag\Tests\Unit\Action;

use Moonspot\Phlag\Action\GetFlags;
use Moonspot\Phlag\Data\Phlag;
use Moonspot\Phlag\Data\PhlagEnvironment;
use Moonspot\Phlag\Data\PhlagEnvironmentValue;
use Moonspot\Phlag\Data\Repository;
use PHPUnit\Framework\TestCase;

/**
 * Tests the GetFlags action class
 *
 * This test suite verifies the behavior of the GetFlags action which
 * retrieves all flags with complete details including name, type, evaluated
 * value, and temporal constraints in ISO 8601 format for a specific environment.
 *
 * @package Moonspot\Phlag\Tests\Unit\Action
 */
class GetFlagsTest extends TestCase {

    /**
     * Creates a test phlag object
     *
     * @param int    $phlag_id Phlag ID
     * @param string $name     Flag name
     * @param string $type     Flag type
     *
     * @return Phlag Test phlag object
     */
    protected function createPhlag(
        int $phlag_id,
        string $name,
        string $type = 'SWITCH'
    ): Phlag {
        $phlag = new Phlag();
        $phlag->phlag_id = $phlag_id;
        $phlag->name = $name;
        $phlag->type = $type;

        return $phlag;
    }

    /**
     * Creates a test environment object
     *
     * @param string $name Environment name
     *
     * @return PhlagEnvironment Test environment object
     */
    protected function createEnvironment(string $name = 'production'): PhlagEnvironment {
        $env = new PhlagEnvironment();
        $env->phlag_environment_id = 1;
        $env->name = $name;

        return $env;
    }

    /**
     * Creates a test environment value object
     *
     * @param int     $phlag_id       Phlag ID
     * @param ?string $value          Flag value
     * @param ?string $start_datetime Start datetime or null
     * @param ?string $end_datetime   End datetime or null
     *
     * @return PhlagEnvironmentValue Test environment value object
     */
    protected function createEnvironmentValue(
        int $phlag_id,
        ?string $value = 'true',
        ?string $start_datetime = null,
        ?string $end_datetime = null
    ): PhlagEnvironmentValue {
        $env_value = new PhlagEnvironmentValue();
        $env_value->phlag_environment_value_id = $phlag_id;
        $env_value->phlag_id = $phlag_id;
        $env_value->phlag_environment_id = 1;
        $env_value->value = $value;
        $env_value->start_datetime = $start_datetime;
        $env_value->end_datetime = $end_datetime;

        return $env_value;
    }

    /**
     * Creates a mock repository for testing GetFlags
     *
     * This helper creates a repository mock that returns the provided phlags
     * and environment values. The phlags array should map phlag_id => Phlag object.
     * The env_values array should map phlag_id => PhlagEnvironmentValue object.
     *
     * @param array $phlags     Array of Phlag objects keyed by phlag_id
     * @param array $env_values Array of PhlagEnvironmentValue objects keyed by phlag_id
     *
     * @return Repository Mock repository
     */
    protected function createMockRepository(array $phlags, array $env_values = []): Repository {
        $env = $this->createEnvironment('production');
        
        $repository = $this->createMock(Repository::class);
        $repository->method('find')
            ->willReturnCallback(function($entity, $criteria) use ($phlags, $env_values, $env) {
                if ($entity === 'PhlagEnvironment') {
                    return [1 => $env];
                } elseif ($entity === 'Phlag') {
                    return $phlags;
                } elseif ($entity === 'PhlagEnvironmentValue') {
                    $phlag_id = $criteria['phlag_id'] ?? null;
                    if ($phlag_id !== null && isset($env_values[$phlag_id])) {
                        return [$phlag_id => $env_values[$phlag_id]];
                    }
                    return [];
                }
                return [];
            });

        return $repository;
    }

    /**
     * Creates a GetFlags action with authentication bypassed
     *
     * This helper method creates a partial mock that stubs the
     * authenticateApiKey method to return null (success) so tests can
     * run without actual API keys.
     *
     * @return GetFlags Action instance with auth bypassed
     */
    protected function createActionWithAuthBypass(): GetFlags {
        $action = $this->getMockBuilder(GetFlags::class)
            ->onlyMethods(['authenticateApiKey'])
            ->getMock();
        $action->method('authenticateApiKey')
            ->willReturn(null);

        return $action;
    }

    /**
     * Tests loading data when no flags exist returns empty array
     */
    public function testLoadDataNoFlagsReturnsEmptyArray(): void {
        $action = $this->createActionWithAuthBypass();
        $action->environment = 'production';
        
        $repository = $this->createMock(Repository::class);
        $repository->method('find')
            ->willReturnCallback(function($entity, $criteria) {
                if ($entity === 'PhlagEnvironment') {
                    $env = new \Moonspot\Phlag\Data\PhlagEnvironment();
                    $env->phlag_environment_id = 1;
                    $env->name = 'production';
                    return [1 => $env];
                }
                return [];
            });

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
        $phlag = $this->createPhlag(1, 'feature_checkout', 'SWITCH');
        $env = $this->createEnvironment('production');
        $env_value = $this->createEnvironmentValue(1, 'true', '2024-01-01 00:00:00', '2099-12-31 23:59:59');

        $action = $this->createActionWithAuthBypass();
        $action->environment = 'production';
        
        $repository = $this->createMock(Repository::class);
        $repository->method('find')
            ->willReturnCallback(function($entity, $criteria) use ($phlag, $env, $env_value) {
                if ($entity === 'PhlagEnvironment') {
                    return [1 => $env];
                } elseif ($entity === 'Phlag') {
                    return [1 => $phlag];
                } elseif ($entity === 'PhlagEnvironmentValue') {
                    return [1 => $env_value];
                }
                return [];
            });

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
            1 => $this->createPhlag(1, 'feature_flag', 'SWITCH'),
            2 => $this->createPhlag(2, 'max_items', 'INTEGER'),
            3 => $this->createPhlag(3, 'multiplier', 'FLOAT'),
            4 => $this->createPhlag(4, 'message', 'STRING'),
        ];

        $env_values = [
            1 => $this->createEnvironmentValue(1, 'true'),
            2 => $this->createEnvironmentValue(2, '100'),
            3 => $this->createEnvironmentValue(3, '1.5'),
            4 => $this->createEnvironmentValue(4, 'Hello'),
        ];

        $action = $this->createActionWithAuthBypass();
        $action->environment = 'production';
        $repository = $this->createMockRepository($phlags, $env_values);

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
        $phlag = $this->createPhlag(1, 'scheduled_flag', 'SWITCH');
        $env_value = $this->createEnvironmentValue(1, 'true', '2099-01-01 00:00:00', null);

        $action = $this->createActionWithAuthBypass();
        $action->environment = 'production';
        $repository = $this->createMockRepository([1 => $phlag], [1 => $env_value]);

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
        $phlag = $this->createPhlag(1, 'old_config', 'INTEGER');
        $env_value = $this->createEnvironmentValue(1, '50', null, '2020-01-01 00:00:00');

        $action = $this->createActionWithAuthBypass();
        $action->environment = 'production';
        $repository = $this->createMockRepository([1 => $phlag], [1 => $env_value]);

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
        $phlag = $this->createPhlag(1, 'no_dates', 'SWITCH');
        $env_value = $this->createEnvironmentValue(1, 'true', null, null);

        $action = $this->createActionWithAuthBypass();
        $action->environment = 'production';
        $repository = $this->createMockRepository([1 => $phlag], [1 => $env_value]);

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
        $phlag = $this->createPhlag(1, 'dated_flag', 'SWITCH');
        $env_value = $this->createEnvironmentValue(1, 'true', '2024-06-15 12:30:45', null);

        $action = $this->createActionWithAuthBypass();
        $action->environment = 'production';
        $repository = $this->createMockRepository([1 => $phlag], [1 => $env_value]);

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
            1 => $this->createPhlag(1, 'active_switch', 'SWITCH'),
            2 => $this->createPhlag(2, 'inactive_switch', 'SWITCH'),
            3 => $this->createPhlag(3, 'active_int', 'INTEGER'),
            4 => $this->createPhlag(4, 'inactive_int', 'INTEGER'),
        ];

        $env_values = [
            1 => $this->createEnvironmentValue(1, 'true'),
            2 => $this->createEnvironmentValue(2, 'true', '2099-01-01 00:00:00', null),
            3 => $this->createEnvironmentValue(3, '42'),
            4 => $this->createEnvironmentValue(4, '99', null, '2020-01-01 00:00:00'),
        ];

        $action = $this->createActionWithAuthBypass();
        $action->environment = 'production';
        $repository = $this->createMockRepository($phlags, $env_values);

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
        $action = $this->createActionWithAuthBypass();
        $action->environment = 'production';

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
        $action = $this->createActionWithAuthBypass();
        $action->environment = 'production';

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
        $action = $this->createActionWithAuthBypass();
        $action->environment = 'production';

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
        $action = $this->createActionWithAuthBypass();
        $action->environment = 'production';

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
        $action = $this->createActionWithAuthBypass();
        $action->environment = 'production';

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
        $phlag = $this->createPhlag(1, 'test_flag', 'SWITCH');
        $env_value = $this->createEnvironmentValue(1, 'true');

        $action = $this->createActionWithAuthBypass();
        $action->environment = 'production';
        $repository = $this->createMockRepository([1 => $phlag], [1 => $env_value]);

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
