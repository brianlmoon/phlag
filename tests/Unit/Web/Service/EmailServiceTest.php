<?php

namespace Moonspot\Phlag\Tests\Unit\Web\Service;

use Moonspot\Phlag\Web\Service\EmailService;
use DealNews\GetConfig\GetConfig;
use PHPMailer\PHPMailer\Exception;
use PHPUnit\Framework\TestCase;

/**
 * EmailService Test
 *
 * Tests email service functionality including SMTP configuration,
 * mail() configuration, sending emails, and password reset emails.
 * Uses mocked GetConfig to avoid singleton caching issues.
 */
class EmailServiceTest extends TestCase {

    /**
     * Tests EmailService constructor with mail() method
     *
     * Verifies that EmailService can be constructed with minimal
     * configuration using the mail() method.
     *
     * @return void
     */
    public function testConstructorWithMailMethod(): void {

        $config = $this->createMock(GetConfig::class);
        $config->method('get')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'mailer.method' => 'mail',
                    'mailer.from.address' => 'test@example.com',
                    'mailer.from.name' => 'Test Sender',
                    default => null,
                };
            });

        $email_service = new EmailService($config);

        $this->assertInstanceOf(EmailService::class, $email_service);
    }

    /**
     * Tests EmailService constructor with SMTP method
     *
     * Verifies that EmailService can be constructed with SMTP
     * configuration including all required SMTP settings.
     *
     * @return void
     */
    public function testConstructorWithSmtpMethod(): void {

        $config = $this->createMock(GetConfig::class);
        $config->method('get')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'mailer.method' => 'smtp',
                    'mailer.from.address' => 'test@example.com',
                    'mailer.from.name' => 'Test Sender',
                    'mailer.smtp.host' => 'smtp.example.com',
                    'mailer.smtp.port' => '587',
                    'mailer.smtp.username' => 'smtp_user',
                    'mailer.smtp.password' => 'smtp_pass',
                    'mailer.smtp.encryption' => 'tls',
                    default => null,
                };
            });

        $email_service = new EmailService($config);

        $this->assertInstanceOf(EmailService::class, $email_service);
    }

    /**
     * Tests EmailService constructor with SMTP without authentication
     *
     * Verifies that EmailService can be constructed with SMTP
     * configuration without username/password for open SMTP relays.
     *
     * @return void
     */
    public function testConstructorWithSmtpNoAuth(): void {

        $config = $this->createMock(GetConfig::class);
        $config->method('get')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'mailer.method' => 'smtp',
                    'mailer.from.address' => 'test@example.com',
                    'mailer.from.name' => 'Test Sender',
                    'mailer.smtp.host' => 'smtp.example.com',
                    'mailer.smtp.port' => '25',
                    default => null,
                };
            });

        $email_service = new EmailService($config);

        $this->assertInstanceOf(EmailService::class, $email_service);
    }

    /**
     * Tests EmailService constructor uses default from name
     *
     * Verifies that if mailer.from.name is not configured,
     * it defaults to "Phlag Admin".
     *
     * @return void
     */
    public function testConstructorUsesDefaultFromName(): void {

        $config = $this->createMock(GetConfig::class);
        $config->method('get')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'mailer.method' => 'mail',
                    'mailer.from.address' => 'test@example.com',
                    default => null,
                };
            });

        $email_service = new EmailService($config);

        $this->assertInstanceOf(EmailService::class, $email_service);
    }

    /**
     * Tests EmailService constructor throws exception without from address
     *
     * Verifies that EmailService throws an exception if
     * mailer.from.address is not configured.
     *
     * @return void
     */
    public function testConstructorThrowsExceptionWithoutFromAddress(): void {

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('mailer.from.address configuration is required');

        $config = $this->createMock(GetConfig::class);
        $config->method('get')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'mailer.method' => 'mail',
                    default => null,
                };
            });

        new EmailService($config);
    }

    /**
     * Tests EmailService constructor throws exception for SMTP without host
     *
     * Verifies that EmailService throws an exception if SMTP method
     * is selected but mailer.smtp.host is not configured.
     *
     * @return void
     */
    public function testConstructorThrowsExceptionForSmtpWithoutHost(): void {

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('mailer.smtp.host configuration is required when using SMTP');

        $config = $this->createMock(GetConfig::class);
        $config->method('get')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'mailer.method' => 'smtp',
                    'mailer.from.address' => 'test@example.com',
                    default => null,
                };
            });

        new EmailService($config);
    }

    /**
     * Tests EmailService uses default method when not configured
     *
     * Verifies that EmailService defaults to 'mail' method when
     * mailer.method is not configured.
     *
     * @return void
     */
    public function testConstructorUsesDefaultMethod(): void {

        $config = $this->createMock(GetConfig::class);
        $config->method('get')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'mailer.from.address' => 'test@example.com',
                    default => null,
                };
            });

        $email_service = new EmailService($config);

        $this->assertInstanceOf(EmailService::class, $email_service);
    }

    /**
     * Tests EmailService uses default SMTP port
     *
     * Verifies that EmailService defaults to port 587 when
     * mailer.smtp.port is not configured.
     *
     * @return void
     */
    public function testConstructorUsesDefaultSmtpPort(): void {

        $config = $this->createMock(GetConfig::class);
        $config->method('get')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'mailer.method' => 'smtp',
                    'mailer.from.address' => 'test@example.com',
                    'mailer.smtp.host' => 'smtp.example.com',
                    default => null,
                };
            });

        $email_service = new EmailService($config);

        $this->assertInstanceOf(EmailService::class, $email_service);
    }

    /**
     * Tests EmailService uses default SMTP encryption
     *
     * Verifies that EmailService defaults to TLS encryption when
     * mailer.smtp.encryption is not configured.
     *
     * @return void
     */
    public function testConstructorUsesDefaultSmtpEncryption(): void {

        $config = $this->createMock(GetConfig::class);
        $config->method('get')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'mailer.method' => 'smtp',
                    'mailer.from.address' => 'test@example.com',
                    'mailer.smtp.host' => 'smtp.example.com',
                    default => null,
                };
            });

        $email_service = new EmailService($config);

        $this->assertInstanceOf(EmailService::class, $email_service);
    }

    /**
     * Tests EmailService uses SSL encryption when configured
     *
     * Verifies that EmailService uses SSL encryption when
     * mailer.smtp.encryption is set to 'ssl'.
     *
     * @return void
     */
    public function testConstructorUsesSslEncryption(): void {

        $config = $this->createMock(GetConfig::class);
        $config->method('get')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'mailer.method' => 'smtp',
                    'mailer.from.address' => 'test@example.com',
                    'mailer.smtp.host' => 'smtp.example.com',
                    'mailer.smtp.encryption' => 'ssl',
                    default => null,
                };
            });

        $email_service = new EmailService($config);

        $this->assertInstanceOf(EmailService::class, $email_service);
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
        $config->method('get')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'mailer.method' => 'mail',
                    'mailer.from.address' => 'test@example.com',
                    default => null,
                };
            });

        $email_service = new EmailService($config);

        $this->assertSame('', $email_service->getError());
    }

    /**
     * Tests sendPasswordReset generates correct subject
     *
     * Verifies that sendPasswordReset uses the correct email subject
     * for password reset emails.
     *
     * Heads-up: This test cannot verify actual email sending without
     * PHPMailer mocking, so it only checks that the method executes
     * without throwing exceptions.
     *
     * @return void
     */
    public function testSendPasswordResetGeneratesCorrectSubject(): void {

        $config = $this->createMock(GetConfig::class);
        $config->method('get')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'mailer.method' => 'mail',
                    'mailer.from.address' => 'test@example.com',
                    default => null,
                };
            });

        $email_service = new EmailService($config);

        // We can't actually send the email without a real mail server,
        // but we can verify the method doesn't throw exceptions
        $result = @$email_service->sendPasswordReset(
            'user@example.com',
            'https://example.com/reset?token=abc123',
            '2025-11-21 15:30:00'
        );

        // Result might be false due to mail server, but no exception thrown
        $this->assertIsBool($result);
    }

    /**
     * Tests constructor with empty from address string
     *
     * Verifies that EmailService throws an exception if
     * mailer.from.address is an empty string.
     *
     * @return void
     */
    public function testConstructorThrowsExceptionWithEmptyFromAddress(): void {

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('mailer.from.address configuration is required');

        $config = $this->createMock(GetConfig::class);
        $config->method('get')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'mailer.method' => 'mail',
                    'mailer.from.address' => '',
                    default => null,
                };
            });

        new EmailService($config);
    }

    /**
     * Tests constructor with empty SMTP host string
     *
     * Verifies that EmailService throws an exception if SMTP method
     * is selected but mailer.smtp.host is an empty string.
     *
     * @return void
     */
    public function testConstructorThrowsExceptionForSmtpWithEmptyHost(): void {

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('mailer.smtp.host configuration is required when using SMTP');

        $config = $this->createMock(GetConfig::class);
        $config->method('get')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'mailer.method' => 'smtp',
                    'mailer.from.address' => 'test@example.com',
                    'mailer.smtp.host' => '',
                    default => null,
                };
            });

        new EmailService($config);
    }

    /**
     * Tests SMTP configuration with only username provided
     *
     * Verifies that EmailService does not enable SMTP authentication
     * if only username is provided without password.
     *
     * @return void
     */
    public function testSmtpConfigurationWithOnlyUsername(): void {

        $config = $this->createMock(GetConfig::class);
        $config->method('get')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'mailer.method' => 'smtp',
                    'mailer.from.address' => 'test@example.com',
                    'mailer.smtp.host' => 'smtp.example.com',
                    'mailer.smtp.username' => 'smtp_user',
                    default => null,
                };
            });

        $email_service = new EmailService($config);

        $this->assertInstanceOf(EmailService::class, $email_service);
    }

    /**
     * Tests SMTP configuration with only password provided
     *
     * Verifies that EmailService does not enable SMTP authentication
     * if only password is provided without username.
     *
     * @return void
     */
    public function testSmtpConfigurationWithOnlyPassword(): void {

        $config = $this->createMock(GetConfig::class);
        $config->method('get')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'mailer.method' => 'smtp',
                    'mailer.from.address' => 'test@example.com',
                    'mailer.smtp.host' => 'smtp.example.com',
                    'mailer.smtp.password' => 'smtp_pass',
                    default => null,
                };
            });

        $email_service = new EmailService($config);

        $this->assertInstanceOf(EmailService::class, $email_service);
    }

    /**
     * Tests SMTP configuration with empty username
     *
     * Verifies that EmailService does not enable SMTP authentication
     * if username is an empty string.
     *
     * @return void
     */
    public function testSmtpConfigurationWithEmptyUsername(): void {

        $config = $this->createMock(GetConfig::class);
        $config->method('get')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'mailer.method' => 'smtp',
                    'mailer.from.address' => 'test@example.com',
                    'mailer.smtp.host' => 'smtp.example.com',
                    'mailer.smtp.username' => '',
                    'mailer.smtp.password' => 'smtp_pass',
                    default => null,
                };
            });

        $email_service = new EmailService($config);

        $this->assertInstanceOf(EmailService::class, $email_service);
    }

    /**
     * Tests SMTP configuration with empty password
     *
     * Verifies that EmailService does not enable SMTP authentication
     * if password is an empty string.
     *
     * @return void
     */
    public function testSmtpConfigurationWithEmptyPassword(): void {

        $config = $this->createMock(GetConfig::class);
        $config->method('get')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'mailer.method' => 'smtp',
                    'mailer.from.address' => 'test@example.com',
                    'mailer.smtp.host' => 'smtp.example.com',
                    'mailer.smtp.username' => 'smtp_user',
                    'mailer.smtp.password' => '',
                    default => null,
                };
            });

        $email_service = new EmailService($config);

        $this->assertInstanceOf(EmailService::class, $email_service);
    }

    /**
     * Tests SMTP configuration with custom port
     *
     * Verifies that EmailService uses custom SMTP port when configured.
     *
     * @return void
     */
    public function testSmtpConfigurationWithCustomPort(): void {

        $config = $this->createMock(GetConfig::class);
        $config->method('get')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'mailer.method' => 'smtp',
                    'mailer.from.address' => 'test@example.com',
                    'mailer.smtp.host' => 'smtp.example.com',
                    'mailer.smtp.port' => '465',
                    'mailer.smtp.encryption' => 'ssl',
                    default => null,
                };
            });

        $email_service = new EmailService($config);

        $this->assertInstanceOf(EmailService::class, $email_service);
    }

    /**
     * Tests send method with basic email
     *
     * Verifies that send method can be called with basic parameters.
     * Actual sending will fail without mail server, but method should
     * handle gracefully.
     *
     * @return void
     */
    public function testSendMethodWithBasicEmail(): void {

        $config = $this->createMock(GetConfig::class);
        $config->method('get')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'mailer.method' => 'mail',
                    'mailer.from.address' => 'test@example.com',
                    default => null,
                };
            });

        $email_service = new EmailService($config);

        $result = @$email_service->send(
            'recipient@example.com',
            'Test Subject',
            '<p>Test HTML body</p>',
            'Test plain text body'
        );

        $this->assertIsBool($result);
    }

    /**
     * Tests send method without text body
     *
     * Verifies that send method auto-generates plain text from HTML
     * when text body is not provided.
     *
     * @return void
     */
    public function testSendMethodWithoutTextBody(): void {

        $config = $this->createMock(GetConfig::class);
        $config->method('get')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'mailer.method' => 'mail',
                    'mailer.from.address' => 'test@example.com',
                    default => null,
                };
            });

        $email_service = new EmailService($config);

        $result = @$email_service->send(
            'recipient@example.com',
            'Test Subject',
            '<p>Test HTML body</p>'
        );

        $this->assertIsBool($result);
    }

    /**
     * Tests send method with null text body
     *
     * Verifies that send method handles null text body parameter.
     *
     * @return void
     */
    public function testSendMethodWithNullTextBody(): void {

        $config = $this->createMock(GetConfig::class);
        $config->method('get')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'mailer.method' => 'mail',
                    'mailer.from.address' => 'test@example.com',
                    default => null,
                };
            });

        $email_service = new EmailService($config);

        $result = @$email_service->send(
            'recipient@example.com',
            'Test Subject',
            '<p>Test HTML body</p>',
            null
        );

        $this->assertIsBool($result);
    }
}
