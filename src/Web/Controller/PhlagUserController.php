<?php

namespace Moonspot\Phlag\Web\Controller;

/**
 * Phlag User Controller
 *
 * Handles web interface pages for managing user accounts in the Phlag
 * system. Provides views for listing, creating, editing, and viewing
 * users. All data operations are performed via JavaScript AJAX calls
 * to the REST API endpoints.
 *
 * ## Responsibilities
 *
 * - Render user list page with table structure
 * - Render user creation form
 * - Render user edit form (pre-populated via JS)
 * - Render user detail view
 * - Handle 404s for non-existent users
 *
 * ## Security Considerations
 *
 * User passwords are never displayed in the UI. The edit form allows
 * changing the password, but the current password is never shown. An
 * empty password field on edit means "don't change the password".
 *
 * ## Usage
 *
 * Controllers are invoked by the router in public/index.php:
 *
 * ```php
 * $controller = new PhlagUserController();
 * $controller->list();
 * ```
 *
 * @package Moonspot\Phlag\Web\Controller
 */
class PhlagUserController extends BaseController {

    /**
     * Displays a paginated list of all users
     *
     * Renders a table view of all users with their usernames and full
     * names. The actual data is loaded via JavaScript making an AJAX
     * call to GET /api/PhlagUser. This keeps the controller simple and
     * leverages the existing API. Requires user to be logged in.
     *
     * @return void
     */
    public function list(): void {

        $this->requireLogin();

        $this->render('user/list.html.twig', [
            'title' => 'Manage Users',
        ]);
    }

    /**
     * Displays the create user form
     *
     * Renders an empty form for creating a new user account. Form
     * submission is handled via JavaScript POST to /api/PhlagUser.
     * Requires user to be logged in.
     *
     * @return void
     */
    public function create(): void {

        $this->requireLogin();

        $this->render('user/form.html.twig', [
            'title' => 'Create User',
            'mode'  => 'create',
            'user'  => null,
        ]);
    }

    /**
     * Displays the edit user form
     *
     * Renders a form for editing an existing user. The user data is
     * loaded via JavaScript GET to /api/PhlagUser/{id} and the form
     * is populated client-side. Form submission is handled via
     * JavaScript PUT to /api/PhlagUser/{id}. Password field is optional
     * on edit - empty means don't change password. Requires user to be
     * logged in.
     *
     * @param int $id User ID to edit
     *
     * @return void
     */
    public function edit(int $id): void {

        $this->requireLogin();

        $this->render('user/form.html.twig', [
            'title'   => 'Edit User',
            'mode'    => 'edit',
            'user_id' => $id,
        ]);
    }

    /**
     * Displays a single user's details
     *
     * Renders a read-only view of a user's properties. Passwords are
     * never displayed for security. The user data is loaded via
     * JavaScript GET to /api/PhlagUser/{id}. Requires user to be
     * logged in.
     *
     * @param int $id User ID to view
     *
     * @return void
     */
    public function view(int $id): void {

        $this->requireLogin();

        $this->render('user/view.html.twig', [
            'title'   => 'View User',
            'user_id' => $id,
        ]);
    }
}
