<?php

namespace Moonspot\Phlag\Tests\Unit\Action;

use Moonspot\Phlag\Action\ApiKeyAuthTrait;
use Moonspot\Phlag\Data\Repository;
use PHPUnit\Framework\TestCase;

/**
 * ApiKeyAuthTrait Test
 *
 * Tests API key authentication functionality including Bearer token extraction,
 * validation against the database, and error responses. Uses a concrete test
 * class to test the trait methods.
 */
class ApiKeyAuthTraitTest extends TestCase {

    /**
     * Original HTTP_AUTHORIZATION value for restoration
     *
     * @var string|null
     */
    protected ?string $original_auth = null;

    /**
     * Sets up test environment before each test
     *
     * Saves the original HTTP_AUTHORIZATION header value if present
     * so it can be restored after the test.
     *
     * @return void
     */
    protected function setUp(): void {

        parent::setUp();

        // Save original authorization header
        $this->original_auth = $_SERVER['HTTP_AUTHORIZATION'] ?? null;
    }

    /**
     * Tears down test environment after each test
     *
     * Restores the original HTTP_AUTHORIZATION header value to prevent
     * test interference.
     *
     * @return void
     */
    protected function tearDown(): void {

        // Restore original authorization header
        if ($this->original_auth !== null) {
            $_SERVER['HTTP_AUTHORIZATION'] = $this->original_auth;
        } else {
            unset($_SERVER['HTTP_AUTHORIZATION']);
        }

        parent::tearDown();
    }

    /**
     * Creates a test class instance using the trait
     *
     * Creates a concrete class that uses ApiKeyAuthTrait with a mocked
     * repository for testing authentication methods.
     *
     * @param array $api_keys Array of valid API keys for mock repository
     *
     * @return object Test class instance
     */
    protected function createTestClass(array $api_keys = []): object {

        // Create a concrete class that uses the trait
        $test_class = new class {
            use ApiKeyAuthTrait;

            public Repository $repository;

            public function setRepository(Repository $repository): void {
                $this->repository = $repository;
            }

            // Make protected methods public for testing
            public function publicAuthenticateApiKey(): ?array {
                return $this->authenticateApiKey();
            }

            public function publicExtractBearerToken(string $auth_header): ?string {
                return $this->extractBearerToken($auth_header);
            }

            public function publicValidateApiKey(string $token): bool {
                return $this->validateApiKey($token);
            }

            public function publicGetUnauthorizedResponse(string $message): array {
                return $this->getUnauthorizedResponse($message);
            }
        };

        // Mock repository
        $repository = $this->createMock(Repository::class);
        $repository->method('find')
            ->willReturnCallback(function ($entity, $criteria) use ($api_keys) {
                if ($entity === 'PhlagApiKey' && isset($criteria['api_key'])) {
                    $token = $criteria['api_key'];
                    if (in_array($token, $api_keys)) {
                        return [
                            ['phlag_api_key_id' => 1, 'api_key' => $token],
                        ];
                    }
                }
                return [];
            });

        $test_class->setRepository($repository);

        return $test_class;
    }

    /**
     * Tests extractBearerToken extracts valid token
     *
     * Verifies that extractBearerToken correctly extracts the token
     * from a properly formatted Authorization header.
     *
     * @return void
     */
    public function testExtractBearerTokenExtractsValidToken(): void {

        $test_class = $this->createTestClass();

        $token = $test_class->publicExtractBearerToken('Bearer abc123def456');

        $this->assertSame('abc123def456', $token);
    }

    /**
     * Tests extractBearerToken handles case insensitivity
     *
     * Verifies that extractBearerToken handles "Bearer" prefix
     * case-insensitively (bearer, BEARER, Bearer, etc.).
     *
     * @return void
     */
    public function testExtractBearerTokenHandlesCaseInsensitivity(): void {

        $test_class = $this->createTestClass();

        $token1 = $test_class->publicExtractBearerToken('bearer abc123');
        $token2 = $test_class->publicExtractBearerToken('BEARER abc123');
        $token3 = $test_class->publicExtractBearerToken('BeArEr abc123');

        $this->assertSame('abc123', $token1);
        $this->assertSame('abc123', $token2);
        $this->assertSame('abc123', $token3);
    }

    /**
     * Tests extractBearerToken trims whitespace
     *
     * Verifies that extractBearerToken trims leading and trailing
     * whitespace from the extracted token.
     *
     * @return void
     */
    public function testExtractBearerTokenTrimsWhitespace(): void {

        $test_class = $this->createTestClass();

        $token = $test_class->publicExtractBearerToken('Bearer   abc123   ');

        $this->assertSame('abc123', $token);
    }

    /**
     * Tests extractBearerToken returns null for invalid format
     *
     * Verifies that extractBearerToken returns null when the header
     * doesn't start with "Bearer ".
     *
     * @return void
     */
    public function testExtractBearerTokenReturnsNullForInvalidFormat(): void {

        $test_class = $this->createTestClass();

        $token1 = $test_class->publicExtractBearerToken('Basic abc123');
        $token2 = $test_class->publicExtractBearerToken('abc123');
        $token3 = $test_class->publicExtractBearerToken('Token abc123');

        $this->assertNull($token1);
        $this->assertNull($token2);
        $this->assertNull($token3);
    }

    /**
     * Tests extractBearerToken returns null for empty token
     *
     * Verifies that extractBearerToken returns null when the header
     * has "Bearer " but no actual token value.
     *
     * @return void
     */
    public function testExtractBearerTokenReturnsNullForEmptyToken(): void {

        $test_class = $this->createTestClass();

        $token1 = $test_class->publicExtractBearerToken('Bearer ');
        $token2 = $test_class->publicExtractBearerToken('Bearer    ');

        $this->assertNull($token1);
        $this->assertNull($token2);
    }

    /**
     * Tests extractBearerToken handles token with spaces
     *
     * Verifies that extractBearerToken extracts tokens that may
     * contain internal spaces (though unusual).
     *
     * @return void
     */
    public function testExtractBearerTokenHandlesTokenWithSpaces(): void {

        $test_class = $this->createTestClass();

        $token = $test_class->publicExtractBearerToken('Bearer abc 123 def');

        $this->assertSame('abc 123 def', $token);
    }

    /**
     * Tests validateApiKey returns true for valid key
     *
     * Verifies that validateApiKey returns true when the token
     * exists in the database.
     *
     * @return void
     */
    public function testValidateApiKeyReturnsTrueForValidKey(): void {

        $test_class = $this->createTestClass(['valid_key_123']);

        $is_valid = $test_class->publicValidateApiKey('valid_key_123');

        $this->assertTrue($is_valid);
    }

    /**
     * Tests validateApiKey returns false for invalid key
     *
     * Verifies that validateApiKey returns false when the token
     * does not exist in the database.
     *
     * @return void
     */
    public function testValidateApiKeyReturnsFalseForInvalidKey(): void {

        $test_class = $this->createTestClass(['valid_key_123']);

        $is_valid = $test_class->publicValidateApiKey('invalid_key');

        $this->assertFalse($is_valid);
    }

    /**
     * Tests validateApiKey returns false for empty key
     *
     * Verifies that validateApiKey returns false when given
     * an empty string.
     *
     * @return void
     */
    public function testValidateApiKeyReturnsFalseForEmptyKey(): void {

        $test_class = $this->createTestClass(['valid_key_123']);

        $is_valid = $test_class->publicValidateApiKey('');

        $this->assertFalse($is_valid);
    }

    /**
     * Tests getUnauthorizedResponse returns correct structure
     *
     * Verifies that getUnauthorizedResponse returns an array with
     * the correct structure and values.
     *
     * @return void
     */
    public function testGetUnauthorizedResponseReturnsCorrectStructure(): void {

        $test_class = $this->createTestClass();

        $response = $test_class->publicGetUnauthorizedResponse('Test message');

        $this->assertIsArray($response);
        $this->assertArrayHasKey('http_status', $response);
        $this->assertArrayHasKey('error', $response);
        $this->assertArrayHasKey('message', $response);
        $this->assertSame(401, $response['http_status']);
        $this->assertSame('Unauthorized', $response['error']);
        $this->assertSame('Test message', $response['message']);
    }

    /**
     * Tests authenticateApiKey returns null for valid authentication
     *
     * Verifies that authenticateApiKey returns null when a valid
     * Bearer token is provided.
     *
     * @return void
     */
    public function testAuthenticateApiKeyReturnsNullForValidAuth(): void {

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer valid_key_123';

        $test_class = $this->createTestClass(['valid_key_123']);

        $result = $test_class->publicAuthenticateApiKey();

        $this->assertNull($result);
    }

    /**
     * Tests authenticateApiKey returns error for missing header
     *
     * Verifies that authenticateApiKey returns an error response
     * when the Authorization header is missing.
     *
     * @return void
     */
    public function testAuthenticateApiKeyReturnsErrorForMissingHeader(): void {

        unset($_SERVER['HTTP_AUTHORIZATION']);

        $test_class = $this->createTestClass(['valid_key_123']);

        $result = $test_class->publicAuthenticateApiKey();

        $this->assertIsArray($result);
        $this->assertSame(401, $result['http_status']);
        $this->assertSame('Missing authorization header', $result['message']);
    }

    /**
     * Tests authenticateApiKey returns error for empty header
     *
     * Verifies that authenticateApiKey returns an error response
     * when the Authorization header is an empty string.
     *
     * @return void
     */
    public function testAuthenticateApiKeyReturnsErrorForEmptyHeader(): void {

        $_SERVER['HTTP_AUTHORIZATION'] = '';

        $test_class = $this->createTestClass(['valid_key_123']);

        $result = $test_class->publicAuthenticateApiKey();

        $this->assertIsArray($result);
        $this->assertSame(401, $result['http_status']);
        $this->assertSame('Missing authorization header', $result['message']);
    }

    /**
     * Tests authenticateApiKey returns error for invalid format
     *
     * Verifies that authenticateApiKey returns an error response
     * when the Authorization header has an invalid format.
     *
     * @return void
     */
    public function testAuthenticateApiKeyReturnsErrorForInvalidFormat(): void {

        $_SERVER['HTTP_AUTHORIZATION'] = 'Basic abc123';

        $test_class = $this->createTestClass(['valid_key_123']);

        $result = $test_class->publicAuthenticateApiKey();

        $this->assertIsArray($result);
        $this->assertSame(401, $result['http_status']);
        $this->assertSame('Invalid authorization header format', $result['message']);
    }

    /**
     * Tests authenticateApiKey returns error for invalid key
     *
     * Verifies that authenticateApiKey returns an error response
     * when the Bearer token is not found in the database.
     *
     * @return void
     */
    public function testAuthenticateApiKeyReturnsErrorForInvalidKey(): void {

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer invalid_key';

        $test_class = $this->createTestClass(['valid_key_123']);

        $result = $test_class->publicAuthenticateApiKey();

        $this->assertIsArray($result);
        $this->assertSame(401, $result['http_status']);
        $this->assertSame('Invalid API key', $result['message']);
    }

    /**
     * Tests authenticateApiKey with case-insensitive bearer prefix
     *
     * Verifies that authenticateApiKey works with different cases
     * of the "Bearer" prefix.
     *
     * @return void
     */
    public function testAuthenticateApiKeyWithCaseInsensitiveBearerPrefix(): void {

        $_SERVER['HTTP_AUTHORIZATION'] = 'bearer valid_key_123';

        $test_class = $this->createTestClass(['valid_key_123']);

        $result = $test_class->publicAuthenticateApiKey();

        $this->assertNull($result);
    }

    /**
     * Tests authenticateApiKey with whitespace in header
     *
     * Verifies that authenticateApiKey handles extra whitespace
     * around the token correctly.
     *
     * @return void
     */
    public function testAuthenticateApiKeyWithWhitespaceInHeader(): void {

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer   valid_key_123   ';

        $test_class = $this->createTestClass(['valid_key_123']);

        $result = $test_class->publicAuthenticateApiKey();

        $this->assertNull($result);
    }

    /**
     * Tests authenticateApiKey returns error for empty token
     *
     * Verifies that authenticateApiKey returns an error when the
     * header has "Bearer " but no token value.
     *
     * @return void
     */
    public function testAuthenticateApiKeyReturnsErrorForEmptyToken(): void {

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ';

        $test_class = $this->createTestClass(['valid_key_123']);

        $result = $test_class->publicAuthenticateApiKey();

        $this->assertIsArray($result);
        $this->assertSame(401, $result['http_status']);
        $this->assertSame('Invalid authorization header format', $result['message']);
    }

    /**
     * Tests authenticateApiKey with multiple valid keys
     *
     * Verifies that authenticateApiKey works correctly when there
     * are multiple valid API keys in the database.
     *
     * @return void
     */
    public function testAuthenticateApiKeyWithMultipleValidKeys(): void {

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer key2';

        $test_class = $this->createTestClass(['key1', 'key2', 'key3']);

        $result = $test_class->publicAuthenticateApiKey();

        $this->assertNull($result);
    }

    /**
     * Tests authenticateApiKey with long API key
     *
     * Verifies that authenticateApiKey handles long API keys
     * (like the 64-character keys generated by the system).
     *
     * @return void
     */
    public function testAuthenticateApiKeyWithLongApiKey(): void {

        $long_key = str_repeat('a', 64);
        $_SERVER['HTTP_AUTHORIZATION'] = "Bearer {$long_key}";

        $test_class = $this->createTestClass([$long_key]);

        $result = $test_class->publicAuthenticateApiKey();

        $this->assertNull($result);
    }

    /**
     * Tests extractBearerToken with special characters
     *
     * Verifies that extractBearerToken correctly handles tokens
     * with special characters (as API keys may contain various chars).
     *
     * @return void
     */
    public function testExtractBearerTokenWithSpecialCharacters(): void {

        $test_class = $this->createTestClass();

        $token = $test_class->publicExtractBearerToken('Bearer abc-123_DEF/456+789=');

        $this->assertSame('abc-123_DEF/456+789=', $token);
    }

    /**
     * Tests validateApiKey with special characters
     *
     * Verifies that validateApiKey works with tokens containing
     * special characters.
     *
     * @return void
     */
    public function testValidateApiKeyWithSpecialCharacters(): void {

        $special_key = 'abc-123_DEF/456+789=';
        $test_class = $this->createTestClass([$special_key]);

        $is_valid = $test_class->publicValidateApiKey($special_key);

        $this->assertTrue($is_valid);
    }

    /**
     * Tests getUnauthorizedResponse with empty message
     *
     * Verifies that getUnauthorizedResponse handles empty message strings.
     *
     * @return void
     */
    public function testGetUnauthorizedResponseWithEmptyMessage(): void {

        $test_class = $this->createTestClass();

        $response = $test_class->publicGetUnauthorizedResponse('');

        $this->assertSame('', $response['message']);
    }

    /**
     * Tests getUnauthorizedResponse with long message
     *
     * Verifies that getUnauthorizedResponse handles long error messages.
     *
     * @return void
     */
    public function testGetUnauthorizedResponseWithLongMessage(): void {

        $test_class = $this->createTestClass();

        $long_message = str_repeat('This is a long error message. ', 10);
        $response = $test_class->publicGetUnauthorizedResponse($long_message);

        $this->assertSame($long_message, $response['message']);
    }
}
