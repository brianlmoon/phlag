<?php

namespace Moonspot\Phlag\Web\Service;

use DealNews\GetConfig\GetConfig;
use Moonspot\Phlag\Data\Phlag;
use Moonspot\Phlag\Data\PhlagEnvironment;
use Moonspot\Phlag\Data\PhlagEnvironmentValue;
use Moonspot\Phlag\Data\PhlagWebhook;
use Moonspot\Phlag\Data\Repository;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * Webhook Dispatcher Service
 *
 * Dispatches HTTP POST requests to configured webhooks when flags change.
 * Supports Twig templating for payload customization and includes full
 * environment value context in all webhooks.
 *
 * ## Responsibilities
 *
 * - Fetch active webhooks matching event type
 * - Build payload from Twig template with flag and environment data
 * - Send HTTP POST requests with custom headers
 * - Retry failed requests once (configurable)
 * - Log errors without blocking flag operations
 *
 * ## Configuration
 *
 * Set optional configuration via GetConfig:
 *
 * - `webhooks.enabled` - true/false to enable webhooks globally
 * - `webhooks.timeout` - Timeout in seconds (default: 5)
 * - `webhooks.max_retries` - Max retry attempts (default: 1)
 * - `webhooks.allow_http` - Allow http:// URLs (default: false)
 *
 * ## Usage
 *
 * ```php
 * $dispatcher = new WebhookDispatcher();
 * $dispatcher->dispatch('updated', $flag, $old_flag);
 * ```
 *
 * ## Edge Cases
 *
 * - Never throws exceptions (fail-safe design)
 * - Logs all errors to error_log
 * - Skips inactive webhooks automatically
 * - Returns early if no webhooks match event type
 * - Handles missing environment values gracefully
 *
 * @package Moonspot\Phlag\Web\Service
 */
class WebhookDispatcher {

    /**
     * Repository for data access
     *
     * @var Repository
     */
    protected Repository $repository;

    /**
     * GetConfig instance for configuration
     *
     * @var GetConfig
     */
    protected GetConfig $config;

    /**
     * Twig environment for template rendering
     *
     * @var Environment
     */
    protected Environment $twig;

    /**
     * HTTP timeout in seconds
     *
     * @var int
     */
    protected int $timeout = 5;

    /**
     * Maximum retry attempts
     *
     * @var int
     */
    protected int $max_retries = 1;

    /**
     * Constructor
     *
     * Initializes repository, config, and Twig environment.
     * Accepts optional dependencies for testability.
     *
     * @param Repository|null $repository Optional repository instance
     * @param GetConfig|null $config Optional config instance
     */
    public function __construct(
        ?Repository $repository = null,
        ?GetConfig $config = null
    ) {
        $this->repository = $repository ?? Repository::init();
        $this->config = $config ?? new GetConfig();

        // Load configuration
        $this->timeout = (int) $this->config->get(
            'webhooks.timeout',
            '5'
        );
        $this->max_retries = (int) $this->config->get(
            'webhooks.max_retries',
            '1'
        );

        // Initialize Twig
        $loader = new ArrayLoader();
        $this->twig = new Environment($loader);
    }

    /**
     * Dispatches webhooks for a flag event.
     *
     * Fetches all active webhooks, filters by event type, and sends
     * HTTP POST requests. Retries once on failure. Logs errors but
     * never throws exceptions (fail-safe design).
     *
     * @param string $event_type 'created', 'updated', 'deleted'
     * @param Phlag $flag Current flag state
     * @param Phlag|null $old_flag Previous state (updates only)
     * @return void
     */
    public function dispatch(
        string $event_type,
        Phlag $flag,
        ?Phlag $old_flag = null
    ): void {

        // Check if webhooks are globally enabled
        $enabled = $this->config->get('webhooks.enabled', 'true');
        if ($enabled !== 'true' && $enabled !== '1') {
            return;
        }

        try {
            // Fetch active webhooks
            $webhooks = $this->repository->find('PhlagWebhook', [
                'is_active' => 1,
            ]);

            if (empty($webhooks)) {
                return;
            }

            foreach ($webhooks as $webhook) {
                // Check if webhook wants this event type
                $event_types = json_decode(
                    $webhook->event_types_json,
                    true
                );
                if (!in_array($event_type, $event_types)) {
                    continue;
                }

                // Build payload
                $payload = $this->buildPayload(
                    $webhook,
                    $event_type,
                    $flag,
                    $old_flag
                );

                // Send with retry
                $result = $this->sendRequest($webhook, $payload);

                if (!$result['success']) {
                    error_log(sprintf(
                        "Webhook '%s' failed for %s on flag '%s': %s",
                        $webhook->name,
                        $event_type,
                        $flag->name,
                        $result['error']
                    ));
                }
            }
        } catch (\Throwable $e) {
            error_log(sprintf(
                "Webhook dispatch failed: %s",
                $e->getMessage()
            ));
        }
    }

    /**
     * Dispatches a test webhook and returns the result.
     *
     * Unlike dispatch(), this method returns the HTTP response details
     * for testing purposes.
     * Uses real flag data and simulates a phlag update event (with
     * previous values) to validate the webhook's Twig template.
     *
     * @param PhlagWebhook $webhook Webhook to test
     * @param Phlag $flag Real flag object with data and environments
     * @return array {success: bool, status_code: int, response_body: string, error: ?string}
     */
    public function dispatchTest(
        PhlagWebhook $webhook,
        Phlag $flag
    ): array {

        $result = [
            'success'       => false,
            'status_code'   => 0,
            'response_body' => '',
            'error'         => null,
        ];

        try {
            // Build payload using production method with updated event
            // Use same flag value for "previous" to simulate update
            $payload = $this->buildPayload(
                $webhook,
                'updated',
                $flag,
                $flag
            );

            // Send request with cURL
            $ch = curl_init($webhook->url);

            if ($ch === false) {
                throw new \RuntimeException('curl_init failed');
            }

            // Build headers
            $headers = ['Content-Type: application/json'];
            if ($webhook->headers_json !== null) {
                $custom_headers = json_decode(
                    $webhook->headers_json,
                    true
                );
                if (is_array($custom_headers)) {
                    foreach ($custom_headers as $name => $value) {
                        $headers[] = "$name: $value";
                    }
                }
            }

            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => $this->timeout,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 3,
            ]);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);

            curl_close($ch);

            if ($response === false) {
                throw new \RuntimeException(
                    "cURL error: $error"
                );
            }

            $result['status_code'] = $http_code;
            $result['response_body'] = $response;

            // Consider 2xx status codes as success
            if ($http_code >= 200 && $http_code < 300) {
                $result['success'] = true;
            } else {
                $result['error'] = sprintf(
                    "HTTP %d: %s",
                    $http_code,
                    substr($response, 0, 200)
                );
            }
        } catch (\Throwable $e) {
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Dispatches webhooks for environment value changes.
     *
     * Only sends to webhooks with include_environment_changes enabled.
     * Fetches the parent flag and all current environment values to
     * provide full context in the payload.
     *
     * @param string $event_type 'environment_value_updated'
     * @param PhlagEnvironmentValue $env_value
     * @return void
     */
    public function dispatchEnvironmentChange(
        string $event_type,
        PhlagEnvironmentValue $env_value
    ): void {

        // Check if webhooks are globally enabled
        $enabled = $this->config->get('webhooks.enabled', 'true');
        if ($enabled !== 'true' && $enabled !== '1') {
            return;
        }

        try {
            // Fetch parent flag
            $flag = $this->repository->get('Phlag', $env_value->phlag_id);
            if ($flag === null) {
                return;
            }

            // Fetch environment name
            $environment = $this->repository->get(
                'PhlagEnvironment',
                $env_value->phlag_environment_id
            );
            if ($environment === null) {
                return;
            }

            // Find webhooks with environment tracking enabled
            $webhooks = $this->repository->find('PhlagWebhook', [
                'is_active'                   => 1,
                'include_environment_changes' => 1,
            ]);

            if (empty($webhooks)) {
                return;
            }

            foreach ($webhooks as $webhook) {
                // Check if webhook wants this event type
                $event_types = json_decode(
                    $webhook->event_types_json,
                    true
                );
                if (!in_array($event_type, $event_types)) {
                    continue;
                }

                // Build specialized payload for environment changes
                $payload = $this->buildEnvironmentChangePayload(
                    $webhook,
                    $event_type,
                    $flag,
                    $environment,
                    $env_value
                );

                // Send with retry
                $result = $this->sendRequest($webhook, $payload);

                if (!$result['success']) {
                    error_log(sprintf(
                        "Webhook '%s' failed for env change on flag '%s': %s",
                        $webhook->name,
                        $flag->name,
                        $result['error']
                    ));
                }
            }
        } catch (\Throwable $e) {
            error_log(sprintf(
                "Webhook environment dispatch failed: %s",
                $e->getMessage()
            ));
        }
    }

    /**
     * Builds payload from Twig template.
     *
     * Fetches all environment values for the flag and includes them
     * in the payload. For updates, compares old and new environment
     * values to detect changes.
     *
     * Available variables:
     * - event_type: 'created', 'updated', 'deleted'
     * - flag: {name, type, description, ...}
     * - environments: [{name, value, start_datetime, end_datetime}, ...]
     * - old_value: Previous description (updates only, else null)
     * - old_environments: [{name, value, ...}, ...] (updates only, else [])
     * - timestamp: ISO 8601 current datetime
     *
     * @param PhlagWebhook $webhook
     * @param string $event_type
     * @param Phlag $flag
     * @param Phlag|null $old_flag
     * @return string Rendered JSON payload
     */
    protected function buildPayload(
        PhlagWebhook $webhook,
        string $event_type,
        Phlag $flag,
        ?Phlag $old_flag
    ): string {

        // Fetch current environment values
        $environments = $this->fetchEnvironments($flag->phlag_id);

        // Fetch old environment values for updates
        $old_environments = [];
        if ($old_flag !== null) {
            $old_environments = $this->fetchEnvironments($old_flag->phlag_id);
        }

        // Build template context
        $context = [
            'event_type'       => $event_type,
            'flag'             => $flag,
            'environments'     => $environments,
            'old_value'        => $old_flag?->description,
            'old_environments' => $old_environments,
            'timestamp'        => date('c'),
        ];

        // Render template or use default
        $template = $webhook->payload_template ?? $this->getDefaultTemplate();

        return $this->renderTemplate($template, $context);
    }

    /**
     * Builds payload for environment value changes.
     *
     * Similar to buildPayload but includes environment-specific context.
     *
     * @param PhlagWebhook $webhook
     * @param string $event_type
     * @param Phlag $flag
     * @param PhlagEnvironment $environment
     * @param PhlagEnvironmentValue $env_value
     * @return string Rendered JSON payload
     */
    protected function buildEnvironmentChangePayload(
        PhlagWebhook $webhook,
        string $event_type,
        Phlag $flag,
        PhlagEnvironment $environment,
        PhlagEnvironmentValue $env_value
    ): string {

        // Fetch ALL current environment values
        $environments = $this->fetchEnvironments($flag->phlag_id);

        // Build context
        $context = [
            'event_type'          => $event_type,
            'flag'                => $flag,
            'environments'        => $environments,
            'changed_environment' => [
                'name'           => $environment->name,
                'value'          => $env_value->value,
                'start_datetime' => $env_value->start_datetime,
                'end_datetime'   => $env_value->end_datetime,
            ],
            'timestamp'           => date('c'),
        ];

        $template = $webhook->payload_template ?? $this->getDefaultTemplate();

        return $this->renderTemplate($template, $context);
    }

    /**
     * Fetches all environment values for a flag.
     *
     * Returns array of environment data sorted alphabetically by name.
     *
     * @param int $phlag_id Flag ID
     * @return array Array of [{name, value, start_datetime, end_datetime}]
     */
    protected function fetchEnvironments(int $phlag_id): array {

        $environments = [];

        $env_values = $this->repository->find('PhlagEnvironmentValue', [
            'phlag_id' => $phlag_id,
        ]);

        foreach ($env_values as $env_value) {
            $env = $this->repository->get(
                'PhlagEnvironment',
                $env_value->phlag_environment_id
            );

            if ($env !== null) {
                $environments[] = [
                    'name'           => $env->name,
                    'value'          => $env_value->value,
                    'start_datetime' => $env_value->start_datetime,
                    'end_datetime'   => $env_value->end_datetime,
                ];
            }
        }

        // Sort alphabetically by name
        usort($environments, function ($a, $b) {
            return strcmp($a['name'], $b['name']);
        });

        return $environments;
    }

    /**
     * Renders a Twig template with context.
     *
     * Handles both inline templates and default template.
     *
     * @param string $template Twig template string
     * @param array $context Template variables
     * @return string Rendered output
     */
    protected function renderTemplate(string $template, array $context): string {

        $return = '';

        try {
            $twig_template = $this->twig->createTemplate($template);
            $return = $twig_template->render($context);
        } catch (\Throwable $e) {
            error_log(sprintf(
                "Webhook template rendering failed: %s",
                $e->getMessage()
            ));
            // Fallback to default template
            try {
                $default = $this->getDefaultTemplate();
                $twig_template = $this->twig->createTemplate($default);
                $return = $twig_template->render($context);
            } catch (\Throwable $fallbackException) {
                error_log(sprintf(
                    "Webhook default template rendering failed: %s",
                    $fallbackException->getMessage()
                ));
                // As a last resort, return a minimal JSON error payload
                $errorPayload = json_encode([
                    'error'   => 'Webhook rendering failed',
                    'message' => 'Both custom and default templates failed to render.',
                ]);
                // Guard against json_encode failure
                $return = $errorPayload !== false
                    ? $errorPayload
                    : '{"error":"Webhook rendering failed"}';
            }
        }

        return $return;
    }

    /**
     * Sends HTTP POST request with retry logic.
     *
     * Attempts delivery up to (max_retries + 1) times with configured
     * timeout. Returns success status, HTTP status code, and error message.
     *
     * @param PhlagWebhook $webhook
     * @param string $payload JSON string
     * @return array {success: bool, status: int, error: ?string}
     */
    protected function sendRequest(
        PhlagWebhook $webhook,
        string $payload
    ): array {

        $result = [
            'success' => false,
            'status'  => 0,
            'error'   => null,
        ];

        $attempts = 0;
        $max_attempts = $this->max_retries + 1;

        while ($attempts < $max_attempts && !$result['success']) {
            $attempts++;

            try {
                $ch = curl_init($webhook->url);

                if ($ch === false) {
                    throw new \RuntimeException('curl_init failed');
                }

                // Build headers
                $headers = ['Content-Type: application/json'];
                if ($webhook->headers_json !== null) {
                    $custom_headers = json_decode(
                        $webhook->headers_json,
                        true
                    );
                    if (is_array($custom_headers)) {
                        foreach ($custom_headers as $name => $value) {
                            $headers[] = "$name: $value";
                        }
                    }
                }

                curl_setopt_array($ch, [
                    CURLOPT_POST           => true,
                    CURLOPT_POSTFIELDS     => $payload,
                    CURLOPT_HTTPHEADER     => $headers,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => $this->timeout,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_MAXREDIRS      => 3,
                ]);

                $response = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error = curl_error($ch);

                curl_close($ch);

                if ($response === false) {
                    throw new \RuntimeException(
                        "cURL error: $error"
                    );
                }

                $result['status'] = $http_code;

                // Consider 2xx status codes as success
                if ($http_code >= 200 && $http_code < 300) {
                    $result['success'] = true;
                } else {
                    $result['error'] = sprintf(
                        "HTTP %d: %s",
                        $http_code,
                        substr($response, 0, 200)
                    );
                }
            } catch (\Throwable $e) {
                $result['error'] = $e->getMessage();
            }

            // If failed and have retries left, wait briefly
            if (!$result['success'] && $attempts < $max_attempts) {
                usleep(100000); // 100ms delay
            }
        }

        return $result;
    }

    /**
     * Returns the default Twig template.
     *
     * Used when webhook has no custom payload_template configured.
     *
     * @return string Twig template string
     */
    protected function getDefaultTemplate(): string {

        return '{
  "event": {{ event_type|json_encode|raw }},
  "flag": {
    "name": {{ flag.name|json_encode|raw }},
    "type": {{ flag.type|json_encode|raw }},
    "description": {{ flag.description|json_encode|raw }},
    "environments": [
      {% for env in environments %}
      {
        "name": {{ env.name|json_encode|raw }},
        "value": {{ env.value|json_encode|raw }},
        "start_datetime": {{ env.start_datetime|json_encode|raw }},
        "end_datetime": {{ env.end_datetime|json_encode|raw }}
      }{% if not loop.last %},{% endif %}
      {% endfor %}
    ]
  }{% if old_value is not null %},
  "previous": {
    "description": {{ old_value|json_encode|raw }},
    "environments": [
      {% for env in old_environments %}
      {
        "name": {{ env.name|json_encode|raw }},
        "value": {{ env.value|json_encode|raw }},
        "start_datetime": {{ env.start_datetime|json_encode|raw }},
        "end_datetime": {{ env.end_datetime|json_encode|raw }}
      }{% if not loop.last %},{% endif %}
      {% endfor %}
    ]
  }{% endif %},
  "timestamp": {{ timestamp|json_encode|raw }}
}';
    }
}
