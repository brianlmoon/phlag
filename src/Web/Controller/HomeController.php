<?php

namespace Moonspot\Phlag\Web\Controller;

/**
 * Home Controller
 *
 * Handles the dashboard/home page of the Phlag admin interface.
 * Displays overview information and quick links to manage phlags,
 * API keys, environments, and users.
 *
 * @package Moonspot\Phlag\Web\Controller
 */
class HomeController extends BaseController {

    /**
     * Displays the dashboard home page
     *
     * Renders the main dashboard with links to manage phlags, API keys,
     * environments, and users, plus API endpoint documentation. Requires
     * user to be logged in.
     *
     * @return void
     */
    public function index(): void {

        $this->requireLogin();

        $this->render('home.html.twig', [
            'title' => 'Dashboard',
        ]);
    }
}
