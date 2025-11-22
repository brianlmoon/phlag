<?php

namespace Moonspot\Phlag\Tests\Unit\Mapper;

use Moonspot\Phlag\Data\PasswordResetToken;
use Moonspot\Phlag\Mapper\PasswordResetToken as PasswordResetTokenMapper;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Tests the PasswordResetToken mapper functionality
 *
 * This test suite verifies the token generation logic and automatic
 * expiration setting in the PasswordResetToken mapper. We test the
 * protected generateToken() method directly using reflection since
 * it contains the core security logic.
 *
 * ## What We Test
 *
 * - Token generation creates 64-character strings
 * - Tokens are URL-safe (no +, /, or = characters)
 * - Token uniqueness (probabilistically verified)
 * - Token has high entropy (cryptographic randomness)
 *
 * Note: We don't test save() with database integration as that would
 * require a full database setup. The core token generation logic is
 * what matters for security.
 *
 * @package Moonspot\Phlag\Tests\Unit\Mapper
 */
class PasswordResetTokenTest extends TestCase {

    /**
     * Calls the protected generateToken method via reflection
     *
     * @return string Generated token
     */
    protected function callGenerateToken(): string {

        $mapper = $this->getMockBuilder(PasswordResetTokenMapper::class)
            ->disableOriginalConstructor()
            ->getMock();

        $method = new ReflectionMethod(PasswordResetTokenMapper::class, 'generateToken');
        $method->setAccessible(true);

        return $method->invoke($mapper);
    }

    /**
     * Test that generateToken creates a 64-character string
     *
     * The token should be exactly 64 characters long as per
     * the specification in the mapper.
     *
     * @return void
     */
    public function testGenerateTokenCreates64CharacterString(): void {

        $token = $this->callGenerateToken();

        $this->assertIsString($token);
        $this->assertEquals(64, strlen($token));
    }

    /**
     * Test that generateToken creates URL-safe strings
     *
     * URL-safe tokens should only contain:
     * - Uppercase letters (A-Z)
     * - Lowercase letters (a-z)
     * - Digits (0-9)
     * - Minus (-) and underscore (_)
     *
     * They should NOT contain:
     * - Plus (+)
     * - Slash (/)
     * - Equals (=)
     *
     * @return void
     */
    public function testGenerateTokenCreatesUrlSafeString(): void {

        $token = $this->callGenerateToken();

        // Should only contain URL-safe characters
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $token);

        // Should NOT contain these characters
        $this->assertStringNotContainsString('+', $token);
        $this->assertStringNotContainsString('/', $token);
        $this->assertStringNotContainsString('=', $token);
    }

    /**
     * Test that generateToken creates unique tokens
     *
     * While not guaranteed mathematically (due to randomness), the
     * probability of collision is astronomically low. We generate
     * 100 tokens and verify they're all unique.
     *
     * @return void
     */
    public function testGenerateTokenCreatesUniqueTokens(): void {

        $tokens = [];
        for ($i = 0; $i < 100; $i++) {
            $tokens[] = $this->callGenerateToken();
        }

        // All tokens should be unique
        $unique_tokens = array_unique($tokens);
        $this->assertCount(100, $unique_tokens);
    }

    /**
     * Test that generateToken uses cryptographic randomness
     *
     * We verify that the token has high entropy by checking that
     * the character distribution is reasonably varied. A truly
     * random token shouldn't have too many repeated characters.
     *
     * @return void
     */
    public function testGenerateTokenHasHighEntropy(): void {

        $token = $this->callGenerateToken();

        // Count unique characters
        $chars         = str_split($token);
        $unique_chars  = array_unique($chars);
        $unique_count  = count($unique_chars);

        // A 64-character random string should have many unique characters
        // Let's say at least 20 different characters (very conservative)
        $this->assertGreaterThanOrEqual(20, $unique_count);
    }

    /**
     * Test that multiple token generations have varied results
     *
     * Generate multiple tokens and verify they have different
     * character patterns, confirming randomness.
     *
     * @return void
     */
    public function testGenerateTokensHaveVariedPatterns(): void {

        $token1 = $this->callGenerateToken();
        $token2 = $this->callGenerateToken();
        $token3 = $this->callGenerateToken();

        // Tokens should be different
        $this->assertNotEquals($token1, $token2);
        $this->assertNotEquals($token2, $token3);
        $this->assertNotEquals($token1, $token3);

        // No token should be a substring of another
        $this->assertStringNotContainsString($token1, $token2);
        $this->assertStringNotContainsString($token2, $token3);
    }

    /**
     * Test token format consistency
     *
     * Verify that all generated tokens match the expected format.
     *
     * @return void
     */
    public function testGenerateTokenFormatConsistency(): void {

        for ($i = 0; $i < 10; $i++) {
            $token = $this->callGenerateToken();

            $this->assertEquals(64, strlen($token), "Token $i should be 64 characters");
            $this->assertMatchesRegularExpression(
                '/^[A-Za-z0-9_-]+$/',
                $token,
                "Token $i should be URL-safe"
            );
        }
    }
}

