<?php

namespace Moonspot\Phlag\Action\PhlagWebhook;

use DealNews\DataMapperAPI\Action\Base;
use Moonspot\Phlag\Data\Repository;
use Moonspot\Phlag\Web\Service\WebhookDispatcher;

/**
 * Tests a webhook configuration by sending a test payload
 *
 * This action allows users to send a test webhook delivery without
 * triggering a real flag change event. It's useful for verifying
 * webhook configurations before activating them.
 *
 * ## HTTP Method
 *
 * POST only - This is an action endpoint, not a CRUD operation.
 *
 * ## Authentication
 *
 * Requires session authentication (logged-in user). Returns 401 if
 * not authenticated.
 *
 * ## Request Body
 *
 * Expects JSON with two fields:
 *
 * ```json
 * {
 *   "phlag_webhook_id": 123,
 *   "phlag_id": 456
 * }
 * ```
 *
 * The selected flag's real data and environments will be used in the
 * test payload to validate the webhook's Twig template.
 *
 * ## Response
 *
 * Returns the HTTP response from the webhook endpoint:
 *
 * ```json
 * {
 *   "success": true,
 *   "status_code": 200,
 *   "response_body": "OK",
 *   "message": "Test webhook delivered successfully"
 * }
 * ```
 *
 * On failure:
 *
 * ```json
 * {
 *   "success": false,
 *   "status_code": 500,
 *   "error": "Connection timeout",
 *   "message": "Failed to deliver test webhook"
 * }
 * ```
 *
 * ## Usage
 *
 * ```javascript
 * // From the admin UI
 * fetch('/api/PhlagWebhook/test/', {
 *   method: 'POST',
 *   headers: {'Content-Type': 'application/json'},
 *   body: JSON.stringify({phlag_webhook_id: 123})
 * });
 * ```
 *
 * ## Edge Cases
 *
 * - Returns 404 if webhook ID doesn't exist
 * - Returns 400 if webhook is inactive (is_active = 0)
 * - Returns full error details if webhook delivery fails
 * - Test payload event type is "webhook_test"
 *
 * @package Moonspot\Phlag\Action\PhlagWebhook
 */
class Test extends Base {

    /**
     * Sends a test webhook delivery
     *
     * This method fetches the webhook configuration, validates it's active,
     * and dispatches a test event. The test uses a minimal payload with
     * event type "webhook_test" and a sample flag.
     *
     * @return void Outputs JSON response
     */
    public function run(): void {

        // Check authentication
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            return;
        }

        // Only allow POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method Not Allowed']);
            return;
        }

        // Parse request body
        $input = json_decode(file_get_contents('php://input'), true);
        if (empty($input['phlag_webhook_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing phlag_webhook_id']);
            return;
        }

        if (empty($input['phlag_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing phlag_id - flag selection required']);
            return;
        }

        $webhook_id = (int)$input['phlag_webhook_id'];
        $phlag_id = (int)$input['phlag_id'];

        // Fetch webhook
        $repository = Repository::init();
        $webhook = $repository->get('PhlagWebhook', $webhook_id);

        if (empty($webhook)) {
            http_response_code(404);
            echo json_encode(['error' => 'Webhook not found']);
            return;
        }

        // Fetch flag
        $flag = $repository->get('Phlag', $phlag_id);

        if (empty($flag)) {
            http_response_code(404);
            echo json_encode(['error' => 'Flag not found']);
            return;
        }

        // Check if webhook is active
        if (!$webhook->is_active) {
            http_response_code(400);
            echo json_encode([
                'error' => 'Webhook is inactive',
                'message' => 'Activate the webhook before testing',
            ]);
            return;
        }

        // Dispatch test webhook with real flag
        $dispatcher = new WebhookDispatcher();
        $result = $dispatcher->dispatchTest($webhook, $flag);

        // Return result
        if ($result['success']) {
            echo json_encode([
                'success'       => true,
                'status_code'   => $result['status_code'],
                'response_body' => $result['response_body'],
                'message'       => 'Test webhook delivered successfully',
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'success'     => false,
                'status_code' => $result['status_code'] ?? 0,
                'error'       => $result['error'] ?? 'Unknown error',
                'message'     => 'Failed to deliver test webhook',
            ]);
        }
    }

    public function loadData(): array {
        return [];
        // TODO: Implement loadData() method.
    }
}
