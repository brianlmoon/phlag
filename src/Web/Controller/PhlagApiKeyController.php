<?php

namespace Moonspot\Phlag\Web\Controller;

/**
 * Phlag API Key Controller
 *
 * Handles web interface pages for managing API keys used to authenticate
 * requests to the Phlag API. Provides views for listing, creating, editing,
 * and viewing API keys. All data operations are performed via JavaScript
 * AJAX calls to the REST API endpoints.
 *
 * ## Responsibilities
 *
 * - Render API key list page with table structure
 * - Render API key creation form
 * - Render API key edit form (pre-populated via JS)
 * - Render API key detail view with masked key display
 * - Handle 404s for non-existent API keys
 *
 * ## Security Considerations
 *
 * API keys are sensitive credentials. The view page displays the full key
 * only once when created. After that, keys are masked in the list and view
 * pages for security. Users should copy the key immediately after creation.
 *
 * ## Usage
 *
 * Controllers are invoked by the router in public/index.php:
 *
 * ```php
 * $controller = new PhlagApiKeyController();
 * $controller->list();
 * ```
 *
 * @package Moonspot\Phlag\Web\Controller
 */
class PhlagApiKeyController extends BaseController {

    /**
     * Displays a paginated list of all API keys
     *
     * Renders a table view of all API keys with masked key values for
     * security. The actual data is loaded via JavaScript making an AJAX
     * call to GET /api/PhlagApiKey. This keeps the controller simple and
     * leverages the existing API. Requires user to be logged in.
     *
     * @return void
     */
    public function list(): void {

        $this->requireLogin();

        $this->render('api_key/list.html.twig', [
            'title' => 'Manage API Keys',
        ]);
    }

    /**
     * Displays the create API key form
     *
     * Renders an empty form for creating a new API key. Form submission
     * is handled via JavaScript POST to /api/PhlagApiKey. After successful
     * creation, the full API key is displayed once for the user to copy.
     * Requires user to be logged in.
     *
     * @return void
     */
    public function create(): void {

        $this->requireLogin();

        $this->render('api_key/form.html.twig', [
            'title'   => 'Create API Key',
            'mode'    => 'create',
            'api_key' => null,
        ]);
    }

    /**
     * Displays the edit API key form
     *
     * Renders a form for editing an existing API key's description.
     * The API key value itself cannot be changed for security reasons.
     * The key data is loaded via JavaScript GET to /api/PhlagApiKey/{id}
     * and the form is populated client-side. Form submission is handled
     * via JavaScript PUT to /api/PhlagApiKey/{id}. Requires user to be
     * logged in.
     *
     * @param int $id API Key ID to edit
     *
     * @return void
     */
    public function edit(int $id): void {

        $this->requireLogin();

        $this->render('api_key/form.html.twig', [
            'title'       => 'Edit API Key',
            'mode'        => 'edit',
            'api_key_id'  => $id,
        ]);
    }

    /**
     * Displays a single API key's details
     *
     * Renders a read-only view of an API key's properties. The API key
     * value is masked for security (showing only first/last few characters).
     * The key data is loaded via JavaScript GET to /api/PhlagApiKey/{id}.
     * Requires user to be logged in.
     *
     * @param int $id API Key ID to view
     *
     * @return void
     */
    public function view(int $id): void {

        $this->requireLogin();

        $this->render('api_key/view.html.twig', [
            'title'      => 'View API Key',
            'api_key_id' => $id,
        ]);
    }
}
