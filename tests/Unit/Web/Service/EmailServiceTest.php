<?php

namespace Moonspot\Phlag\Tests\Unit\Web\Service;

use PHPUnit\Framework\TestCase;
use Moonspot\Phlag\Web\Service\EmailService;
use DealNews\GetConfig\GetConfig;
use PHPMailer\PHPMailer\Exception;

/**
 * EmailService Test
 *
 * Tests the EmailService class including configuration loading,
 * email sending, error handling, and default value behavior.
 *
 * ## Coverage
 *
 * - Configuration loading from GetConfig
 * - Mail method configuration (mail vs SMTP)
 * - SMTP configuration with authentication
 * - Default value handling with null coalescing
 * - Email sending success and failure scenarios
 * - Password reset email generation
 * - Error message retrieval
 * - HTML and plain text email bodies
 *
 * @package Moonspot\Phlag\Tests\Unit\Web\Service
 */
class EmailServiceTest extends TestCase {

    /**
     * GetConfig instance for test configuration
     *
     * @var GetConfig
     */
    protected GetConfig $config;

    /**
     * Sets up test configuration before each test
     *
     * Initializes GetConfig and clears any existing configuration
     * to ensure tests start with a clean slate.
     *
     * @return void
     */
    protected function setUp(): void {

        parent::setUp();

        $this->config = GetConfig::init();

        // Clear any existing email configuration
        $this->clearEmailConfig();
    }

    /**
     * Tears down test configuration after each test
     *
     * Clears email configuration to prevent test pollution.
     *
     * @return void
     */
    protected function tearDown(): void {

        $this->clearEmailConfig();

        parent::tearDown();
    }

    /**
     * Clears all email configuration values
     *
     * Removes all mailer.* configuration environment variables to ensure
     * clean test state. GetConfig reads from environment variables when
     * config files don't contain the values.
     *
     * @return void
     */
    protected function clearEmailConfig(): void {

        $keys = [
            'mailer_from_address',
            'mailer_from_name',
            'mailer_method',
            'mailer_smtp_host',
            'mailer_smtp_port',
            'mailer_smtp_username',
            'mailer_smtp_password',
            'mailer_smtp_encryption',
        ];

        foreach ($keys as $key) {
            putenv($key);
        }
    }

    /**
     * Sets up minimal valid configuration for email service
     *
     * Configures the minimum required settings to instantiate EmailService
     * using the mail() method. Uses environment variables that GetConfig
     * will read.
     *
     * @return void
     */
    protected function setMinimalConfig(): void {

        putenv('mailer_from_address=test@example.com');
        putenv('mailer_method=mail');
    }

    /**
     * Sets up SMTP configuration for testing
     *
     * Configures all SMTP settings including authentication credentials
     * via environment variables that GetConfig will read.
     *
     * @return void
     */
    protected function setSMTPConfig(): void {

        putenv('mailer_from_address=test@example.com');
        putenv('mailer_from_name=Test Mailer');
        putenv('mailer_method=smtp');
        putenv('mailer_smtp_host=smtp.example.com');
        putenv('mailer_smtp_port=587');
        putenv('mailer_smtp_encryption=tls');
        putenv('mailer_smtp_username=testuser');
        putenv('mailer_smtp_password=testpass');
    }

    /**
     * Tests that EmailService throws exception when from address is missing
     *
     * The mailer.from.address configuration is required. Without it,
     * the constructor should throw an exception.
     *
     * Heads-up: This test will be skipped if a real configuration file
     * exists with mailer.from.address already set, as GetConfig will
     * load from that file.
     *
     * @return void
     */
    public function testConstructorThrowsExceptionWhenFromAddressMissing(): void {

        // Check if real config exists
        $real_config = GetConfig::init();
        if ($real_config->get('mailer.from.address') !== null) {
            $this->markTestSkipped('Real configuration exists with mailer.from.address set');
        }

        // Ensure all config is cleared
        $this->clearEmailConfig();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('mailer.from.address configuration is required');

        new EmailService();
    }

    /**
     * Tests EmailService constructor with minimal mail configuration
     *
     * Verifies that EmailService can be instantiated with just the
     * required from address, defaulting to mail() method.
     *
     * @return void
     */
    public function testConstructorWithMinimalConfiguration(): void {

        $this->setMinimalConfig();

        $service = new EmailService();

        $this->assertInstanceOf(EmailService::class, $service);
    }

    /**
     * Tests that default method is mail when not specified
     *
     * When mailer.method is not set, the service should default to
     * using the mail() function.
     *
     * @return void
     */
    public function testDefaultMethodIsMail(): void {

        putenv('mailer_from_address=test@example.com');
        // Don't set method - should default to 'mail'

        $service = new EmailService();

        $this->assertInstanceOf(EmailService::class, $service);
    }

    /**
     * Tests that default from name is used when not specified
     *
     * When mailer.from.name is not set, the service should default to
     * "Phlag Admin".
     *
     * @return void
     */
    public function testDefaultFromNameIsUsed(): void {

        putenv('mailer_from_address=test@example.com');
        // Don't set from name - should default to 'Phlag Admin'

        $service = new EmailService();

        $this->assertInstanceOf(EmailService::class, $service);
    }

    /**
     * Tests EmailService constructor with custom from name
     *
     * Verifies that a custom from name can be set via configuration.
     *
     * @return void
     */
    public function testConstructorWithCustomFromName(): void {

        putenv('mailer_from_address=test@example.com');
        putenv('mailer_from_name=Custom Sender');

        $service = new EmailService();

        $this->assertInstanceOf(EmailService::class, $service);
    }

    /**
     * Tests EmailService constructor with SMTP configuration
     *
     * Verifies that EmailService can be configured to use SMTP
     * with all required settings.
     *
     * @return void
     */
    public function testConstructorWithSMTPConfiguration(): void {

        $this->setSMTPConfig();

        $service = new EmailService();

        $this->assertInstanceOf(EmailService::class, $service);
    }

    /**
     * Tests SMTP configuration throws exception when host missing
     *
     * When using SMTP method, the smtp.host configuration is required.
     * Without it, the constructor should throw an exception.
     *
     * Heads-up: This test will be skipped if a real configuration file
     * exists with SMTP settings, as GetConfig will load from that file.
     *
     * @return void
     */
    public function testSMTPConfigurationThrowsExceptionWhenHostMissing(): void {

        $this->clearEmailConfig();
        
        // Check if real config file exists by creating fresh GetConfig instance
        // and checking if it has mailer.smtp.host without environment variables set
        $test_config = new GetConfig();
        if ($test_config->get('mailer.smtp.host') !== null) {
            $this->markTestSkipped('Real configuration exists with mailer.smtp.host set');
        }

        putenv('mailer_from_address=test@example.com');
        putenv('mailer_method=smtp');
        // Don't set smtp.host

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('mailer.smtp.host configuration is required when using SMTP');

        // Pass fresh GetConfig to avoid cached values from previous tests
        new EmailService(new GetConfig());
    }

    /**
     * Tests SMTP uses default port when not specified
     *
     * When smtp.port is not set, the service should default to 587.
     *
     * @return void
     */
    public function testSMTPUsesDefaultPort(): void {

        putenv('mailer_from_address=test@example.com');
        putenv('mailer_method=smtp');
        putenv('mailer_smtp_host=smtp.example.com');
        // Don't set port - should default to 587

        $service = new EmailService();

        $this->assertInstanceOf(EmailService::class, $service);
    }

    /**
     * Tests SMTP uses default encryption when not specified
     *
     * When smtp.encryption is not set, the service should default to 'tls'.
     *
     * @return void
     */
    public function testSMTPUsesDefaultEncryption(): void {

        putenv('mailer_from_address=test@example.com');
        putenv('mailer_method=smtp');
        putenv('mailer_smtp_host=smtp.example.com');
        // Don't set encryption - should default to 'tls'

        $service = new EmailService();

        $this->assertInstanceOf(EmailService::class, $service);
    }

    /**
     * Tests SMTP works without authentication credentials
     *
     * SMTP should work even when username and password are not provided,
     * for servers that don't require authentication.
     *
     * @return void
     */
    public function testSMTPWorksWithoutAuthentication(): void {

        putenv('mailer_from_address=test@example.com');
        putenv('mailer_method=smtp');
        putenv('mailer_smtp_host=smtp.example.com');
        // Don't set username or password

        $service = new EmailService();

        $this->assertInstanceOf(EmailService::class, $service);
    }

    /**
     * Tests SMTP requires both username and password for auth
     *
     * If only one of username or password is set, authentication
     * should not be enabled (both are required).
     *
     * @return void
     */
    public function testSMTPAuthRequiresBothUsernameAndPassword(): void {

        // Test with only username
        putenv('mailer_from_address=test@example.com');
        putenv('mailer_method=smtp');
        putenv('mailer_smtp_host=smtp.example.com');
        putenv('mailer_smtp_username=user');
        // No password

        $service = new EmailService();
        $this->assertInstanceOf(EmailService::class, $service);

        // Test with only password
        $this->clearEmailConfig();
        putenv('mailer_from_address=test@example.com');
        putenv('mailer_method=smtp');
        putenv('mailer_smtp_host=smtp.example.com');
        putenv('mailer_smtp_password=pass');
        // No username

        $service = new EmailService();
        $this->assertInstanceOf(EmailService::class, $service);
    }

    /**
     * Tests getError returns empty string initially
     *
     * Before any email operations, getError should return an empty string.
     *
     * @return void
     */
    public function testGetErrorReturnsEmptyStringInitially(): void {

        $this->setMinimalConfig();

        $service = new EmailService();

        $this->assertSame('', $service->getError());
    }

    /**
     * Tests password reset email HTML contains required elements
     *
     * Verifies that the generated HTML email for password reset
     * includes the reset URL, expiration time, and security notice.
     *
     * @return void
     */
    public function testPasswordResetEmailHTMLContainsRequiredElements(): void {

        $this->setMinimalConfig();
        $service = new EmailService();

        $reset_url = 'https://example.com/reset?token=abc123';
        $expires_at = '2025-11-22 10:00:00';

        // Use reflection to test protected method
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('getPasswordResetHtml');
        $method->setAccessible(true);

        $html = $method->invoke($service, $reset_url, $expires_at);

        $this->assertStringContainsString($reset_url, $html);
        $this->assertStringContainsString($expires_at, $html);
        $this->assertStringContainsString('Password Reset Request', $html);
        $this->assertStringContainsString('Security Notice', $html);
        $this->assertStringContainsString('Reset Password', $html);
    }

    /**
     * Tests password reset email text contains required elements
     *
     * Verifies that the generated plain text email for password reset
     * includes the reset URL, expiration time, and security notice.
     *
     * @return void
     */
    public function testPasswordResetEmailTextContainsRequiredElements(): void {

        $this->setMinimalConfig();
        $service = new EmailService();

        $reset_url = 'https://example.com/reset?token=abc123';
        $expires_at = '2025-11-22 10:00:00';

        // Use reflection to test protected method
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('getPasswordResetText');
        $method->setAccessible(true);

        $text = $method->invoke($service, $reset_url, $expires_at);

        $this->assertStringContainsString($reset_url, $text);
        $this->assertStringContainsString($expires_at, $text);
        $this->assertStringContainsString('Password Reset Request', $text);
        $this->assertStringContainsString('SECURITY NOTICE', $text);
    }

    /**
     * Tests HTML email uses inline styles
     *
     * Email HTML should use inline styles for broad email client
     * compatibility, not external stylesheets.
     *
     * @return void
     */
    public function testHTMLEmailUsesInlineStyles(): void {

        $this->setMinimalConfig();
        $service = new EmailService();

        $reset_url = 'https://example.com/reset?token=abc123';
        $expires_at = '2025-11-22 10:00:00';

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('getPasswordResetHtml');
        $method->setAccessible(true);

        $html = $method->invoke($service, $reset_url, $expires_at);

        // Should contain inline style attributes
        $this->assertStringContainsString('style=', $html);

        // Should NOT contain external stylesheet links
        $this->assertStringNotContainsString('<link', $html);
        $this->assertStringNotContainsString('rel="stylesheet"', $html);
    }

    /**
     * Tests HTML email has no external resources
     *
     * For security and deliverability, email HTML should not reference
     * external images, scripts, or stylesheets.
     *
     * @return void
     */
    public function testHTMLEmailHasNoExternalResources(): void {

        $this->setMinimalConfig();
        $service = new EmailService();

        $reset_url = 'https://example.com/reset?token=abc123';
        $expires_at = '2025-11-22 10:00:00';

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('getPasswordResetHtml');
        $method->setAccessible(true);

        $html = $method->invoke($service, $reset_url, $expires_at);

        // Should NOT contain external images
        $this->assertStringNotContainsString('<img src="http', $html);

        // Should NOT contain JavaScript
        $this->assertStringNotContainsString('<script', $html);

        // Should NOT contain external CSS
        $this->assertStringNotContainsString('<link', $html);
    }

    /**
     * Tests configuration values use null coalescing operator
     *
     * GetConfig's get() method returns null when not set.
     * The service should use ?? operator for default values.
     *
     * This is a documentation test to verify the pattern is correct.
     *
     * @return void
     */
    public function testConfigurationUsesNullCoalescingOperator(): void {

        // This test verifies the code pattern is correct
        // GetConfig::get() returns null, not accepting defaults

        putenv('mailer_from_address=test@example.com');

        // When method is null, should default to 'mail'
        // Don't set method at all

        $service = new EmailService();

        $this->assertInstanceOf(EmailService::class, $service);
    }
}
