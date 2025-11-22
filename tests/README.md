# Phlag Unit Tests

This directory contains comprehensive unit tests for the Phlag application's action classes using PHPUnit 11.

## Running Tests

### Run all tests
```bash
./vendor/bin/phpunit
```

### Run tests with detailed output
```bash
./vendor/bin/phpunit --testdox
```

### Run tests with coverage (requires Xdebug)
```bash
./vendor/bin/phpunit --coverage-html coverage
```

### Run specific test class
```bash
./vendor/bin/phpunit tests/Unit/Action/FlagValueTraitTest.php
```

### Run specific test method
```bash
./vendor/bin/phpunit --filter testIsPhlagActiveNoConstraints
```

## Test Structure

```
tests/
└── Unit/
    └── Action/
        ├── FlagValueTraitTest.php      # Tests for FlagValueTrait
        ├── GetPhlagStateTest.php       # Tests for GetPhlagState action
        ├── GetAllFlagsTest.php         # Tests for GetAllFlags action
        └── GetFlagsTest.php            # Tests for GetFlags action
```

## Test Coverage

### FlagValueTraitTest (21 tests)
Tests the shared temporal logic and type casting functionality:
- Temporal constraint validation (8 tests)
- Type casting for all flag types (13 tests)
- Edge cases (null handling, unknown types)

### GetPhlagStateTest (15 tests)
Tests the single flag retrieval endpoint:
- Flag not found scenarios
- Active flags of all types (SWITCH, INTEGER, FLOAT, STRING)
- Inactive flag handling
- Response formatting

### GetAllFlagsTest (10 tests)
Tests the bulk flag values endpoint:
- Empty flag list
- Multiple flags of different types
- Active and inactive flag mixing
- JSON object response format

### GetFlagsTest (14 tests)
Tests the detailed flag information endpoint:
- Empty flag list
- Complete flag details (name, type, value, dates)
- ISO 8601 datetime formatting
- Active and inactive flag handling
- JSON array response format

## Writing New Tests

### Test Class Template

```php
<?php

namespace Moonspot\Phlag\Tests\Unit\Action;

use PHPUnit\Framework\TestCase;

class MyNewTest extends TestCase {
    
    /**
     * Brief description of what this test validates
     */
    public function testSomething(): void {
        // Arrange
        $expected = true;
        
        // Act
        $result = someFunction();
        
        // Assert
        $this->assertTrue($result);
    }
}
```

### Testing Protected Methods

Use reflection to access protected methods:

```php
$reflection = new \ReflectionClass($action);
$method = $reflection->getMethod('protectedMethod');
$method->setAccessible(true);
$result = $method->invoke($action, $param1, $param2);
```

### Mocking Repository

```php
$repository = $this->createMock(Repository::class);
$repository->method('find')
    ->willReturn($expected_results);
```

## Coding Standards

Tests follow the same coding standards as production code:
- PSR-1, PSR-4, PSR-12 compliance
- 1TBS brace style
- Snake_case for variables
- Type declarations on all methods
- Descriptive test method names starting with `test`
- PHPDoc blocks on all test methods

## Continuous Integration

These tests are designed to run in CI/CD pipelines. Ensure:
- All tests pass before merging
- No test dependencies on external services
- Tests are deterministic (no random failures)
- Fast execution (all tests run in < 1 second)

## Test Data

Tests use mock data and do not require:
- Database connection
- External API access
- File system access
- Environment variables

All test data is created in-memory within each test method.
