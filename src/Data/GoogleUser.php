<?php

namespace Moonspot\Phlag\Data;

/**
 * Value object representing a Google OAuth user profile.
 *
 * This object holds user profile data retrieved from Google OAuth during
 * the authentication flow. It is used to create or link PhlagUser accounts.
 *
 * Usage:
 * ```php
 * $google_user = new GoogleUser();
 * $google_user->google_id = '123456789012345678901';
 * $google_user->email = 'user@example.com';
 * $google_user->name = 'John Doe';
 * ```
 *
 * Edge Cases:
 * - google_id is unique per Google account and never changes
 * - email may change if user updates their Google account email
 * - name may be empty if user hasn't set a name in Google profile
 *
 * @package Moonspot\Phlag\Data
 */
class GoogleUser {

    /**
     * Google's unique user identifier
     *
     * This is a numeric string that uniquely identifies the user in Google's
     * system. It never changes for a given Google account.
     *
     * @var string
     */
    public string $google_id = '';

    /**
     * User's email address from Google profile
     *
     * This is the primary email associated with the Google account.
     * Used for linking to existing PhlagUser accounts by email.
     *
     * @var string
     */
    public string $email = '';

    /**
     * User's full name from Google profile
     *
     * This is the display name from the user's Google account. May be
     * used to populate the full_name field when creating new users.
     *
     * @var string
     */
    public string $name = '';
}
