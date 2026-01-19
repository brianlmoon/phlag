<?php

namespace Moonspot\Phlag\Web\Controller;

/**
 * PhlagWebhook Controller
 *
 * Handles web interface pages for managing webhook configurations.
 * Provides views for listing, creating, editing, and testing webhooks.
 * All data operations are performed via JavaScript AJAX calls to the
 * REST API endpoints.
 *
 * ## Responsibilities
 *
 * - Render webhook list page with table structure
 * - Render webhook creation form
 * - Render webhook edit form (pre-populated via JS)
 * - Handle webhook test modal interactions
 * - Handle 404s for non-existent webhooks
 *
 * ## Usage
 *
 * Controllers are invoked by the router in public/index.php:
 *
 * ```php
 * $controller = new PhlagWebhookController();
 * $controller->list();
 * ```
 *
 * @package Moonspot\Phlag\Web\Controller
 */
class PhlagWebhookController extends BaseController {

    /**
     * Displays a paginated list of all webhooks
     *
     * Renders a table view of all webhooks. The actual data is loaded
     * via JavaScript making an AJAX call to GET /api/PhlagWebhook. This
     * keeps the controller simple and leverages the existing API. Requires
     * user to be logged in.
     *
     * @return void
     */
    public function list(): void {

        $this->requireLogin();

        $this->render('webhooks/list.html.twig', [
            'title' => 'Manage Webhooks',
        ]);
    }

    /**
     * Displays the create webhook form
     *
     * Renders an empty form for creating a new webhook. Form submission
     * is handled via JavaScript POST to /api/PhlagWebhook. Requires user
     * to be logged in.
     *
     * @return void
     */
    public function create(): void {

        $this->requireLogin();

        $this->render('webhooks/form.html.twig', [
            'title'   => 'Create Webhook',
            'mode'    => 'create',
            'webhook' => null,
        ]);
    }

    /**
     * Displays the edit webhook form
     *
     * Renders a form for editing an existing webhook. The webhook data
     * is loaded via JavaScript GET to /api/PhlagWebhook/{id} and the form
     * is populated client-side. Form submission is handled via JavaScript
     * PUT to /api/PhlagWebhook/{id}. Requires user to be logged in.
     *
     * @param int $id Webhook ID to edit
     * @return void
     */
    public function edit(int $id): void {

        $this->requireLogin();

        $this->render('webhooks/form.html.twig', [
            'title'      => 'Edit Webhook',
            'mode'       => 'edit',
            'webhook_id' => $id,
        ]);
    }
}
