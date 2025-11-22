<?php

namespace Moonspot\Phlag\Tests\Unit\Action;

use Moonspot\Phlag\Action\GetAllFlags;
use Moonspot\Phlag\Data\Phlag;
use Moonspot\Phlag\Data\PhlagEnvironment;
use Moonspot\Phlag\Data\PhlagEnvironmentValue;
use Moonspot\Phlag\Data\Repository;
use PHPUnit\Framework\TestCase;

/**
 * Tests the GetAllFlags action class
 *
 * This test suite verifies the behavior of the GetAllFlags action which
 * retrieves all flags and returns them as a simple key-value object with
 * flag names as keys and evaluated values for a specific environment.
 *
 * @package Moonspot\Phlag\Tests\Unit\Action
 */
class GetAllFlagsTest extends TestCase {

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
     * Creates a GetAllFlags action with authentication bypassed
     *
     * This helper method creates a partial mock that stubs the
     * authenticateApiKey method to return null (success) so tests can
     * run without actual API keys.
     *
     * @return GetAllFlags Action instance with auth bypassed
     */
    protected function createActionWithAuthBypass(): GetAllFlags {
        $action = $this->getMockBuilder(GetAllFlags::class)
            ->onlyMethods(['authenticateApiKey'])
            ->getMock();
        $action->method('authenticateApiKey')
            ->willReturn(null);

        return $action;
    }

    /**
     * Tests loading data when no flags exist returns empty object
     */
    public function testLoadDataNoFlagsReturnsEmptyArray(): void {
        $action = $this->createActionWithAuthBypass();
        $action->environment = 'production';
        
        $env = $this->createEnvironment('production');
        
        $repository = $this->createMock(Repository::class);
        $repository->method('find')
            ->willReturnCallback(function($entity, $criteria) use ($env) {
                if ($entity === 'PhlagEnvironment') {
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
     * Tests loading single active SWITCH flag
     */
    public function testLoadDataSingleActiveSwitchFlag(): void {
        $phlag = $this->createPhlag(1, 'feature_checkout', 'SWITCH');
        $env = $this->createEnvironment('production');
        $env_value = $this->createEnvironmentValue(1, 'true');
        
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
        $this->assertArrayHasKey('feature_checkout', $result['__raw_value']);
        $this->assertTrue($result['__raw_value']['feature_checkout']);
    }

    /**
     * Tests loading multiple active flags of different types
     */
    public function testLoadDataMultipleFlagsAllTypes(): void {
        $phlags = [
            1 => $this->createPhlag(1, 'feature_flag', 'SWITCH'),
            2 => $this->createPhlag(2, 'max_items', 'INTEGER'),
            3 => $this->createPhlag(3, 'multiplier', 'FLOAT'),
            4 => $this->createPhlag(4, 'message', 'STRING'),
        ];
        
        $env = $this->createEnvironment('production');
        
        $env_values = [
            1 => $this->createEnvironmentValue(1, 'true'),
            2 => $this->createEnvironmentValue(2, '100'),
            3 => $this->createEnvironmentValue(3, '1.5'),
            4 => $this->createEnvironmentValue(4, 'Hello'),
        ];

        $action = $this->createActionWithAuthBypass();
        $action->environment = 'production';
        
        $repository = $this->createMock(Repository::class);
        $repository->method('find')
            ->willReturnCallback(function($entity, $criteria) use ($phlags, $env, $env_values) {
                if ($entity === 'PhlagEnvironment') {
                    return [1 => $env];
                } elseif ($entity === 'Phlag') {
                    return $phlags;
                } elseif ($entity === 'PhlagEnvironmentValue') {
                    if (isset($criteria['phlag_id'])) {
                        $phlag_id = $criteria['phlag_id'];
                        return isset($env_values[$phlag_id]) ? [$phlag_id => $env_values[$phlag_id]] : [];
                    }
                }
                return [];
            });

        $reflection = new \ReflectionClass($action);
        $property = $reflection->getProperty('repository');
        $property->setAccessible(true);
        $property->setValue($action, $repository);

        $result = $action->loadData();

        $this->assertSame(200, $result['http_status']);
        $this->assertCount(4, $result['__raw_value']);
        $this->assertTrue($result['__raw_value']['feature_flag']);
        $this->assertSame(100, $result['__raw_value']['max_items']);
        $this->assertSame(1.5, $result['__raw_value']['multiplier']);
        $this->assertSame('Hello', $result['__raw_value']['message']);
    }

    /**
     * Tests inactive SWITCH flag returns false
     */
    public function testLoadDataInactiveSwitchReturnsFalse(): void {
        $phlag = $this->createPhlag(1, 'scheduled_flag', 'SWITCH');
        $env = $this->createEnvironment('production');
        $env_value = $this->createEnvironmentValue(1, 'true', '2099-01-01 00:00:00', null);

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
        $this->assertArrayHasKey('scheduled_flag', $result['__raw_value']);
        $this->assertFalse($result['__raw_value']['scheduled_flag']);
    }

    /**
     * Tests inactive INTEGER flag returns null
     */
    public function testLoadDataInactiveIntegerReturnsNull(): void {
        $phlag = $this->createPhlag(1, 'old_config', 'INTEGER');
        $env = $this->createEnvironment('production');
        $env_value = $this->createEnvironmentValue(1, '50', null, '2020-01-01 00:00:00');

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
        $this->assertArrayHasKey('old_config', $result['__raw_value']);
        $this->assertNull($result['__raw_value']['old_config']);
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
        
        $env = $this->createEnvironment('production');
        
        $env_values = [
            1 => $this->createEnvironmentValue(1, 'true'),
            2 => $this->createEnvironmentValue(2, 'true', '2099-01-01 00:00:00', null),
            3 => $this->createEnvironmentValue(3, '42'),
            4 => $this->createEnvironmentValue(4, '99', null, '2020-01-01 00:00:00'),
        ];

        $action = $this->createActionWithAuthBypass();
        $action->environment = 'production';
        
        $repository = $this->createMock(Repository::class);
        $repository->method('find')
            ->willReturnCallback(function($entity, $criteria) use ($phlags, $env, $env_values) {
                if ($entity === 'PhlagEnvironment') {
                    return [1 => $env];
                } elseif ($entity === 'Phlag') {
                    return $phlags;
                } elseif ($entity === 'PhlagEnvironmentValue') {
                    if (isset($criteria['phlag_id'])) {
                        $phlag_id = $criteria['phlag_id'];
                        return isset($env_values[$phlag_id]) ? [$phlag_id => $env_values[$phlag_id]] : [];
                    }
                }
                return [];
            });

        $reflection = new \ReflectionClass($action);
        $property = $reflection->getProperty('repository');
        $property->setAccessible(true);
        $property->setValue($action, $repository);

        $result = $action->loadData();

        $this->assertSame(200, $result['http_status']);
        $this->assertCount(4, $result['__raw_value']);
        $this->assertTrue($result['__raw_value']['active_switch']);
        $this->assertFalse($result['__raw_value']['inactive_switch']);
        $this->assertSame(42, $result['__raw_value']['active_int']);
        $this->assertNull($result['__raw_value']['inactive_int']);
    }

    /**
     * Tests flags with null values
     */
    public function testLoadDataNullValues(): void {
        $phlags = [
            1 => $this->createPhlag(1, 'null_switch', 'SWITCH'),
            2 => $this->createPhlag(2, 'null_string', 'STRING'),
        ];
        
        $env = $this->createEnvironment('production');
        
        $env_values = [
            1 => $this->createEnvironmentValue(1, null),
            2 => $this->createEnvironmentValue(2, null),
        ];

        $action = $this->createActionWithAuthBypass();
        $action->environment = 'production';
        
        $repository = $this->createMock(Repository::class);
        $repository->method('find')
            ->willReturnCallback(function($entity, $criteria) use ($phlags, $env, $env_values) {
                if ($entity === 'PhlagEnvironment') {
                    return [1 => $env];
                } elseif ($entity === 'Phlag') {
                    return $phlags;
                } elseif ($entity === 'PhlagEnvironmentValue') {
                    if (isset($criteria['phlag_id'])) {
                        $phlag_id = $criteria['phlag_id'];
                        return isset($env_values[$phlag_id]) ? [$phlag_id => $env_values[$phlag_id]] : [];
                    }
                }
                return [];
            });

        $reflection = new \ReflectionClass($action);
        $property = $reflection->getProperty('repository');
        $property->setAccessible(true);
        $property->setValue($action, $repository);

        $result = $action->loadData();

        $this->assertSame(200, $result['http_status']);
        $this->assertArrayHasKey('null_switch', $result['__raw_value']);
        $this->assertArrayHasKey('null_string', $result['__raw_value']);
        $this->assertFalse($result['__raw_value']['null_switch']); // SWITCH with null value returns false
        $this->assertNull($result['__raw_value']['null_string']);
    }

    /**
     * Tests respond method outputs object as JSON
     */
    public function testRespondOutputsJsonObject(): void {
        $action = $this->createActionWithAuthBypass();
        $action->environment = 'production';

        $data = [
            'http_status' => 200,
            '__raw_value' => [
                'flag1' => true,
                'flag2' => 42,
            ],
        ];

        ob_start();
        $action->respond($data);
        $output = ob_get_clean();

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('flag1', $decoded);
        $this->assertArrayHasKey('flag2', $decoded);
        $this->assertTrue($decoded['flag1']);
        $this->assertSame(42, $decoded['flag2']);
    }

    /**
     * Tests respond method outputs empty object correctly
     */
    public function testRespondOutputsEmptyObject(): void {
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
     * Tests respond method preserves null values in output
     */
    public function testRespondPreservesNullValues(): void {
        $action = $this->createActionWithAuthBypass();
        $action->environment = 'production';

        $data = [
            'http_status' => 200,
            '__raw_value' => [
                'active_flag' => true,
                'inactive_int' => null,
            ],
        ];

        ob_start();
        $action->respond($data);
        $output = ob_get_clean();

        $decoded = json_decode($output, true);
        $this->assertArrayHasKey('active_flag', $decoded);
        $this->assertArrayHasKey('inactive_int', $decoded);
        $this->assertTrue($decoded['active_flag']);
        $this->assertNull($decoded['inactive_int']);
    }
}
