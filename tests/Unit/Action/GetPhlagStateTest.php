<?php

namespace Moonspot\Phlag\Tests\Unit\Action;

use Moonspot\Phlag\Action\GetPhlagState;
use Moonspot\Phlag\Data\Phlag;
use Moonspot\Phlag\Data\Repository;
use PHPUnit\Framework\TestCase;

/**
 * Tests the GetPhlagState action class
 *
 * This test suite verifies the behavior of the GetPhlagState action which
 * retrieves a single flag's value by name and evaluates it based on temporal
 * constraints and type casting.
 *
 * @package Moonspot\Phlag\Tests\Unit\Action
 */
class GetPhlagStateTest extends TestCase {

    /**
     * Creates a test phlag object
     *
     * @param string  $name            Flag name
     * @param string  $type            Flag type
     * @param ?string $value           Flag value
     * @param ?string $start_datetime  Start datetime or null
     * @param ?string $end_datetime    End datetime or null
     *
     * @return Phlag Test phlag object
     */
    protected function createPhlag(
        string $name = 'test_flag',
        string $type = 'SWITCH',
        ?string $value = 'true',
        ?string $start_datetime = null,
        ?string $end_datetime = null
    ): Phlag {
        $phlag = new Phlag();
        $phlag->phlag_id = 1;
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
     * Tests loading data when flag does not exist returns null
     */
    public function testLoadDataFlagNotFoundReturnsNull(): void {
        $action = new GetPhlagState();
        $action->name = 'nonexistent_flag';

        $repository = $this->createMockRepository([]);

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
        $phlag = $this->createPhlag('feature_flag', 'SWITCH', 'true');
        $action = new GetPhlagState();
        $action->name = 'feature_flag';

        $repository = $this->createMockRepository([1 => $phlag]);

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
        $phlag = $this->createPhlag('feature_flag', 'SWITCH', 'false');
        $action = new GetPhlagState();
        $action->name = 'feature_flag';

        $repository = $this->createMockRepository([1 => $phlag]);

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
        $phlag = $this->createPhlag('max_items', 'INTEGER', '100');
        $action = new GetPhlagState();
        $action->name = 'max_items';

        $repository = $this->createMockRepository([1 => $phlag]);

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
        $phlag = $this->createPhlag('multiplier', 'FLOAT', '1.5');
        $action = new GetPhlagState();
        $action->name = 'multiplier';

        $repository = $this->createMockRepository([1 => $phlag]);

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
        $phlag = $this->createPhlag('message', 'STRING', 'Hello World');
        $action = new GetPhlagState();
        $action->name = 'message';

        $repository = $this->createMockRepository([1 => $phlag]);

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
        $phlag = $this->createPhlag(
            'scheduled_flag',
            'SWITCH',
            'true',
            '2099-01-01 00:00:00',
            null
        );
        $action = new GetPhlagState();
        $action->name = 'scheduled_flag';

        $repository = $this->createMockRepository([1 => $phlag]);

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
        $phlag = $this->createPhlag(
            'old_config',
            'INTEGER',
            '50',
            null,
            '2020-01-01 00:00:00'
        );
        $action = new GetPhlagState();
        $action->name = 'old_config';

        $repository = $this->createMockRepository([1 => $phlag]);

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
        $phlag = $this->createPhlag(
            'expired_multiplier',
            'FLOAT',
            '2.5',
            null,
            '2020-01-01 00:00:00'
        );
        $action = new GetPhlagState();
        $action->name = 'expired_multiplier';

        $repository = $this->createMockRepository([1 => $phlag]);

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
        $phlag = $this->createPhlag(
            'old_message',
            'STRING',
            'Goodbye',
            null,
            '2020-01-01 00:00:00'
        );
        $action = new GetPhlagState();
        $action->name = 'old_message';

        $repository = $this->createMockRepository([1 => $phlag]);

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
        $phlag = $this->createPhlag('null_flag', 'STRING', null);
        $action = new GetPhlagState();
        $action->name = 'null_flag';

        $repository = $this->createMockRepository([1 => $phlag]);

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
        $action = new GetPhlagState();

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
        $action = new GetPhlagState();

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
        $action = new GetPhlagState();

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
        $action = new GetPhlagState();

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
