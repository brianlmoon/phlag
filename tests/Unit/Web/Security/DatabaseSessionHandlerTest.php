<?php

namespace Moonspot\Phlag\Tests\Unit\Web\Security;

use Moonspot\Phlag\Data\PhlagSession;
use Moonspot\Phlag\Data\Repository;
use Moonspot\Phlag\Web\Security\DatabaseSessionHandler;
use PHPUnit\Framework\TestCase;

/**
 * DatabaseSessionHandler Test
 *
 * Tests database-backed session storage functionality including
 * reading, writing, destruction, and garbage collection using
 * mocked repository to avoid database dependencies.
 */
class DatabaseSessionHandlerTest extends TestCase {

    /**
     * Session handler instance
     *
     * @var DatabaseSessionHandler
     */
    protected DatabaseSessionHandler $handler;

    /**
     * Mocked repository instance
     *
     * @var Repository|\PHPUnit\Framework\MockObject\MockObject
     */
    protected Repository $repository;

    /**
     * Sets up test environment before each test
     *
     * Creates mocked dependencies to avoid database connections.
     *
     * @return void
     */
    protected function setUp(): void {

        parent::setUp();

        // Create mock repository
        $this->repository = $this->createMock(Repository::class);

        // Create handler with mocked repository
        $this->handler = new DatabaseSessionHandler($this->repository);
    }

    /**
     * Tests that open() returns true
     *
     * @return void
     */
    public function testOpenReturnsTrue(): void {

        $result = $this->handler->open('/tmp', 'PHPSESSID');

        $this->assertTrue($result);
    }

    /**
     * Tests that close() returns true
     *
     * @return void
     */
    public function testCloseReturnsTrue(): void {

        $result = $this->handler->close();

        $this->assertTrue($result);
    }

    /**
     * Tests reading non-existent session returns empty string
     *
     * @return void
     */
    public function testReadNonExistentSessionReturnsEmptyString(): void {

        // Mock repository returning false (session not found)
        $this->repository->expects($this->once())
            ->method('get')
            ->with('PhlagSession', 'non_existent_session_id')
            ->willReturn(false);

        $data = $this->handler->read('non_existent_session_id');

        $this->assertSame('', $data);
    }

    /**
     * Tests writing new session creates database record
     *
     * @return void
     */
    public function testWriteCreatesNewSession(): void {

        $session_id = 'test_session_abc123';
        $session_data = serialize(['user_id' => 123, 'username' => 'testuser']);

        // Mock repository returning false (session doesn't exist)
        $this->repository->expects($this->once())
            ->method('get')
            ->with('PhlagSession', $session_id)
            ->willReturn(false);

        // Mock creating new session object
        $new_session = new PhlagSession();
        $this->repository->expects($this->once())
            ->method('new')
            ->with('PhlagSession')
            ->willReturn($new_session);

        // Mock save returning the saved session
        $saved_session = new PhlagSession();
        $saved_session->session_id = $session_id;
        $saved_session->session_data = $session_data;
        $saved_session->last_activity = time();
        
        $this->repository->expects($this->once())
            ->method('save')
            ->with('PhlagSession', $this->isInstanceOf(PhlagSession::class))
            ->willReturn($saved_session);

        $result = $this->handler->write($session_id, $session_data);

        $this->assertTrue($result);
    }

    /**
     * Tests writing to existing session updates data
     *
     * @return void
     */
    public function testWriteUpdatesExistingSession(): void {

        $session_id = 'existing_session_abc123';
        $updated_data = serialize(['user_id' => 123, 'username' => 'updated']);

        // Mock repository returning existing session
        $existing_session = new PhlagSession();
        $existing_session->session_id = $session_id;
        $existing_session->session_data = serialize(['old' => 'data']);
        $existing_session->last_activity = time() - 100;

        $this->repository->expects($this->once())
            ->method('get')
            ->with('PhlagSession', $session_id)
            ->willReturn($existing_session);

        // Mock save returning updated session
        $this->repository->expects($this->once())
            ->method('save')
            ->with('PhlagSession', $this->isInstanceOf(PhlagSession::class))
            ->willReturn($existing_session);

        $result = $this->handler->write($session_id, $updated_data);

        $this->assertTrue($result);
    }

    /**
     * Tests reading existing session returns data
     *
     * @return void
     */
    public function testReadExistingSessionReturnsData(): void {

        $session_id = 'test_session_read';
        $session_data = serialize(['user_id' => 456, 'username' => 'readtest']);

        // Mock repository returning session with data
        $session = new PhlagSession();
        $session->session_id = $session_id;
        $session->session_data = $session_data;
        $session->last_activity = time();

        $this->repository->expects($this->once())
            ->method('get')
            ->with('PhlagSession', $session_id)
            ->willReturn($session);

        $read_data = $this->handler->read($session_id);

        $this->assertEquals($session_data, $read_data);
    }

    /**
     * Tests destroy removes session from database
     *
     * @return void
     */
    public function testDestroyRemovesSession(): void {

        $session_id = 'session_to_destroy';

        // Mock repository returning existing session
        $session = new PhlagSession();
        $session->session_id = $session_id;

        $this->repository->expects($this->once())
            ->method('get')
            ->with('PhlagSession', $session_id)
            ->willReturn($session);

        // Mock successful deletion
        $this->repository->expects($this->once())
            ->method('delete')
            ->with('PhlagSession', $session_id)
            ->willReturn(true);

        $result = $this->handler->destroy($session_id);

        $this->assertTrue($result);
    }

    /**
     * Tests destroying non-existent session returns true
     *
     * @return void
     */
    public function testDestroyNonExistentSessionReturnsTrue(): void {

        $session_id = 'non_existent_session';

        // Mock repository returning false (not found)
        $this->repository->expects($this->once())
            ->method('get')
            ->with('PhlagSession', $session_id)
            ->willReturn(false);

        $result = $this->handler->destroy($session_id);

        $this->assertTrue($result);
    }

    /**
     * Tests garbage collection removes expired sessions
     *
     * @return void
     */
    public function testGarbageCollectionRemovesExpiredSessions(): void {

        $current_time = time();
        $max_lifetime = 1800;

        // Create two expired sessions
        $expired1 = new PhlagSession();
        $expired1->session_id = 'expired_1';
        $expired1->last_activity = $current_time - 3600;

        $expired2 = new PhlagSession();
        $expired2->session_id = 'expired_2';
        $expired2->last_activity = $current_time - 7200;

        // Mock repository find returning expired sessions
        $this->repository->expects($this->once())
            ->method('find')
            ->with('PhlagSession', $this->callback(function($filters) use ($current_time, $max_lifetime) {
                return isset($filters['last_activity <']) && 
                       $filters['last_activity <'] === ($current_time - $max_lifetime);
            }))
            ->willReturn([$expired1, $expired2]);

        // Mock successful deletion for both sessions
        $this->repository->expects($this->exactly(2))
            ->method('delete')
            ->willReturnCallback(function($type, $id) {
                $this->assertEquals('PhlagSession', $type);
                $this->assertContains($id, ['expired_1', 'expired_2']);
                return true;
            });

        $deleted_count = $this->handler->gc($max_lifetime);

        $this->assertEquals(2, $deleted_count);
    }

    /**
     * Tests garbage collection with no expired sessions
     *
     * @return void
     */
    public function testGarbageCollectionWithNoExpiredSessions(): void {

        $current_time = time();
        $max_lifetime = 1800;

        // Mock repository find returning empty array
        $this->repository->expects($this->once())
            ->method('find')
            ->with('PhlagSession', ['last_activity <' => $current_time - $max_lifetime])
            ->willReturn([]);

        $deleted_count = $this->handler->gc($max_lifetime);

        $this->assertEquals(0, $deleted_count);
    }

    /**
     * Tests write handles repository exceptions gracefully
     *
     * @return void
     */
    public function testWriteHandlesExceptionsGracefully(): void {

        $session_id = 'error_session';
        $session_data = serialize(['test' => 'data']);

        // Mock repository throwing exception
        $this->repository->expects($this->once())
            ->method('get')
            ->willThrowException(new \RuntimeException('Database error'));

        $result = $this->handler->write($session_id, $session_data);

        $this->assertFalse($result);
    }

    /**
     * Tests read handles repository exceptions gracefully
     *
     * @return void
     */
    public function testReadHandlesExceptionsGracefully(): void {

        $session_id = 'error_session';

        // Mock repository throwing exception
        $this->repository->expects($this->once())
            ->method('get')
            ->willThrowException(new \RuntimeException('Database error'));

        $data = $this->handler->read($session_id);

        $this->assertSame('', $data);
    }

    /**
     * Tests destroy handles repository exceptions gracefully
     *
     * @return void
     */
    public function testDestroyHandlesExceptionsGracefully(): void {

        $session_id = 'error_session';

        // Mock repository throwing exception
        $this->repository->expects($this->once())
            ->method('get')
            ->willThrowException(new \RuntimeException('Database error'));

        $result = $this->handler->destroy($session_id);

        $this->assertFalse($result);
    }

    /**
     * Tests garbage collection handles exceptions gracefully
     *
     * @return void
     */
    public function testGarbageCollectionHandlesExceptionsGracefully(): void {

        // Mock repository throwing exception
        $this->repository->expects($this->once())
            ->method('find')
            ->willThrowException(new \RuntimeException('Database error'));

        $result = $this->handler->gc(1800);

        $this->assertFalse($result);
    }

    /**
     * Tests reading session with empty data returns empty string
     *
     * @return void
     */
    public function testReadSessionWithEmptyDataReturnsEmptyString(): void {

        $session_id = 'empty_data_session';

        // Mock repository returning session with empty data
        $session = new PhlagSession();
        $session->session_id = $session_id;
        $session->session_data = '';

        $this->repository->expects($this->once())
            ->method('get')
            ->with('PhlagSession', $session_id)
            ->willReturn($session);

        $data = $this->handler->read($session_id);

        $this->assertSame('', $data);
    }

    /**
     * Tests write returns false when save fails
     *
     * @return void
     */
    public function testWriteReturnsFalseWhenSaveFails(): void {

        $session_id = 'test_session';
        $session_data = serialize(['data' => 'test']);

        // Mock repository returning false for get
        $this->repository->expects($this->once())
            ->method('get')
            ->willReturn(false);

        // Mock new session
        $new_session = new PhlagSession();
        $this->repository->expects($this->once())
            ->method('new')
            ->willReturn($new_session);

        // Mock save returning false (failure)
        $this->repository->expects($this->once())
            ->method('save')
            ->with('PhlagSession', $this->isInstanceOf(PhlagSession::class))
            ->willReturn(false);

        $result = $this->handler->write($session_id, $session_data);

        $this->assertFalse($result);
    }
}
