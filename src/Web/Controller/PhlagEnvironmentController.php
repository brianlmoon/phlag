<?php

namespace Moonspot\Phlag\Web\Controller;

/**
 * Phlag Environment Controller
 *
 * Handles web interface pages for managing deployment environments.
 * Environments are used to organize feature flags across different
 * deployment contexts such as development, staging, and production.
 * All data operations are performed via JavaScript AJAX calls to the
 * REST API endpoints.
 *
 * ## Responsibilities
 *
 * - Render environment list page with table structure
 * - Render environment creation form
 * - Render environment edit form (pre-populated via JS)
 * - Render environment detail view
 * - Handle 404s for non-existent environments
 *
 * ## Usage
 *
 * Controllers are invoked by the router in public/index.php:
 *
 * ```php
 * $controller = new PhlagEnvironmentController();
 * $controller->list();
 * ```
 *
 * @package Moonspot\Phlag\Web\Controller
 */
class PhlagEnvironmentController extends BaseController {

    /**
     * Displays a paginated list of all environments
     *
     * Renders a table view of all environments. The actual data is loaded
     * via JavaScript making an AJAX call to GET /api/PhlagEnvironment.
     * This keeps the controller simple and leverages the existing API.
     * Requires user to be logged in.
     *
     * @return void
     */
    public function list(): void {

        $this->requireLogin();

        $this->render('environment/list.html.twig', [
            'title' => 'Manage Environments',
        ]);
    }

    /**
     * Displays the create environment form
     *
     * Renders an empty form for creating a new environment. Form submission
     * is handled via JavaScript POST to /api/PhlagEnvironment.
     * Requires user to be logged in.
     *
     * @return void
     */
    public function create(): void {

        $this->requireLogin();

        $this->render('environment/form.html.twig', [
            'title'       => 'Create Environment',
            'mode'        => 'create',
            'environment' => null,
        ]);
    }

    /**
     * Displays the edit environment form
     *
     * Renders a form for editing an existing environment's properties.
     * The environment data is loaded via JavaScript GET to
     * /api/PhlagEnvironment/{id} and the form is populated client-side.
     * Form submission is handled via JavaScript PUT to
     * /api/PhlagEnvironment/{id}. Requires user to be logged in.
     *
     * @param int $id Environment ID to edit
     *
     * @return void
     */
    public function edit(int $id): void {

        $this->requireLogin();

        $this->render('environment/form.html.twig', [
            'title'          => 'Edit Environment',
            'mode'           => 'edit',
            'environment_id' => $id,
        ]);
    }

    /**
     * Displays a single environment's details
     *
     * Renders a read-only view of an environment's properties.
     * The environment data is loaded via JavaScript GET to
     * /api/PhlagEnvironment/{id}. Requires user to be logged in.
     *
     * @param int $id Environment ID to view
     *
     * @return void
     */
    public function view(int $id): void {

        $this->requireLogin();

        $this->render('environment/view.html.twig', [
            'title'          => 'View Environment',
            'environment_id' => $id,
        ]);
    }
}
