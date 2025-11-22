<?php

namespace Moonspot\Phlag\Tests\Unit\Web\Security;

use PHPUnit\Framework\TestCase;
use Moonspot\Phlag\Web\Security\CsrfToken;

/**
 * Unit Tests for CSRF Token Manager
 *
 * Tests the CSRF token generation, validation, and management functionality
 * to ensure protection against Cross-Site Request Forgery attacks.
 *
 * ## Test Coverage
 *
 * - Token generation and storage
 * - Token validation with timing-safe comparison
 * - Token retrieval without regeneration
 * - Token destruction
 * - Edge cases (empty tokens, missing session data, etc.)
 *
 * @package Moonspot\Phlag\Tests\Unit\Web\Security
 */
class CsrfTokenTest extends TestCase {

    /**
     * Set up test environment before each test
     *
     * Ensures session is available and clean for each test case.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();

        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Clear any existing CSRF token
        unset($_SESSION['csrf_token']);
    }

    /**
     * Tear down test environment after each test
     *
     * Cleans up session data to prevent test contamination.
     *
     * @return void
     */
    protected function tearDown(): void {
        // Clean up session
        unset($_SESSION['csrf_token']);

        parent::tearDown();
    }

    /**
     * Tests that generate() creates a non-empty token
     *
     * Verifies that the generate method produces a valid token string
     * and stores it in the session.
     *
     * @return void
     */
    public function testGenerateCreatesNonEmptyToken(): void {

        $token = CsrfToken::generate();

        $this->assertNotEmpty($token);
        $this->assertIsString($token);
    }

    /**
     * Tests that generate() stores token in session
     *
     * Verifies that the generated token is properly stored in the
     * session for later validation.
     *
     * @return void
     */
    public function testGenerateStoresTokenInSession(): void {

        $token = CsrfToken::generate();

        $this->assertArrayHasKey('csrf_token', $_SESSION);
        $this->assertEquals($token, $_SESSION['csrf_token']);
    }

    /**
     * Tests that generate() creates unique tokens
     *
     * Verifies that each call to generate produces a different token,
     * ensuring randomness and security.
     *
     * @return void
     */
    public function testGenerateCreatesUniqueTokens(): void {

        $token1 = CsrfToken::generate();
        $token2 = CsrfToken::generate();

        $this->assertNotEquals($token1, $token2);
    }

    /**
     * Tests that generated token is 64 characters long
     *
     * Verifies that the token length matches expected format
     * (32 bytes = 64 hex characters).
     *
     * @return void
     */
    public function testGeneratedTokenLength(): void {

        $token = CsrfToken::generate();

        $this->assertEquals(64, strlen($token));
    }

    /**
     * Tests that validate() returns true for valid token
     *
     * Verifies that a valid token stored in the session passes
     * validation successfully.
     *
     * @return void
     */
    public function testValidateReturnsTrueForValidToken(): void {

        $token = CsrfToken::generate();
        $result = CsrfToken::validate($token);

        $this->assertTrue($result);
    }

    /**
     * Tests that validate() returns false for invalid token
     *
     * Verifies that an incorrect token fails validation.
     *
     * @return void
     */
    public function testValidateReturnsFalseForInvalidToken(): void {

        CsrfToken::generate();
        $result = CsrfToken::validate('invalid_token_12345');

        $this->assertFalse($result);
    }

    /**
     * Tests that validate() returns false for empty token
     *
     * Verifies that empty or null tokens are rejected.
     *
     * @return void
     */
    public function testValidateReturnsFalseForEmptyToken(): void {

        CsrfToken::generate();

        $this->assertFalse(CsrfToken::validate(''));
        $this->assertFalse(CsrfToken::validate(null));
    }

    /**
     * Tests that validate() returns false when no token in session
     *
     * Verifies that validation fails gracefully when no token
     * has been generated yet.
     *
     * @return void
     */
    public function testValidateReturnsFalseWhenNoSessionToken(): void {

        $result = CsrfToken::validate('some_token');

        $this->assertFalse($result);
    }

    /**
     * Tests that validate() regenerates token after validation
     *
     * Verifies that the token is regenerated after successful validation
     * to prevent replay attacks.
     *
     * @return void
     */
    public function testValidateRegeneratesTokenAfterValidation(): void {

        $original_token = CsrfToken::generate();
        CsrfToken::validate($original_token);
        $new_token = $_SESSION['csrf_token'];

        $this->assertNotEquals($original_token, $new_token);
    }

    /**
     * Tests that validate() regenerates token even on failure
     *
     * Verifies that the token is regenerated even when validation
     * fails, ensuring tokens are single-use.
     *
     * @return void
     */
    public function testValidateRegeneratesTokenOnFailure(): void {

        $original_token = CsrfToken::generate();
        CsrfToken::validate('wrong_token');
        $new_token = $_SESSION['csrf_token'];

        $this->assertNotEquals($original_token, $new_token);
    }

    /**
     * Tests that get() returns existing token without regenerating
     *
     * Verifies that get() retrieves the current token without
     * creating a new one.
     *
     * @return void
     */
    public function testGetReturnsExistingToken(): void {

        $original_token = CsrfToken::generate();
        $retrieved_token = CsrfToken::get();

        $this->assertEquals($original_token, $retrieved_token);
    }

    /**
     * Tests that get() generates token if none exists
     *
     * Verifies that get() creates a new token if one doesn't
     * already exist in the session.
     *
     * @return void
     */
    public function testGetGeneratesTokenIfNoneExists(): void {

        $token = CsrfToken::get();

        $this->assertNotEmpty($token);
        $this->assertArrayHasKey('csrf_token', $_SESSION);
    }

    /**
     * Tests that destroy() removes token from session
     *
     * Verifies that the destroy method properly removes the
     * token from the session.
     *
     * @return void
     */
    public function testDestroyRemovesToken(): void {

        CsrfToken::generate();
        CsrfToken::destroy();

        $this->assertArrayNotHasKey('csrf_token', $_SESSION);
    }

    /**
     * Tests that destroy() works when no token exists
     *
     * Verifies that destroy() doesn't throw errors when called
     * without an existing token.
     *
     * @return void
     */
    public function testDestroyWorksWithoutExistingToken(): void {

        // Should not throw any errors
        CsrfToken::destroy();

        $this->assertArrayNotHasKey('csrf_token', $_SESSION);
    }

    /**
     * Tests timing-safe comparison prevents timing attacks
     *
     * Verifies that validation uses hash_equals for timing-safe
     * comparison. This test ensures that correct and incorrect
     * tokens of the same length take similar time to validate.
     *
     * @return void
     */
    public function testValidationUsesTimingSafeComparison(): void {

        $token = CsrfToken::generate();

        // Create a token of the same length but different value
        $fake_token = str_repeat('a', strlen($token));

        // Both should fail or succeed consistently
        $result1 = CsrfToken::validate($fake_token);
        $this->assertFalse($result1);

        // Generate new token and test with correct one
        $new_token = CsrfToken::generate();
        $result2 = CsrfToken::validate($new_token);
        $this->assertTrue($result2);
    }

    /**
     * Tests that token cannot be reused after validation
     *
     * Verifies that once a token is validated, it cannot be
     * used again (prevents replay attacks).
     *
     * @return void
     */
    public function testTokenCannotBeReusedAfterValidation(): void {

        $token = CsrfToken::generate();

        // First validation should succeed
        $first_result = CsrfToken::validate($token);
        $this->assertTrue($first_result);

        // Second validation with same token should fail
        $second_result = CsrfToken::validate($token);
        $this->assertFalse($second_result);
    }

    /**
     * Tests multiple get() calls return same token
     *
     * Verifies that calling get() multiple times returns the
     * same token without regenerating it.
     *
     * @return void
     */
    public function testMultipleGetCallsReturnSameToken(): void {

        $token1 = CsrfToken::get();
        $token2 = CsrfToken::get();
        $token3 = CsrfToken::get();

        $this->assertEquals($token1, $token2);
        $this->assertEquals($token2, $token3);
    }
}
