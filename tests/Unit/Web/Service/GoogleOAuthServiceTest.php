<?php

namespace Moonspot\Phlag\Tests\Unit\Web\Service;

use Moonspot\Phlag\Web\Service\GoogleOAuthService;
use DealNews\GetConfig\GetConfig;
use PHPUnit\Framework\TestCase;

/**
 * GoogleOAuthService Test
 *
 * Tests Google OAuth service functionality including configuration checks,
 * domain validation, and password generation. Uses mocked GetConfig to
 * avoid singleton caching issues.
 */
class GoogleOAuthServiceTest extends TestCase {

    /**
     * Tests isEnabled returns false when disabled in config
     *
     * Verifies that isEnabled() returns false when google_oauth.enabled
     * is not set to "true".
     *
     * @return void
     */
    public function testIsEnabledReturnsFalseWhenDisabled(): void {

        $config = $this->createMock(GetConfig::class);
        $config->method('get')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'google_oauth.enabled' => 'false',
                    'google_oauth.client_id' => 'test-client-id',
                    'google_oauth.client_secret' => 'test-client-secret',
                    default => null,
                };
            });

        $service = new GoogleOAuthService($config);

        $this->assertFalse($service->isEnabled());
    }

    /**
     * Tests isEnabled returns false when client_id is missing
     *
     * Verifies that isEnabled() returns false when google_oauth.client_id
     * is not configured.
     *
     * @return void
     */
    public function testIsEnabledReturnsFalseWithoutClientId(): void {

        $config = $this->createMock(GetConfig::class);
        $config->method('get')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'google_oauth.enabled' => 'true',
                    'google_oauth.client_secret' => 'test-client-secret',
                    default => null,
                };
            });

        $service = new GoogleOAuthService($config);

        $this->assertFalse($service->isEnabled());
    }

    /**
     * Tests isEnabled returns false when client_secret is missing
     *
     * Verifies that isEnabled() returns false when google_oauth.client_secret
     * is not configured.
     *
     * @return void
     */
    public function testIsEnabledReturnsFalseWithoutClientSecret(): void {

        $config = $this->createMock(GetConfig::class);
        $config->method('get')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'google_oauth.enabled' => 'true',
                    'google_oauth.client_id' => 'test-client-id',
                    default => null,
                };
            });

        $service = new GoogleOAuthService($config);

        $this->assertFalse($service->isEnabled());
    }

    /**
     * Tests isEnabled returns true when fully configured
     *
     * Verifies that isEnabled() returns true when all required
     * configuration values are present.
     *
     * @return void
     */
    public function testIsEnabledReturnsTrueWhenFullyConfigured(): void {

        $config = $this->createMock(GetConfig::class);
        $config->method('get')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'google_oauth.enabled' => 'true',
                    'google_oauth.client_id' => 'test-client-id',
                    'google_oauth.client_secret' => 'test-client-secret',
                    default => null,
                };
            });

        $service = new GoogleOAuthService($config);

        $this->assertTrue($service->isEnabled());
    }

    /**
     * Tests isEnabled returns true when enabled is "1"
     *
     * Verifies that isEnabled() returns true when google_oauth.enabled
     * is set to "1" (as PHP's parse_ini_file converts true to "1").
     *
     * @return void
     */
    public function testIsEnabledReturnsTrueWhenEnabledIsOne(): void {

        $config = $this->createMock(GetConfig::class);
        $config->method('get')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'google_oauth.enabled' => '1',
                    'google_oauth.client_id' => 'test-client-id',
                    'google_oauth.client_secret' => 'test-client-secret',
                    default => null,
                };
            });

        $service = new GoogleOAuthService($config);

        $this->assertTrue($service->isEnabled());
    }

    /**
     * Tests isAllowedDomain allows any domain when not configured
     *
     * Verifies that isAllowedDomain() returns true when no domains
     * are configured (allows any domain).
     *
     * @return void
     */
    public function testIsAllowedDomainAllowsAnyWhenNotConfigured(): void {

        $config = $this->createMock(GetConfig::class);
        $config->method('get')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'google_oauth.allowed_domains' => null,
                    default => null,
                };
            });

        $service = new GoogleOAuthService($config);

        $this->assertTrue($service->isAllowedDomain('user@example.com'));
        $this->assertTrue($service->isAllowedDomain('user@another.org'));
    }

    /**
     * Tests isAllowedDomain allows any domain when config is empty
     *
     * Verifies that isAllowedDomain() returns true when allowed_domains
     * is an empty string.
     *
     * @return void
     */
    public function testIsAllowedDomainAllowsAnyWhenEmpty(): void {

        $config = $this->createMock(GetConfig::class);
        $config->method('get')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'google_oauth.allowed_domains' => '',
                    default => null,
                };
            });

        $service = new GoogleOAuthService($config);

        $this->assertTrue($service->isAllowedDomain('user@example.com'));
    }

    /**
     * Tests isAllowedDomain validates single domain
     *
     * Verifies that isAllowedDomain() correctly validates against
     * a single configured domain.
     *
     * @return void
     */
    public function testIsAllowedDomainValidatesSingleDomain(): void {

        $config = $this->createMock(GetConfig::class);
        $config->method('get')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'google_oauth.allowed_domains' => 'example.com',
                    default => null,
                };
            });

        $service = new GoogleOAuthService($config);

        $this->assertTrue($service->isAllowedDomain('user@example.com'));
        $this->assertFalse($service->isAllowedDomain('user@other.com'));
    }

    /**
     * Tests isAllowedDomain validates multiple domains
     *
     * Verifies that isAllowedDomain() correctly validates against
     * multiple comma-separated domains.
     *
     * @return void
     */
    public function testIsAllowedDomainValidatesMultipleDomains(): void {

        $config = $this->createMock(GetConfig::class);
        $config->method('get')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'google_oauth.allowed_domains' => 'example.com,company.org,test.net',
                    default => null,
                };
            });

        $service = new GoogleOAuthService($config);

        $this->assertTrue($service->isAllowedDomain('user@example.com'));
        $this->assertTrue($service->isAllowedDomain('user@company.org'));
        $this->assertTrue($service->isAllowedDomain('user@test.net'));
        $this->assertFalse($service->isAllowedDomain('user@other.com'));
    }

    /**
     * Tests isAllowedDomain is case insensitive
     *
     * Verifies that domain comparison is case-insensitive.
     *
     * @return void
     */
    public function testIsAllowedDomainIsCaseInsensitive(): void {

        $config = $this->createMock(GetConfig::class);
        $config->method('get')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'google_oauth.allowed_domains' => 'Example.COM',
                    default => null,
                };
            });

        $service = new GoogleOAuthService($config);

        $this->assertTrue($service->isAllowedDomain('user@example.com'));
        $this->assertTrue($service->isAllowedDomain('user@EXAMPLE.COM'));
        $this->assertTrue($service->isAllowedDomain('user@Example.Com'));
    }

    /**
     * Tests isAllowedDomain handles spaces in domain list
     *
     * Verifies that isAllowedDomain() trims whitespace around domains.
     *
     * @return void
     */
    public function testIsAllowedDomainHandlesSpaces(): void {

        $config = $this->createMock(GetConfig::class);
        $config->method('get')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'google_oauth.allowed_domains' => '  example.com , company.org  ',
                    default => null,
                };
            });

        $service = new GoogleOAuthService($config);

        $this->assertTrue($service->isAllowedDomain('user@example.com'));
        $this->assertTrue($service->isAllowedDomain('user@company.org'));
    }

    /**
     * Tests isAllowedDomain rejects invalid email format
     *
     * Verifies that isAllowedDomain() returns false for emails
     * without a valid @ separator.
     *
     * @return void
     */
    public function testIsAllowedDomainRejectsInvalidEmail(): void {

        $config = $this->createMock(GetConfig::class);
        $config->method('get')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'google_oauth.allowed_domains' => 'example.com',
                    default => null,
                };
            });

        $service = new GoogleOAuthService($config);

        $this->assertFalse($service->isAllowedDomain('invalidemail'));
        $this->assertFalse($service->isAllowedDomain(''));
    }

    /**
     * Tests isAllowedDomain handles only comma separators
     *
     * Verifies that isAllowedDomain() correctly handles a config
     * with only commas (no valid domains).
     *
     * @return void
     */
    public function testIsAllowedDomainHandlesOnlyCommas(): void {

        $config = $this->createMock(GetConfig::class);
        $config->method('get')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'google_oauth.allowed_domains' => ',,,',
                    default => null,
                };
            });

        $service = new GoogleOAuthService($config);

        // When all entries are empty after filtering, allow any domain
        $this->assertTrue($service->isAllowedDomain('user@example.com'));
    }

    /**
     * Tests generateRandomPassword returns non-empty string
     *
     * Verifies that generateRandomPassword() returns a non-empty
     * base64-encoded string.
     *
     * @return void
     */
    public function testGenerateRandomPasswordReturnsNonEmptyString(): void {

        $config = $this->createMock(GetConfig::class);
        $service = new GoogleOAuthService($config);

        $password = $service->generateRandomPassword();

        $this->assertNotEmpty($password);
        $this->assertIsString($password);
    }

    /**
     * Tests generateRandomPassword returns unique values
     *
     * Verifies that generateRandomPassword() returns different
     * values on subsequent calls (cryptographically random).
     *
     * @return void
     */
    public function testGenerateRandomPasswordReturnsUniqueValues(): void {

        $config = $this->createMock(GetConfig::class);
        $service = new GoogleOAuthService($config);

        $password1 = $service->generateRandomPassword();
        $password2 = $service->generateRandomPassword();

        $this->assertNotSame($password1, $password2);
    }

    /**
     * Tests generateRandomPassword returns sufficient length
     *
     * Verifies that generateRandomPassword() returns a password
     * of appropriate length (32 bytes base64 = ~44 chars).
     *
     * @return void
     */
    public function testGenerateRandomPasswordHasSufficientLength(): void {

        $config = $this->createMock(GetConfig::class);
        $service = new GoogleOAuthService($config);

        $password = $service->generateRandomPassword();

        // 32 bytes base64-encoded = 44 characters (including padding)
        $this->assertGreaterThanOrEqual(40, strlen($password));
    }

    /**
     * Tests getError returns empty string initially
     *
     * Verifies that getError() returns an empty string when
     * no error has occurred.
     *
     * @return void
     */
    public function testGetErrorReturnsEmptyStringInitially(): void {

        $config = $this->createMock(GetConfig::class);
        $service = new GoogleOAuthService($config);

        $this->assertSame('', $service->getError());
    }

    /**
     * Tests getState returns empty string before authorization
     *
     * Verifies that getState() returns an empty string before
     * getAuthorizationUrl() has been called.
     *
     * @return void
     */
    public function testGetStateReturnsEmptyStringInitially(): void {

        $config = $this->createMock(GetConfig::class);
        $service = new GoogleOAuthService($config);

        $this->assertSame('', $service->getState());
    }

    /**
     * Tests getAuthorizationUrl returns valid URL
     *
     * Verifies that getAuthorizationUrl() returns a URL pointing
     * to Google's OAuth endpoint.
     *
     * @return void
     */
    public function testGetAuthorizationUrlReturnsValidUrl(): void {

        $config = $this->createMock(GetConfig::class);
        $config->method('get')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'google_oauth.enabled' => 'true',
                    'google_oauth.client_id' => 'test-client-id.apps.googleusercontent.com',
                    'google_oauth.client_secret' => 'test-client-secret',
                    default => null,
                };
            });

        $service = new GoogleOAuthService($config);

        $auth_url = $service->getAuthorizationUrl('https://example.com/callback');

        $this->assertStringContainsString('accounts.google.com', $auth_url);
        $this->assertStringContainsString('client_id=', $auth_url);
        $this->assertStringContainsString('redirect_uri=', $auth_url);
    }

    /**
     * Tests getState returns non-empty string after getAuthorizationUrl
     *
     * Verifies that getState() returns a non-empty state parameter
     * after getAuthorizationUrl() has been called.
     *
     * @return void
     */
    public function testGetStateReturnsNonEmptyAfterAuthorizationUrl(): void {

        $config = $this->createMock(GetConfig::class);
        $config->method('get')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'google_oauth.enabled' => 'true',
                    'google_oauth.client_id' => 'test-client-id.apps.googleusercontent.com',
                    'google_oauth.client_secret' => 'test-client-secret',
                    default => null,
                };
            });

        $service = new GoogleOAuthService($config);

        $service->getAuthorizationUrl('https://example.com/callback');
        $state = $service->getState();

        $this->assertNotEmpty($state);
    }

    /**
     * Tests getAccessToken returns null with invalid code
     *
     * Verifies that getAccessToken() returns null when given
     * an invalid authorization code.
     *
     * @return void
     */
    public function testGetAccessTokenReturnsNullWithInvalidCode(): void {

        $config = $this->createMock(GetConfig::class);
        $config->method('get')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'google_oauth.enabled' => 'true',
                    'google_oauth.client_id' => 'test-client-id.apps.googleusercontent.com',
                    'google_oauth.client_secret' => 'test-client-secret',
                    default => null,
                };
            });

        $service = new GoogleOAuthService($config);

        $token = $service->getAccessToken(
            'invalid-code',
            'https://example.com/callback'
        );

        $this->assertNull($token);
    }

    /**
     * Tests getError returns message after failed token exchange
     *
     * Verifies that getError() returns an error message after
     * a failed getAccessToken() call.
     *
     * @return void
     */
    public function testGetErrorReturnsMessageAfterFailedTokenExchange(): void {

        $config = $this->createMock(GetConfig::class);
        $config->method('get')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'google_oauth.enabled' => 'true',
                    'google_oauth.client_id' => 'test-client-id.apps.googleusercontent.com',
                    'google_oauth.client_secret' => 'test-client-secret',
                    default => null,
                };
            });

        $service = new GoogleOAuthService($config);

        $service->getAccessToken('invalid-code', 'https://example.com/callback');
        $error = $service->getError();

        $this->assertNotEmpty($error);
    }

    /**
     * Tests isEnabled with empty client_id string
     *
     * Verifies that isEnabled() returns false when client_id
     * is an empty string.
     *
     * @return void
     */
    public function testIsEnabledReturnsFalseWithEmptyClientId(): void {

        $config = $this->createMock(GetConfig::class);
        $config->method('get')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'google_oauth.enabled' => 'true',
                    'google_oauth.client_id' => '',
                    'google_oauth.client_secret' => 'test-client-secret',
                    default => null,
                };
            });

        $service = new GoogleOAuthService($config);

        $this->assertFalse($service->isEnabled());
    }

    /**
     * Tests isEnabled with empty client_secret string
     *
     * Verifies that isEnabled() returns false when client_secret
     * is an empty string.
     *
     * @return void
     */
    public function testIsEnabledReturnsFalseWithEmptyClientSecret(): void {

        $config = $this->createMock(GetConfig::class);
        $config->method('get')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'google_oauth.enabled' => 'true',
                    'google_oauth.client_id' => 'test-client-id',
                    'google_oauth.client_secret' => '',
                    default => null,
                };
            });

        $service = new GoogleOAuthService($config);

        $this->assertFalse($service->isEnabled());
    }
}
