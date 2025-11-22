<?php

namespace Moonspot\Phlag\Web\Controller;

/**
 * Phlag Controller
 *
 * Handles web interface pages for managing Phlag feature flags.
 * Provides views for listing, creating, editing, and viewing phlags.
 * All data operations are performed via JavaScript AJAX calls to the
 * REST API endpoints.
 *
 * ## Responsibilities
 *
 * - Render phlag list page with table structure
 * - Render phlag creation form
 * - Render phlag edit form (pre-populated via JS)
 * - Render phlag detail view
 * - Handle 404s for non-existent phlags
 *
 * ## Usage
 *
 * Controllers are invoked by the router in public/index.php:
 *
 * ```php
 * $controller = new PhlagController();
 * $controller->list();
 * ```
 *
 * @package Moonspot\Phlag\Web\Controller
 */
class PhlagController extends BaseController {

    /**
     * Displays a paginated list of all phlags
     *
     * Renders a table view of all phlags. The actual data is loaded
     * via JavaScript making an AJAX call to GET /api/Phlag. This keeps
     * the controller simple and leverages the existing API. Requires
     * user to be logged in.
     *
     * @return void
     */
    public function list(): void {

        $this->requireLogin();

        $this->render('phlag/list.html.twig', [
            'title' => 'Manage Flags',
        ]);
    }

    /**
     * Displays the create phlag form
     *
     * Renders an empty form for creating a new phlag. Form submission
     * is handled via JavaScript POST to /api/Phlag. Requires user to
     * be logged in.
     *
     * @return void
     */
    public function create(): void {

        $this->requireLogin();

        $this->render('phlag/form.html.twig', [
            'title'   => 'Create Flag',
            'mode'    => 'create',
            'phlag'   => null,
        ]);
    }

    /**
     * Displays the edit phlag form
     *
     * Renders a form for editing an existing phlag. The phlag data
     * is loaded via JavaScript GET to /api/Phlag/{id} and the form
     * is populated client-side. Form submission is handled via
     * JavaScript PUT to /api/Phlag/{id}. Requires user to be logged in.
     *
     * @param int $id Phlag ID to edit
     *
     * @return void
     */
    public function edit(int $id): void {

        $this->requireLogin();

        $this->render('phlag/form.html.twig', [
            'title'    => 'Edit Flag',
            'mode'     => 'edit',
            'phlag_id' => $id,
        ]);
    }

    /**
     * Displays a single phlag's details
     *
     * Renders a read-only view of a phlag's properties. The phlag
     * data is loaded via JavaScript GET to /api/Phlag/{id}. Requires
     * user to be logged in.
     *
     * @param int $id Phlag ID to view
     *
     * @return void
     */
    public function view(int $id): void {

        $this->requireLogin();

        $this->render('phlag/view.html.twig', [
            'title'    => 'View Flag',
            'phlag_id' => $id,
        ]);
    }
}
