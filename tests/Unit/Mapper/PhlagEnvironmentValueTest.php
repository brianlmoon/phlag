<?php

declare(strict_types=1);

namespace Moonspot\Phlag\Tests\Unit\Mapper;

use Moonspot\Phlag\Data\Phlag;
use Moonspot\Phlag\Data\PhlagEnvironmentValue;
use Moonspot\Phlag\Data\Repository;
use Moonspot\Phlag\Mapper\PhlagEnvironmentValue as PhlagEnvironmentValueMapper;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Test suite for PhlagEnvironmentValue mapper
 *
 * Tests JSON validation logic in the save method. Uses reflection and
 * mocked repositories to test the protected validateJsonIfNeeded() method
 * without requiring database connections.
 */
class PhlagEnvironmentValueTest extends TestCase {

    /**
     * Calls the protected validateJsonIfNeeded method via reflection
     *
     * @param PhlagEnvironmentValue $value Value object to validate
     * @param Repository $repository Mocked repository
     * @return void
     * @throws \RuntimeException If validation fails
     */
    protected function callValidateJsonIfNeeded(
        PhlagEnvironmentValue $value,
        Repository $repository
    ): void {

        $mapper = $this->getMockBuilder(PhlagEnvironmentValueMapper::class)
            ->disableOriginalConstructor()
            ->getMock();

        $method = new ReflectionMethod(
            PhlagEnvironmentValueMapper::class,
            'validateJsonIfNeeded'
        );
        $method->setAccessible(true);

        $method->invoke($mapper, $value, $repository);
    }

    /**
     * Creates a mocked Phlag object
     *
     * @param string $type Flag type (SWITCH, INTEGER, FLOAT, STRING, JSON)
     * @return Phlag Mocked phlag object
     */
    protected function createMockPhlag(string $type): Phlag {

        $phlag = new Phlag();
        $phlag->phlag_id = 1;
        $phlag->name = 'test_flag';
        $phlag->type = $type;
        $phlag->description = 'Test flag';

        return $phlag;
    }

    /**
     * Tests validating JSON type with valid object
     */
    public function testValidateJsonValidObject(): void {

        $value = new PhlagEnvironmentValue();
        $value->phlag_id = 1;
        $value->phlag_environment_id = 1;
        $value->value = '{"key": "value", "number": 42}';

        $repository = $this->createMock(Repository::class);
        $repository->expects($this->once())
            ->method('get')
            ->with('Phlag', 1)
            ->willReturn($this->createMockPhlag('JSON'));

        // Should not throw exception
        $this->callValidateJsonIfNeeded($value, $repository);
        $this->assertTrue(true); // Assertion to confirm no exception
    }

    /**
     * Tests validating JSON type with valid array
     */
    public function testValidateJsonValidArray(): void {

        $value = new PhlagEnvironmentValue();
        $value->phlag_id = 1;
        $value->phlag_environment_id = 1;
        $value->value = '["item1", "item2", "item3"]';

        $repository = $this->createMock(Repository::class);
        $repository->expects($this->once())
            ->method('get')
            ->with('Phlag', 1)
            ->willReturn($this->createMockPhlag('JSON'));

        $this->callValidateJsonIfNeeded($value, $repository);
        $this->assertTrue(true);
    }

    /**
     * Tests validating JSON type with nested structure
     */
    public function testValidateJsonNestedStructure(): void {

        $value = new PhlagEnvironmentValue();
        $value->phlag_id = 1;
        $value->phlag_environment_id = 1;
        $value->value = '{"user": {"name": "John", "tags": ["admin"]}}';

        $repository = $this->createMock(Repository::class);
        $repository->expects($this->once())
            ->method('get')
            ->with('Phlag', 1)
            ->willReturn($this->createMockPhlag('JSON'));

        $this->callValidateJsonIfNeeded($value, $repository);
        $this->assertTrue(true);
    }

    /**
     * Tests validating JSON type with empty object
     */
    public function testValidateJsonEmptyObject(): void {

        $value = new PhlagEnvironmentValue();
        $value->phlag_id = 1;
        $value->phlag_environment_id = 1;
        $value->value = '{}';

        $repository = $this->createMock(Repository::class);
        $repository->expects($this->once())
            ->method('get')
            ->with('Phlag', 1)
            ->willReturn($this->createMockPhlag('JSON'));

        $this->callValidateJsonIfNeeded($value, $repository);
        $this->assertTrue(true);
    }

    /**
     * Tests validating JSON type with empty array
     */
    public function testValidateJsonEmptyArray(): void {

        $value = new PhlagEnvironmentValue();
        $value->phlag_id = 1;
        $value->phlag_environment_id = 1;
        $value->value = '[]';

        $repository = $this->createMock(Repository::class);
        $repository->expects($this->once())
            ->method('get')
            ->with('Phlag', 1)
            ->willReturn($this->createMockPhlag('JSON'));

        $this->callValidateJsonIfNeeded($value, $repository);
        $this->assertTrue(true);
    }

    /**
     * Tests validating JSON type with invalid syntax throws exception
     */
    public function testValidateJsonInvalidSyntax(): void {

        $value = new PhlagEnvironmentValue();
        $value->phlag_id = 1;
        $value->phlag_environment_id = 1;
        $value->value = '{invalid json}';

        $repository = $this->createMock(Repository::class);
        $repository->expects($this->once())
            ->method('get')
            ->with('Phlag', 1)
            ->willReturn($this->createMockPhlag('JSON'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid JSON format');

        $this->callValidateJsonIfNeeded($value, $repository);
    }

    /**
     * Tests validating JSON type with malformed JSON throws exception
     */
    public function testValidateJsonMalformed(): void {

        $value = new PhlagEnvironmentValue();
        $value->phlag_id = 1;
        $value->phlag_environment_id = 1;
        $value->value = '{"key": "value"';  // Missing closing brace

        $repository = $this->createMock(Repository::class);
        $repository->expects($this->once())
            ->method('get')
            ->with('Phlag', 1)
            ->willReturn($this->createMockPhlag('JSON'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid JSON format');

        $this->callValidateJsonIfNeeded($value, $repository);
    }

    /**
     * Tests validating JSON type with string primitive throws exception
     */
    public function testValidateJsonStringPrimitive(): void {

        $value = new PhlagEnvironmentValue();
        $value->phlag_id = 1;
        $value->phlag_environment_id = 1;
        $value->value = '"just a string"';

        $repository = $this->createMock(Repository::class);
        $repository->expects($this->once())
            ->method('get')
            ->with('Phlag', 1)
            ->willReturn($this->createMockPhlag('JSON'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('JSON must be an object or array');

        $this->callValidateJsonIfNeeded($value, $repository);
    }

    /**
     * Tests validating JSON type with number primitive throws exception
     */
    public function testValidateJsonNumberPrimitive(): void {

        $value = new PhlagEnvironmentValue();
        $value->phlag_id = 1;
        $value->phlag_environment_id = 1;
        $value->value = '123';

        $repository = $this->createMock(Repository::class);
        $repository->expects($this->once())
            ->method('get')
            ->with('Phlag', 1)
            ->willReturn($this->createMockPhlag('JSON'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('JSON must be an object or array');

        $this->callValidateJsonIfNeeded($value, $repository);
    }

    /**
     * Tests validating JSON type with boolean primitive throws exception
     */
    public function testValidateJsonBooleanPrimitive(): void {

        $value = new PhlagEnvironmentValue();
        $value->phlag_id = 1;
        $value->phlag_environment_id = 1;
        $value->value = 'true';

        $repository = $this->createMock(Repository::class);
        $repository->expects($this->once())
            ->method('get')
            ->with('Phlag', 1)
            ->willReturn($this->createMockPhlag('JSON'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('JSON must be an object or array');

        $this->callValidateJsonIfNeeded($value, $repository);
    }

    /**
     * Tests validating JSON type with null primitive throws exception
     */
    public function testValidateJsonNullPrimitive(): void {

        $value = new PhlagEnvironmentValue();
        $value->phlag_id = 1;
        $value->phlag_environment_id = 1;
        $value->value = 'null';

        $repository = $this->createMock(Repository::class);
        $repository->expects($this->once())
            ->method('get')
            ->with('Phlag', 1)
            ->willReturn($this->createMockPhlag('JSON'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('JSON must be an object or array');

        $this->callValidateJsonIfNeeded($value, $repository);
    }

    /**
     * Tests validating JSON type with empty string throws exception
     */
    public function testValidateJsonEmptyString(): void {

        $value = new PhlagEnvironmentValue();
        $value->phlag_id = 1;
        $value->phlag_environment_id = 1;
        $value->value = '';

        $repository = $this->createMock(Repository::class);
        $repository->expects($this->once())
            ->method('get')
            ->with('Phlag', 1)
            ->willReturn($this->createMockPhlag('JSON'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid JSON format');

        $this->callValidateJsonIfNeeded($value, $repository);
    }

    /**
     * Tests validating non-JSON type passes through without validation
     */
    public function testValidateNonJsonType(): void {

        $value = new PhlagEnvironmentValue();
        $value->phlag_id = 1;
        $value->phlag_environment_id = 1;
        $value->value = '{not validated}';  // Invalid JSON but STRING type

        $repository = $this->createMock(Repository::class);
        $repository->expects($this->once())
            ->method('get')
            ->with('Phlag', 1)
            ->willReturn($this->createMockPhlag('STRING'));

        $this->callValidateJsonIfNeeded($value, $repository);
        $this->assertTrue(true);
    }

    /**
     * Tests validating when phlag does not exist
     */
    public function testValidateJsonPhlagNotFound(): void {

        $value = new PhlagEnvironmentValue();
        $value->phlag_id = 999;
        $value->phlag_environment_id = 1;
        $value->value = '{"key": "value"}';

        $repository = $this->createMock(Repository::class);
        $repository->expects($this->once())
            ->method('get')
            ->with('Phlag', 999)
            ->willReturn(null);

        // Should not throw - non-existent flags skip validation
        $this->callValidateJsonIfNeeded($value, $repository);
        $this->assertTrue(true);
    }
}
