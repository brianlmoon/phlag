<?php

namespace Moonspot\Phlag\Tests\Unit\Web\Security;

use Moonspot\Phlag\Web\Security\SessionManager;
use PHPUnit\Framework\TestCase;

/**
 * SessionManager Test
 *
 * Tests session timeout functionality including activity tracking,
 * timeout detection, and session lifecycle management.
 */
class SessionManagerTest extends TestCase {

    /**
     * Sets up test environment before each test
     *
     * Ensures session is clean and environment variables are reset.
     *
     * @return void
     */
    protected function setUp(): void {

        parent::setUp();

        // Destroy any existing session
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        // Clear session data
        $_SESSION = [];

        // Clear environment variables
        putenv('SESSION_TIMEOUT');
    }

    /**
     * Tears down test environment after each test
     *
     * Ensures session is destroyed to prevent test interference.
     *
     * @return void
     */
    protected function tearDown(): void {

        // Destroy session
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        // Clear session data
        $_SESSION = [];

        parent::tearDown();
    }

    /**
     * Tests that start() initializes a new session
     *
     * @return void
     */
    public function testStartInitializesSession(): void {

        SessionManager::start();

        $this->assertEquals(PHP_SESSION_ACTIVE, session_status());
    }

    /**
     * Tests that start() sets initial timestamps for new sessions
     *
     * @return void
     */
    public function testStartSetsInitialTimestamps(): void {

        SessionManager::start();

        $this->assertIsInt(SessionManager::getCreatedTime());
        $this->assertIsInt(SessionManager::getLastActivity());
    }

    /**
     * Tests that start() doesn't reset timestamps for existing sessions
     *
     * @return void
     */
    public function testStartDoesNotResetExistingSession(): void {

        SessionManager::start();

        $original_created  = SessionManager::getCreatedTime();
        $original_activity = SessionManager::getLastActivity();

        // Wait a moment to ensure time would be different
        sleep(1);

        // Start again
        SessionManager::start();

        $this->assertEquals($original_created, SessionManager::getCreatedTime());
        $this->assertEquals($original_activity, SessionManager::getLastActivity());
    }

    /**
     * Tests that isActive() returns false when no user is logged in
     *
     * @return void
     */
    public function testIsActiveReturnsFalseWithoutUser(): void {

        SessionManager::start();

        $this->assertFalse(SessionManager::isActive());
    }

    /**
     * Tests that isActive() returns true for logged in user with recent activity
     *
     * @return void
     */
    public function testIsActiveReturnsTrueForActiveSession(): void {

        SessionManager::start();
        $_SESSION['user_id'] = 1;
        SessionManager::touch();

        $this->assertTrue(SessionManager::isActive());
    }

    /**
     * Tests that isActive() returns true for new session without activity timestamp
     *
     * @return void
     */
    public function testIsActiveReturnsTrueForNewSession(): void {

        SessionManager::start();
        $_SESSION['user_id'] = 1;
        unset($_SESSION['last_activity']);

        $this->assertTrue(SessionManager::isActive());
    }

    /**
     * Tests that isActive() returns false for timed out sessions
     *
     * @return void
     */
    public function testIsActiveReturnsFalseForTimedOutSession(): void {

        // Set very short timeout for testing
        putenv('SESSION_TIMEOUT=1');

        SessionManager::start();
        $_SESSION['user_id']       = 1;
        $_SESSION['last_activity'] = time() - 2; // 2 seconds ago

        $this->assertFalse(SessionManager::isActive());
    }

    /**
     * Tests that touch() updates the activity timestamp
     *
     * @return void
     */
    public function testTouchUpdatesActivityTimestamp(): void {

        SessionManager::start();
        $_SESSION['user_id'] = 1;

        $original = SessionManager::getLastActivity();

        // Wait a moment
        sleep(1);

        SessionManager::touch();

        $updated = SessionManager::getLastActivity();

        $this->assertGreaterThan($original, $updated);
    }

    /**
     * Tests that touch() only works when user is logged in
     *
     * @return void
     */
    public function testTouchOnlyWorksWhenLoggedIn(): void {

        SessionManager::start();

        $original = SessionManager::getLastActivity();

        // Wait a moment
        sleep(1);

        // Try to touch without being logged in
        SessionManager::touch();

        $this->assertEquals($original, SessionManager::getLastActivity());
    }

    /**
     * Tests that getTimeout() returns default timeout
     *
     * @return void
     */
    public function testGetTimeoutReturnsDefault(): void {

        $timeout = SessionManager::getTimeout();

        $this->assertEquals(1800, $timeout);
    }

    /**
     * Tests that getTimeout() respects environment variable
     *
     * @return void
     */
    public function testGetTimeoutRespectsEnvironmentVariable(): void {

        putenv('SESSION_TIMEOUT=3600');

        $timeout = SessionManager::getTimeout();

        $this->assertEquals(3600, $timeout);
    }

    /**
     * Tests that destroy() clears all session data
     *
     * @return void
     */
    public function testDestroysClearsSessionData(): void {

        SessionManager::start();
        $_SESSION['user_id']  = 1;
        $_SESSION['username'] = 'testuser';

        SessionManager::destroy();

        $this->assertEmpty($_SESSION);
    }

    /**
     * Tests that destroy() destroys the session
     *
     * @return void
     */
    public function testDestroyDestroysSession(): void {

        SessionManager::start();

        SessionManager::destroy();

        $this->assertEquals(PHP_SESSION_NONE, session_status());
    }

    /**
     * Tests that getLastActivity() returns null when not set
     *
     * @return void
     */
    public function testGetLastActivityReturnsNullWhenNotSet(): void {

        $this->assertNull(SessionManager::getLastActivity());
    }

    /**
     * Tests that getCreatedTime() returns null when not set
     *
     * @return void
     */
    public function testGetCreatedTimeReturnsNullWhenNotSet(): void {

        $this->assertNull(SessionManager::getCreatedTime());
    }

    /**
     * Tests timeout boundary condition at exactly timeout duration
     *
     * @return void
     */
    public function testTimeoutBoundaryCondition(): void {

        putenv('SESSION_TIMEOUT=2');

        SessionManager::start();
        $_SESSION['user_id']       = 1;
        $_SESSION['last_activity'] = time() - 2; // Exactly at timeout

        // At exactly timeout duration, should be expired
        $this->assertFalse(SessionManager::isActive());
    }

    /**
     * Tests that session just before timeout is still active
     *
     * @return void
     */
    public function testSessionJustBeforeTimeoutIsActive(): void {

        putenv('SESSION_TIMEOUT=10');

        SessionManager::start();
        $_SESSION['user_id']       = 1;
        $_SESSION['last_activity'] = time() - 9; // Just before timeout

        $this->assertTrue(SessionManager::isActive());
    }

    /**
     * Tests that activity tracking extends session lifetime
     *
     * @return void
     */
    public function testActivityTrackingExtendsSessionLifetime(): void {

        putenv('SESSION_TIMEOUT=3');

        SessionManager::start();
        $_SESSION['user_id'] = 1;
        SessionManager::touch();

        // Wait 2 seconds
        sleep(2);

        // Touch again to extend
        SessionManager::touch();

        // Session should still be active
        $this->assertTrue(SessionManager::isActive());
    }

    /**
     * Tests that session timeout properly destroys the session
     *
     * @return void
     */
    public function testTimeoutDestroysSession(): void {

        putenv('SESSION_TIMEOUT=1');

        SessionManager::start();
        $_SESSION['user_id']       = 1;
        $_SESSION['username']      = 'testuser';
        $_SESSION['last_activity'] = time() - 2;

        // Check if active (should be false and destroy session)
        SessionManager::isActive();

        // Session data should be cleared
        $this->assertEmpty($_SESSION);
    }

    /**
     * Tests timeout with zero value
     *
     * @return void
     */
    public function testTimeoutWithZeroValue(): void {

        putenv('SESSION_TIMEOUT=0');

        SessionManager::start();
        $_SESSION['user_id']       = 1;
        $_SESSION['last_activity'] = time();

        // With 0 timeout, any activity should expire session immediately
        $this->assertFalse(SessionManager::isActive());
    }

    /**
     * Tests that invalid environment timeout falls back to default
     *
     * @return void
     */
    public function testInvalidEnvironmentTimeoutUsesDefault(): void {

        putenv('SESSION_TIMEOUT=invalid');

        $timeout = SessionManager::getTimeout();

        $this->assertEquals(1800, $timeout);
    }
}
