<?php

namespace Moonspot\Phlag\Tests\Unit\Action;

use Moonspot\Phlag\Action\GetPhlagState;
use Moonspot\Phlag\Data\Phlag;
use Moonspot\Phlag\Data\PhlagEnvironment;
use Moonspot\Phlag\Data\PhlagEnvironmentValue;
use Moonspot\Phlag\Data\Repository;
use PHPUnit\Framework\TestCase;

/**
 * Tests the GetPhlagState action class
 *
 * This test suite verifies the behavior of the GetPhlagState action which
 * retrieves a single flag's value by name and environment, evaluating it
 * based on temporal constraints and type casting.
 *
 * @package Moonspot\Phlag\Tests\Unit\Action
 */
class GetPhlagStateTest extends TestCase {

    /**
     * Creates a test phlag object
     *
     * @param string $name Flag name
     * @param string $type Flag type
     *
     * @return Phlag Test phlag object
     */
    protected function createPhlag(
        string $name = 'test_flag',
        string $type = 'SWITCH'
    ): Phlag {
        $phlag = new Phlag();
        $phlag->phlag_id = 1;
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
     * Creates a GetPhlagState action with authentication bypassed
     *
     * This helper method creates a partial mock that stubs the
     * authenticateApiKey method to return null (success) so tests can
     * run without actual API keys.
     *
     * @return GetPhlagState Action instance with auth bypassed
     */
    protected function createActionWithAuthBypass(): GetPhlagState {
        $action = $this->getMockBuilder(GetPhlagState::class)
            ->onlyMethods(['authenticateApiKey'])
            ->getMock();
        $action->method('authenticateApiKey')
            ->willReturn(null);

        return $action;
    }

    /**
     * Creates a mock repository that responds to different find() calls
     *
     * This sets up the repository to return appropriate data for each
     * type of entity: Phlag, PhlagEnvironment, and PhlagEnvironmentValue.
     *
     * @param ?Phlag                   $phlag     Phlag to return or null
     * @param ?PhlagEnvironment        $env       Environment to return or null
     * @param ?PhlagEnvironmentValue   $env_value Environment value to return or null
     *
     * @return Repository Mock repository
     */
    protected function createMockRepositoryFor(
        ?Phlag $phlag = null,
        ?PhlagEnvironment $env = null,
        ?PhlagEnvironmentValue $env_value = null
    ): Repository {
        $repository = $this->createMock(Repository::class);
        
        $repository->method('find')
            ->willReturnCallback(function($entity, $criteria) use ($phlag, $env, $env_value) {
                if ($entity === 'Phlag' && $phlag !== null) {
                    return [1 => $phlag];
                } elseif ($entity === 'PhlagEnvironment' && $env !== null) {
                    return [1 => $env];
                } elseif ($entity === 'PhlagEnvironmentValue' && $env_value !== null) {
                    return [1 => $env_value];
                }
                return [];
            });

        return $repository;
    }

    /**
     * Tests loading data when flag does not exist returns null
     */
    public function testLoadDataFlagNotFoundReturnsNull(): void {
        $action = $this->createActionWithAuthBypass();
        $action->name = 'nonexistent_flag';
        $action->environment = 'production';

        $env = $this->createEnvironment('production');
        $repository = $this->createMockRepositoryFor(null, $env, null);

        $reflection = new \ReflectionClass($action);
        $property = $reflection->getProperty('repository');
        $property->setAccessible(true);
        $property->setValue($action, $repository);

        $result = $action->loadData();

        $this->assertArrayHasKey('http_status', $result);
        $this->assertSame(200, $result['http_status']);
        $this->assertArrayHasKey('__raw_value', $result);
        $this->assertNull($result['__raw_value']);
    }

    /**
     * Tests loading active SWITCH flag returns boolean true
     */
    public function testLoadDataActiveSwitchTrue(): void {
        $phlag = $this->createPhlag('feature_flag', 'SWITCH');
        $env = $this->createEnvironment('production');
        $env_value = $this->createEnvironmentValue('true');
        
        $action = $this->createActionWithAuthBypass();
        $action->name = 'feature_flag';
        $action->environment = 'production';

        $repository = $this->createMockRepositoryFor($phlag, $env, $env_value);

        $reflection = new \ReflectionClass($action);
        $property = $reflection->getProperty('repository');
        $property->setAccessible(true);
        $property->setValue($action, $repository);

        $result = $action->loadData();

        $this->assertSame(200, $result['http_status']);
        $this->assertIsBool($result['__raw_value']);
        $this->assertTrue($result['__raw_value']);
    }

    /**
     * Tests loading active SWITCH flag returns boolean false
     */
    public function testLoadDataActiveSwitchFalse(): void {
        $phlag = $this->createPhlag('feature_flag', 'SWITCH');
        $env = $this->createEnvironment('production');
        $env_value = $this->createEnvironmentValue('false');
        
        $action = $this->createActionWithAuthBypass();
        $action->name = 'feature_flag';
        $action->environment = 'production';

        $repository = $this->createMockRepositoryFor($phlag, $env, $env_value);

        $reflection = new \ReflectionClass($action);
        $property = $reflection->getProperty('repository');
        $property->setAccessible(true);
        $property->setValue($action, $repository);

        $result = $action->loadData();

        $this->assertSame(200, $result['http_status']);
        $this->assertIsBool($result['__raw_value']);
        $this->assertFalse($result['__raw_value']);
    }

    /**
     * Tests loading active INTEGER flag returns integer
     */
    public function testLoadDataActiveInteger(): void {
        $phlag = $this->createPhlag('max_items', 'INTEGER');
        $env = $this->createEnvironment('production');
        $env_value = $this->createEnvironmentValue('100');
        
        $action = $this->createActionWithAuthBypass();
        $action->name = 'max_items';
        $action->environment = 'production';

        $repository = $this->createMockRepositoryFor($phlag, $env, $env_value);

        $reflection = new \ReflectionClass($action);
        $property = $reflection->getProperty('repository');
        $property->setAccessible(true);
        $property->setValue($action, $repository);

        $result = $action->loadData();

        $this->assertSame(200, $result['http_status']);
        $this->assertIsInt($result['__raw_value']);
        $this->assertSame(100, $result['__raw_value']);
    }

    /**
     * Tests loading active FLOAT flag returns float
     */
    public function testLoadDataActiveFloat(): void {
        $phlag = $this->createPhlag('multiplier', 'FLOAT');
        $env = $this->createEnvironment('production');
        $env_value = $this->createEnvironmentValue('1.5');
        
        $action = $this->createActionWithAuthBypass();
        $action->name = 'multiplier';
        $action->environment = 'production';

        $repository = $this->createMockRepositoryFor($phlag, $env, $env_value);

        $reflection = new \ReflectionClass($action);
        $property = $reflection->getProperty('repository');
        $property->setAccessible(true);
        $property->setValue($action, $repository);

        $result = $action->loadData();

        $this->assertSame(200, $result['http_status']);
        $this->assertIsFloat($result['__raw_value']);
        $this->assertSame(1.5, $result['__raw_value']);
    }

    /**
     * Tests loading active STRING flag returns string
     */
    public function testLoadDataActiveString(): void {
        $phlag = $this->createPhlag('message', 'STRING');
        $env = $this->createEnvironment('production');
        $env_value = $this->createEnvironmentValue('Hello World');
        
        $action = $this->createActionWithAuthBypass();
        $action->name = 'message';
        $action->environment = 'production';

        $repository = $this->createMockRepositoryFor($phlag, $env, $env_value);

        $reflection = new \ReflectionClass($action);
        $property = $reflection->getProperty('repository');
        $property->setAccessible(true);
        $property->setValue($action, $repository);

        $result = $action->loadData();

        $this->assertSame(200, $result['http_status']);
        $this->assertIsString($result['__raw_value']);
        $this->assertSame('Hello World', $result['__raw_value']);
    }

    /**
     * Tests inactive SWITCH flag returns false
     */
    public function testLoadDataInactiveSwitchReturnsFalse(): void {
        $phlag = $this->createPhlag('scheduled_flag', 'SWITCH');
        $env = $this->createEnvironment('production');
        $env_value = $this->createEnvironmentValue('true', '2099-01-01 00:00:00', null);
        
        $action = $this->createActionWithAuthBypass();
        $action->name = 'scheduled_flag';
        $action->environment = 'production';

        $repository = $this->createMockRepositoryFor($phlag, $env, $env_value);

        $reflection = new \ReflectionClass($action);
        $property = $reflection->getProperty('repository');
        $property->setAccessible(true);
        $property->setValue($action, $repository);

        $result = $action->loadData();

        $this->assertSame(200, $result['http_status']);
        $this->assertIsBool($result['__raw_value']);
        $this->assertFalse($result['__raw_value']);
    }

    /**
     * Tests inactive INTEGER flag returns null
     */
    public function testLoadDataInactiveIntegerReturnsNull(): void {
        $phlag = $this->createPhlag('old_config', 'INTEGER');
        $env = $this->createEnvironment('production');
        $env_value = $this->createEnvironmentValue('50', null, '2020-01-01 00:00:00');
        
        $action = $this->createActionWithAuthBypass();
        $action->name = 'old_config';
        $action->environment = 'production';

        $repository = $this->createMockRepositoryFor($phlag, $env, $env_value);

        $reflection = new \ReflectionClass($action);
        $property = $reflection->getProperty('repository');
        $property->setAccessible(true);
        $property->setValue($action, $repository);

        $result = $action->loadData();

        $this->assertSame(200, $result['http_status']);
        $this->assertNull($result['__raw_value']);
    }

    /**
     * Tests inactive FLOAT flag returns null
     */
    public function testLoadDataInactiveFloatReturnsNull(): void {
        $phlag = $this->createPhlag('expired_multiplier', 'FLOAT');
        $env = $this->createEnvironment('production');
        $env_value = $this->createEnvironmentValue('2.5', null, '2020-01-01 00:00:00');
        
        $action = $this->createActionWithAuthBypass();
        $action->name = 'expired_multiplier';
        $action->environment = 'production';

        $repository = $this->createMockRepositoryFor($phlag, $env, $env_value);

        $reflection = new \ReflectionClass($action);
        $property = $reflection->getProperty('repository');
        $property->setAccessible(true);
        $property->setValue($action, $repository);

        $result = $action->loadData();

        $this->assertSame(200, $result['http_status']);
        $this->assertNull($result['__raw_value']);
    }

    /**
     * Tests inactive STRING flag returns null
     */
    public function testLoadDataInactiveStringReturnsNull(): void {
        $phlag = $this->createPhlag('old_message', 'STRING');
        $env = $this->createEnvironment('production');
        $env_value = $this->createEnvironmentValue('Goodbye', null, '2020-01-01 00:00:00');
        
        $action = $this->createActionWithAuthBypass();
        $action->name = 'old_message';
        $action->environment = 'production';

        $repository = $this->createMockRepositoryFor($phlag, $env, $env_value);

        $reflection = new \ReflectionClass($action);
        $property = $reflection->getProperty('repository');
        $property->setAccessible(true);
        $property->setValue($action, $repository);

        $result = $action->loadData();

        $this->assertSame(200, $result['http_status']);
        $this->assertNull($result['__raw_value']);
    }

    /**
     * Tests flag with null value returns null
     */
    public function testLoadDataNullValueReturnsNull(): void {
        $phlag = $this->createPhlag('null_flag', 'STRING');
        $env = $this->createEnvironment('production');
        $env_value = $this->createEnvironmentValue(null);
        
        $action = $this->createActionWithAuthBypass();
        $action->name = 'null_flag';
        $action->environment = 'production';

        $repository = $this->createMockRepositoryFor($phlag, $env, $env_value);

        $reflection = new \ReflectionClass($action);
        $property = $reflection->getProperty('repository');
        $property->setAccessible(true);
        $property->setValue($action, $repository);

        $result = $action->loadData();

        $this->assertSame(200, $result['http_status']);
        $this->assertNull($result['__raw_value']);
    }

    /**
     * Tests respond method outputs raw value as JSON
     */
    public function testRespondOutputsRawValue(): void {
        $action = $this->createActionWithAuthBypass();

        $data = [
            'http_status' => 200,
            '__raw_value' => true,
        ];

        ob_start();
        $action->respond($data);
        $output = ob_get_clean();

        $this->assertSame('true', $output);
    }

    /**
     * Tests respond method handles null value correctly
     */
    public function testRespondHandlesNull(): void {
        $action = $this->createActionWithAuthBypass();

        $data = [
            'http_status' => 200,
            '__raw_value' => null,
        ];

        ob_start();
        $action->respond($data);
        $output = ob_get_clean();

        $this->assertSame('null', $output);
    }

    /**
     * Tests respond method outputs integer correctly
     */
    public function testRespondOutputsInteger(): void {
        $action = $this->createActionWithAuthBypass();

        $data = [
            'http_status' => 200,
            '__raw_value' => 42,
        ];

        ob_start();
        $action->respond($data);
        $output = ob_get_clean();

        $this->assertSame('42', $output);
    }

    /**
     * Tests respond method outputs string with proper JSON encoding
     */
    public function testRespondOutputsString(): void {
        $action = $this->createActionWithAuthBypass();

        $data = [
            'http_status' => 200,
            '__raw_value' => 'test',
        ];

        ob_start();
        $action->respond($data);
        $output = ob_get_clean();

        $this->assertSame('"test"', $output);
    }
}
