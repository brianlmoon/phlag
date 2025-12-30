<?php

namespace Moonspot\Phlag\Web\Security;

use Moonspot\Phlag\Data\PhlagSession;
use Moonspot\Phlag\Data\Repository;

/**
 * Database Session Handler
 *
 * Implements PHP's SessionHandlerInterface to store session data in the
 * database instead of files. This enables session sharing across multiple
 * application instances for horizontal scaling and load balancing.
 *
 * ## Benefits
 *
 * - **Multi-instance support**: Share sessions across web servers
 * - **Horizontal scaling**: No session affinity required at load balancer
 * - **Persistence**: Sessions survive server restarts
 * - **Centralized management**: Query and manage sessions via database
 *
 * ## How It Works
 *
 * PHP's session management calls methods on this handler at specific
 * lifecycle points:
 *
 * 1. **open()**: Called when session_start() is invoked
 * 2. **read()**: Retrieves session data for the current session ID
 * 3. **write()**: Saves session data and updates last_activity timestamp
 * 4. **destroy()**: Deletes session from database
 * 5. **gc()**: Garbage collection removes expired sessions
 * 6. **close()**: Called at end of request
 *
 * ## Usage
 *
 * ```php
 * // Register handler before session_start()
 * $handler = new DatabaseSessionHandler();
 * session_set_save_handler($handler, true);
 * session_start();
 * ```
 *
 * Heads-up: This handler is automatically registered by SessionManager
 * when database session storage is enabled. Direct instantiation is
 * only needed for testing or custom session configurations.
 *
 * @package Moonspot\Phlag\Web\Security
 */
class DatabaseSessionHandler implements \SessionHandlerInterface {

    /**
     * Repository instance for database operations
     *
     * @var Repository
     */
    protected Repository $repository;

    /**
     * Creates the database session handler
     *
     * Initializes the repository for database operations. Accepts an
     * optional repository parameter for testing with mocked dependencies.
     *
     * @param ?Repository $repository Optional repository instance (for testing)
     */
    public function __construct(?Repository $repository = null) {

        $this->repository = $repository ?? Repository::init();
    }

    /**
     * Opens a session
     *
     * Called by PHP when session_start() is invoked. For database-backed
     * sessions, this is a no-op as we don't need to open a file handle.
     *
     * ## Parameters
     *
     * - **$path**: Session save path (unused for database storage)
     * - **$name**: Session name (unused for database storage)
     *
     * @param string $path Session save path
     * @param string $name Session name
     *
     * @return bool Always returns true
     */
    public function open(string $path, string $name): bool {

        // No-op for database storage
        return true;
    }

    /**
     * Closes a session
     *
     * Called by PHP at the end of a request. For database-backed sessions,
     * this is a no-op as we don't maintain persistent connections.
     *
     * @return bool Always returns true
     */
    public function close(): bool {

        // No-op for database storage
        return true;
    }

    /**
     * Reads session data from the database
     *
     * Retrieves the serialized session data for the given session ID.
     * Returns an empty string if the session doesn't exist.
     *
     * ## How It Works
     *
     * - Queries database for session with matching session_id
     * - Returns session_data field (serialized by PHP)
     * - Returns empty string if not found (PHP creates new session)
     *
     * ## Error Handling
     *
     * On database errors, returns empty string to allow PHP to create
     * a new session rather than failing the request.
     *
     * @param string $id Session identifier
     *
     * @return string Serialized session data or empty string
     */
    public function read(string $id): string {

        $session_data = '';

        try {

            $session = $this->repository->get('PhlagSession', $id);

            if ($session instanceof PhlagSession && !empty($session->session_data)) {
                $session_data = $session->session_data;
            }

        } catch (\Throwable $e) {

            // Log error but don't fail - return empty string to create new session
            error_log(sprintf(
                'Session read error [%s]: %s in %s:%d',
                $id,
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            ));
        }

        return $session_data;
    }

    /**
     * Writes session data to the database
     *
     * Saves or updates the session data and last_activity timestamp.
     * If the session doesn't exist, creates it. If it exists, updates
     * the data and activity timestamp.
     *
     * ## How It Works
     *
     * - Attempts to load existing session by ID
     * - If found, updates session_data and last_activity
     * - If not found, creates new session record
     * - Uses repository->save() which handles insert vs update
     *
     * ## Error Handling
     *
     * Returns false on database errors to signal failure to PHP.
     * This prevents data loss by ensuring PHP knows the write failed.
     *
     * @param string $id Session identifier
     * @param string $data Serialized session data
     *
     * @return bool True on success, false on failure
     */
    public function write(string $id, string $data): bool {

        $success = false;

        try {

            // Try to load existing session
            $session = $this->repository->get('PhlagSession', $id);

            // If not found, create new session object
            if (!($session instanceof PhlagSession)) {
                $session = $this->repository->new('PhlagSession');
                $session->session_id = $id;
            }

            // Update session data and activity timestamp
            $session->session_data = $data;
            $session->last_activity = time();

            // Save to database
            $saved = $this->repository->save('PhlagSession', $session);

            $success = $saved instanceof PhlagSession;

        } catch (\Throwable $e) {

            // Log error and return false to signal failure
            error_log(sprintf(
                'Session write error [%s]: %s in %s:%d',
                $id,
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            ));
        }

        return $success;
    }

    /**
     * Destroys a session
     *
     * Deletes the session record from the database. Called when
     * session_destroy() is invoked or when a session times out.
     *
     * ## How It Works
     *
     * - Loads session by ID
     * - Calls repository->delete() to remove from database
     * - Returns true even if session doesn't exist (idempotent)
     *
     * ## Error Handling
     *
     * Returns false only on actual database errors, not on
     * "session not found" (which is a successful destroy).
     *
     * @param string $id Session identifier
     *
     * @return bool True on success, false on failure
     */
    public function destroy(string $id): bool {

        $success = false;

        try {

            $session = $this->repository->get('PhlagSession', $id);

            if ($session instanceof PhlagSession) {
                $success = $this->repository->delete('PhlagSession', $id);
            } else {
                // Session doesn't exist - that's a successful destroy
                $success = true;
            }

        } catch (\Throwable $e) {

            // Log error and return false
            error_log(sprintf(
                'Session destroy error [%s]: %s in %s:%d',
                $id,
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            ));
        }

        return $success;
    }

    /**
     * Garbage collection
     *
     * Deletes expired sessions from the database. Called probabilistically
     * by PHP based on session.gc_probability and session.gc_divisor.
     *
     * ## How It Works
     *
     * - Calculates cutoff timestamp (now - max_lifetime)
     * - Queries for sessions with last_activity < cutoff
     * - Deletes all matching sessions
     * - Returns count of deleted sessions
     *
     * ## Performance
     *
     * Uses indexed last_activity column for efficient queries.
     * Garbage collection only runs on a small percentage of requests
     * (default: 1% chance per request).
     *
     * ## Usage
     *
     * ```php
     * // Configure in php.ini or at runtime
     * ini_set('session.gc_probability', 1);
     * ini_set('session.gc_divisor', 100);
     * ini_set('session.gc_maxlifetime', 1800); // 30 minutes
     * ```
     *
     * @param int $max_lifetime Maximum session lifetime in seconds
     *
     * @return int|false Number of sessions deleted or false on failure
     */
    public function gc(int $max_lifetime): int|false {

        $deleted_count = 0;

        try {

            // Calculate cutoff timestamp
            $cutoff = time() - $max_lifetime;

            // Find all expired sessions
            $expired_sessions = $this->repository->find('PhlagSession', [
                'last_activity <' => $cutoff,
            ]);

            // Delete each expired session
            foreach ($expired_sessions as $session) {
                if ($this->repository->delete('PhlagSession', $session->session_id)) {
                    $deleted_count++;
                }
            }

        } catch (\Throwable $e) {

            // Log error and return false to signal failure
            error_log(sprintf(
                'Session garbage collection error: %s in %s:%d',
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            ));

            return false;
        }

        return $deleted_count;
    }
}
