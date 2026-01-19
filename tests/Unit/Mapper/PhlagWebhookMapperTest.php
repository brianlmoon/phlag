<?php

namespace Moonspot\Phlag\Tests\Unit\Mapper;

use Moonspot\Phlag\Data\PhlagWebhook;
use Moonspot\Phlag\Mapper\PhlagWebhook as PhlagWebhookMapper;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Tests the PhlagWebhook mapper functionality
 *
 * This test suite verifies URL validation, security constraints, and
 * event type validation in the PhlagWebhook mapper. Tests focus on the
 * validate() method which enforces webhook configuration rules.
 *
 * ## What We Test
 *
 * - URL format validation (HTTPS required, except localhost)
 * - Private IP range blocking
 * - Event types JSON validation
 * - Headers JSON validation
 * - Empty URL rejection
 * - Invalid URL format rejection
 *
 * Note: We use reflection to test protected methods since they contain
 * the core validation logic.
 *
 * @package Moonspot\Phlag\Tests\Unit\Mapper
 */
class PhlagWebhookMapperTest extends TestCase {

    /**
     * Calls the protected validate method via reflection
     *
     * @param PhlagWebhook $webhook
     * @return bool Validation result
     */
    protected function callValidate(PhlagWebhook $webhook): bool {

        $mapper = $this->getMockBuilder(PhlagWebhookMapper::class)
            ->disableOriginalConstructor()
            ->getMock();

        $method = new ReflectionMethod(PhlagWebhookMapper::class, 'validate');
        $method->setAccessible(true);

        return $method->invoke($mapper, $webhook);
    }

    /**
     * Calls the protected isPrivateIp method via reflection
     *
     * @param string $ip
     * @return bool True if IP is private
     */
    protected function callIsPrivateIp(string $ip): bool {

        $mapper = $this->getMockBuilder(PhlagWebhookMapper::class)
            ->disableOriginalConstructor()
            ->getMock();

        $method = new ReflectionMethod(PhlagWebhookMapper::class, 'isPrivateIp');
        $method->setAccessible(true);

        return $method->invoke($mapper, $ip);
    }

    /**
     * Test that validate accepts HTTPS URLs
     *
     * @return void
     */
    public function testValidateAcceptsHttpsUrls(): void {

        $webhook = new PhlagWebhook();
        $webhook->name = 'Test Webhook';
        $webhook->url = 'https://example.com/webhook';
        $webhook->event_types_json = json_encode(['created']);

        $this->assertTrue($this->callValidate($webhook));
    }

    /**
     * Test that validate accepts HTTP localhost URLs
     *
     * @return void
     */
    public function testValidateAcceptsHttpLocalhost(): void {

        $webhook = new PhlagWebhook();
        $webhook->name = 'Test Webhook';
        $webhook->url = 'http://localhost/webhook';
        $webhook->event_types_json = json_encode(['created']);

        $this->assertTrue($this->callValidate($webhook));
    }

    /**
     * Test that validate accepts HTTP 127.0.0.1 URLs
     *
     * @return void
     */
    public function testValidateAcceptsHttp127001(): void {

        $webhook = new PhlagWebhook();
        $webhook->name = 'Test Webhook';
        $webhook->url = 'http://127.0.0.1/webhook';
        $webhook->event_types_json = json_encode(['created']);

        $this->assertTrue($this->callValidate($webhook));
    }

    /**
     * Test that validate rejects HTTP non-localhost URLs
     *
     * @return void
     */
    public function testValidateRejectsHttpNonLocalhost(): void {

        $webhook = new PhlagWebhook();
        $webhook->name = 'Test Webhook';
        $webhook->url = 'http://example.com/webhook';
        $webhook->event_types_json = json_encode(['created']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('URL must use HTTPS');

        $this->callValidate($webhook);
    }

    /**
     * Test that validate rejects empty URLs
     *
     * @return void
     */
    public function testValidateRejectsEmptyUrl(): void {

        $webhook = new PhlagWebhook();
        $webhook->name = 'Test Webhook';
        $webhook->url = '';
        $webhook->event_types_json = json_encode(['created']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('URL is required');

        $this->callValidate($webhook);
    }

    /**
     * Test that validate rejects invalid URL formats
     *
     * @return void
     */
    public function testValidateRejectsInvalidUrlFormat(): void {

        $webhook = new PhlagWebhook();
        $webhook->name = 'Test Webhook';
        $webhook->url = 'not-a-valid-url';
        $webhook->event_types_json = json_encode(['created']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('URL is not valid');

        $this->callValidate($webhook);
    }

    /**
     * Test that validate rejects empty event types
     *
     * @return void
     */
    public function testValidateRejectsEmptyEventTypes(): void {

        $webhook = new PhlagWebhook();
        $webhook->name = 'Test Webhook';
        $webhook->url = 'https://example.com/webhook';
        $webhook->event_types_json = '';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one event type is required');

        $this->callValidate($webhook);
    }

    /**
     * Test that validate rejects invalid event types JSON
     *
     * @return void
     */
    public function testValidateRejectsInvalidEventTypesJson(): void {

        $webhook = new PhlagWebhook();
        $webhook->name = 'Test Webhook';
        $webhook->url = 'https://example.com/webhook';
        $webhook->event_types_json = 'not-valid-json';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('event_types_json must be valid JSON');

        $this->callValidate($webhook);
    }

    /**
     * Test that validate rejects empty event types array
     *
     * @return void
     */
    public function testValidateRejectsEmptyEventTypesArray(): void {

        $webhook = new PhlagWebhook();
        $webhook->name = 'Test Webhook';
        $webhook->url = 'https://example.com/webhook';
        $webhook->event_types_json = json_encode([]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one event type must be specified');

        $this->callValidate($webhook);
    }

    /**
     * Test that validate accepts valid headers JSON
     *
     * @return void
     */
    public function testValidateAcceptsValidHeadersJson(): void {

        $webhook = new PhlagWebhook();
        $webhook->name = 'Test Webhook';
        $webhook->url = 'https://example.com/webhook';
        $webhook->event_types_json = json_encode(['created']);
        $webhook->headers_json = json_encode(['Authorization' => 'Bearer token']);

        $this->assertTrue($this->callValidate($webhook));
    }

    /**
     * Test that validate rejects invalid headers JSON
     *
     * @return void
     */
    public function testValidateRejectsInvalidHeadersJson(): void {

        $webhook = new PhlagWebhook();
        $webhook->name = 'Test Webhook';
        $webhook->url = 'https://example.com/webhook';
        $webhook->event_types_json = json_encode(['created']);
        $webhook->headers_json = 'not-valid-json';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('headers_json must be valid JSON');

        $this->callValidate($webhook);
    }

    /**
     * Test that validate accepts null headers JSON
     *
     * @return void
     */
    public function testValidateAcceptsNullHeadersJson(): void {

        $webhook = new PhlagWebhook();
        $webhook->name = 'Test Webhook';
        $webhook->url = 'https://example.com/webhook';
        $webhook->event_types_json = json_encode(['created']);
        $webhook->headers_json = null;

        $this->assertTrue($this->callValidate($webhook));
    }

    /**
     * Test that isPrivateIp identifies private IP ranges
     *
     * @return void
     */
    public function testIsPrivateIpIdentifiesPrivateRanges(): void {

        $private_ips = [
            '10.0.0.1',
            '192.168.1.1',
            '172.16.0.1',
            '127.0.0.1',
        ];

        foreach ($private_ips as $ip) {
            $this->assertTrue(
                $this->callIsPrivateIp($ip),
                "Expected $ip to be identified as private"
            );
        }
    }

    /**
     * Test that isPrivateIp allows public IPs
     *
     * @return void
     */
    public function testIsPrivateIpAllowsPublicIps(): void {

        $public_ips = [
            '8.8.8.8',
            '1.1.1.1',
            '93.184.216.34', // example.com
        ];

        foreach ($public_ips as $ip) {
            $this->assertFalse(
                $this->callIsPrivateIp($ip),
                "Expected $ip to be identified as public"
            );
        }
    }

    /**
     * Test that validate accepts multiple event types
     *
     * @return void
     */
    public function testValidateAcceptsMultipleEventTypes(): void {

        $webhook = new PhlagWebhook();
        $webhook->name = 'Test Webhook';
        $webhook->url = 'https://example.com/webhook';
        $webhook->event_types_json = json_encode([
            'created',
            'updated',
            'deleted',
            'environment_value_updated',
        ]);

        $this->assertTrue($this->callValidate($webhook));
    }
}
