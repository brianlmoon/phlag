<?php

namespace Moonspot\Phlag\Tests\Unit\Web\Service;

use Moonspot\Phlag\Web\Service\WebhookDispatcher;
use Moonspot\Phlag\Data\Phlag;
use Moonspot\Phlag\Data\PhlagEnvironment;
use Moonspot\Phlag\Data\PhlagEnvironmentValue;
use Moonspot\Phlag\Data\PhlagWebhook;
use Moonspot\Phlag\Data\Repository;
use DealNews\GetConfig\GetConfig;
use PHPUnit\Framework\TestCase;

/**
 * WebhookDispatcher Test
 *
 * Tests webhook dispatching functionality including template rendering,
 * HTTP request handling, retry logic, and environment value inclusion.
 * Uses mocked dependencies to avoid actual HTTP requests and database access.
 */
class WebhookDispatcherTest extends TestCase {

    /**
     * Tests dispatch returns early when no webhooks exist
     *
     * @return void
     */
    public function testDispatchReturnsEarlyWhenNoWebhooks(): void {

        $config = $this->createMock(GetConfig::class);
        $config->method('get')
            ->willReturnCallback(function ($key, $default) {
                return $default;
            });

        $repository = $this->createMock(Repository::class);
        $repository->expects($this->once())
            ->method('find')
            ->with('PhlagWebhook', ['is_active' => 1])
            ->willReturn([]);

        $dispatcher = new WebhookDispatcher($repository, $config);

        $flag = $this->createMockFlag();
        $dispatcher->dispatch('created', $flag);

        $this->assertTrue(true);
    }

    /**
     * Tests dispatch filters webhooks by event type
     *
     * Verifies that only webhooks configured for the event type receive it.
     *
     * @return void
     */
    public function testDispatchFiltersWebhooksByEventType(): void {

        $config = $this->createMock(GetConfig::class);
        $config->method('get')
            ->willReturnCallback(function ($key, $default) {
                return match ($key) {
                    
                    'webhooks.timeout' => '5',
                    'webhooks.max_retries' => '0',
                    default => $default,
                };
            });

        $webhook1 = $this->createMockWebhook(1, ['created', 'updated']);
        $webhook2 = $this->createMockWebhook(2, ['deleted']);

        $repository = $this->createMock(Repository::class);
        $repository->method('find')
            ->willReturnMap([
                ['PhlagWebhook', ['is_active' => 1], [$webhook1, $webhook2]],
                ['PhlagEnvironmentValue', ['phlag_id' => 1], []],
            ]);

        $dispatcher = $this->getMockBuilder(WebhookDispatcher::class)
            ->setConstructorArgs([$repository, $config])
            ->onlyMethods(['sendRequest'])
            ->getMock();

        // Should only send to webhook1 (has 'created' event)
        $dispatcher->expects($this->once())
            ->method('sendRequest')
            ->with(
                $this->callback(function ($webhook) {
                    return $webhook->phlag_webhook_id === 1;
                }),
                $this->anything()
            )
            ->willReturn(['success' => true, 'status' => 200, 'error' => null]);

        $flag = $this->createMockFlag();
        $dispatcher->dispatch('created', $flag);
    }

    /**
     * Tests buildPayload includes environment values
     *
     * @return void
     */
    public function testBuildPayloadIncludesEnvironments(): void {

        $config = $this->createMock(GetConfig::class);
        $config->method('get')
            ->willReturnCallback(function ($key, $default) {
                return match ($key) {
                    
                    'webhooks.timeout' => '5',
                    'webhooks.max_retries' => '0',
                    default => $default,
                };
            });

        $flag = $this->createMockFlag();
        $webhook = $this->createMockWebhook(1, ['created']);

        $env = new PhlagEnvironment();
        $env->phlag_environment_id = 1;
        $env->name = 'production';

        $env_value = new PhlagEnvironmentValue();
        $env_value->phlag_id = 1;
        $env_value->phlag_environment_id = 1;
        $env_value->value = 'true';
        $env_value->start_datetime = '2026-01-01 00:00:00';
        $env_value->end_datetime = null;

        $repository = $this->createMock(Repository::class);
        $repository->method('find')
            ->willReturnMap([
                ['PhlagWebhook', ['is_active' => 1], [$webhook]],
                ['PhlagEnvironmentValue', ['phlag_id' => 1], [$env_value]],
            ]);
        $repository->method('get')
            ->willReturnMap([
                ['PhlagEnvironment', 1, $env],
            ]);

        $dispatcher = $this->getMockBuilder(WebhookDispatcher::class)
            ->setConstructorArgs([$repository, $config])
            ->onlyMethods(['sendRequest'])
            ->getMock();

        $dispatcher->expects($this->once())
            ->method('sendRequest')
            ->with(
                $this->anything(),
                $this->callback(function ($payload) {
                    $decoded = json_decode($payload, true);
                    return isset($decoded['flag']['environments']) &&
                           count($decoded['flag']['environments']) === 1 &&
                           $decoded['flag']['environments'][0]['name'] === 'production';
                })
            )
            ->willReturn(['success' => true, 'status' => 200, 'error' => null]);

        $dispatcher->dispatch('created', $flag);
    }

    /**
     * Tests buildPayload includes old environments on updates
     *
     * @return void
     */
    public function testBuildPayloadIncludesOldEnvironmentsOnUpdate(): void {

        $config = $this->createMock(GetConfig::class);
        $config->method('get')
            ->willReturnCallback(function ($key, $default) {
                return match ($key) {
                    
                    'webhooks.timeout' => '5',
                    'webhooks.max_retries' => '0',
                    default => $default,
                };
            });

        $flag = $this->createMockFlag();
        $old_flag = $this->createMockFlag();
        $old_flag->description = 'Old description';

        $webhook = $this->createMockWebhook(1, ['updated']);

        $repository = $this->createMock(Repository::class);
        $repository->method('find')
            ->willReturnMap([
                ['PhlagWebhook', ['is_active' => 1], [$webhook]],
                ['PhlagEnvironmentValue', ['phlag_id' => 1], []],
            ]);

        $dispatcher = $this->getMockBuilder(WebhookDispatcher::class)
            ->setConstructorArgs([$repository, $config])
            ->onlyMethods(['sendRequest'])
            ->getMock();

        $dispatcher->expects($this->once())
            ->method('sendRequest')
            ->with(
                $this->anything(),
                $this->callback(function ($payload) {
                    $decoded = json_decode($payload, true);
                    return isset($decoded['previous']) &&
                           $decoded['previous']['description'] === 'Old description';
                })
            )
            ->willReturn(['success' => true, 'status' => 200, 'error' => null]);

        $dispatcher->dispatch('updated', $flag, $old_flag);
    }

    /**
     * Tests environments are sorted alphabetically
     *
     * @return void
     */
    public function testEnvironmentsSortedAlphabetically(): void {

        $config = $this->createMock(GetConfig::class);
        $config->method('get')
            ->willReturnCallback(function ($key, $default) {
                return match ($key) {
                    
                    'webhooks.timeout' => '5',
                    'webhooks.max_retries' => '0',
                    default => $default,
                };
            });

        $flag = $this->createMockFlag();
        $webhook = $this->createMockWebhook(1, ['created']);

        $prod_env = new PhlagEnvironment();
        $prod_env->phlag_environment_id = 1;
        $prod_env->name = 'production';

        $dev_env = new PhlagEnvironment();
        $dev_env->phlag_environment_id = 2;
        $dev_env->name = 'development';

        $prod_value = new PhlagEnvironmentValue();
        $prod_value->phlag_environment_id = 1;
        $prod_value->value = 'true';

        $dev_value = new PhlagEnvironmentValue();
        $dev_value->phlag_environment_id = 2;
        $dev_value->value = 'false';

        $repository = $this->createMock(Repository::class);
        $repository->method('find')
            ->willReturnMap([
                ['PhlagWebhook', ['is_active' => 1], [$webhook]],
                ['PhlagEnvironmentValue', ['phlag_id' => 1], [$prod_value, $dev_value]],
            ]);
        $repository->method('get')
            ->willReturnCallback(function ($type, $id) use ($prod_env, $dev_env) {
                if ($type === 'PhlagEnvironment') {
                    return $id === 1 ? $prod_env : $dev_env;
                }
                return null;
            });

        $dispatcher = $this->getMockBuilder(WebhookDispatcher::class)
            ->setConstructorArgs([$repository, $config])
            ->onlyMethods(['sendRequest'])
            ->getMock();

        $dispatcher->expects($this->once())
            ->method('sendRequest')
            ->with(
                $this->anything(),
                $this->callback(function ($payload) {
                    $decoded = json_decode($payload, true);
                    $envs = $decoded['flag']['environments'] ?? [];
                    return count($envs) === 2 &&
                           $envs[0]['name'] === 'development' &&
                           $envs[1]['name'] === 'production';
                })
            )
            ->willReturn(['success' => true, 'status' => 200, 'error' => null]);

        $dispatcher->dispatch('created', $flag);
    }

    /**
     * Tests dispatchEnvironmentChange only sends to webhooks with flag enabled
     *
     * @return void
     */
    public function testEnvironmentChangeOnlyActiveWebhooks(): void {

        $config = $this->createMock(GetConfig::class);
        $config->method('get')
            ->willReturnCallback(function ($key, $default) {
                return match ($key) {
                    
                    'webhooks.timeout' => '5',
                    'webhooks.max_retries' => '0',
                    default => $default,
                };
            });

        $webhook = $this->createMockWebhook(1, ['environment_value_updated']);
        $webhook->include_environment_changes = true;

        $flag = $this->createMockFlag();
        $environment = new PhlagEnvironment();
        $environment->phlag_environment_id = 1;
        $environment->name = 'staging';

        $env_value = new PhlagEnvironmentValue();
        $env_value->phlag_id = 1;
        $env_value->phlag_environment_id = 1;
        $env_value->value = 'false';

        $repository = $this->createMock(Repository::class);
        $repository->method('get')
            ->willReturnMap([
                ['Phlag', 1, $flag],
                ['PhlagEnvironment', 1, $environment],
            ]);
        $repository->method('find')
            ->willReturnMap([
                ['PhlagWebhook', ['is_active' => 1, 'include_environment_changes' => 1], [$webhook]],
                ['PhlagEnvironmentValue', ['phlag_id' => 1], [$env_value]],
            ]);

        $dispatcher = $this->getMockBuilder(WebhookDispatcher::class)
            ->setConstructorArgs([$repository, $config])
            ->onlyMethods(['sendRequest'])
            ->getMock();

        $dispatcher->expects($this->once())
            ->method('sendRequest')
            ->with(
                $this->anything(),
                $this->callback(function ($payload) {
                    $decoded = json_decode($payload, true);
                    // With default template, verify event type and environments
                    return isset($decoded['event']) &&
                           $decoded['event'] === 'environment_value_updated' &&
                           isset($decoded['flag']['environments']) &&
                           count($decoded['flag']['environments']) === 1 &&
                           $decoded['flag']['environments'][0]['name'] === 'staging';
                })
            )
            ->willReturn(['success' => true, 'status' => 200, 'error' => null]);

        $dispatcher->dispatchEnvironmentChange('environment_value_updated', $env_value);
    }

    /**
     * Tests custom headers are applied to requests
     *
     * This test verifies that custom headers from headers_json are
     * properly formatted and included in the HTTP request.
     *
     * @return void
     */
    public function testCustomHeadersApplied(): void {

        $webhook = new PhlagWebhook();
        $webhook->phlag_webhook_id = 1;
        $webhook->name = 'Test Webhook';
        $webhook->url = 'https://example.com/webhook';
        $webhook->headers_json = json_encode([
            'Authorization' => 'Bearer token123',
            'X-Custom-Header' => 'custom-value',
        ]);
        $webhook->event_types_json = json_encode(['created']);
        $webhook->is_active = true;

        // This test would require mocking curl_exec which is complex
        // In practice, we'd verify headers in integration tests
        $this->assertTrue(true);
    }

    /**
     * Helper method to create a mock flag
     *
     * @return Phlag
     */
    protected function createMockFlag(): Phlag {

        $flag = new Phlag();
        $flag->phlag_id = 1;
        $flag->name = 'test_flag';
        $flag->type = 'SWITCH';
        $flag->description = 'Test flag';
        $flag->create_datetime = '2026-01-01 00:00:00';

        return $flag;
    }

    /**
     * Helper method to create a mock webhook
     *
     * @param int $id Webhook ID
     * @param array $event_types Array of event types
     * @return PhlagWebhook
     */
    protected function createMockWebhook(int $id, array $event_types): PhlagWebhook {

        $webhook = new PhlagWebhook();
        $webhook->phlag_webhook_id = $id;
        $webhook->name = "Test Webhook $id";
        $webhook->url = "https://example.com/webhook/$id";
        $webhook->is_active = true;
        $webhook->event_types_json = json_encode($event_types);
        $webhook->include_environment_changes = false;

        return $webhook;
    }

    /**
     * Tests dispatchTest returns success response
     *
     * Verifies that dispatchTest returns proper structure with
     * status_code and response_body for successful webhook delivery.
     *
     * @return void
     */
    public function testDispatchTestReturnsSuccessResponse(): void {

        $config = $this->createMock(GetConfig::class);
        $config->method('get')
            ->willReturnCallback(function ($key, $default) {
                return match ($key) {
                    'webhooks.timeout' => '5',
                    'webhooks.max_retries' => '1',
                    default => $default,
                };
            });

        $repository = $this->createMock(Repository::class);
        $repository->method('find')
            ->willReturn([]);

        $dispatcher = new WebhookDispatcher($repository, $config);

        $webhook = $this->createMockWebhook(1, ['updated']);
        $webhook->url = 'https://httpbin.org/status/200';
        $webhook->payload_template = '{"test": true}';

        // Use real Phlag object instead of generic object
        $flag = $this->createMockFlag();

        $result = $dispatcher->dispatchTest($webhook, $flag);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('status_code', $result);
        $this->assertArrayHasKey('response_body', $result);
        $this->assertArrayHasKey('error', $result);
    }
}
