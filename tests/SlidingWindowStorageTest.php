<?php

declare(strict_types=1);

namespace PhilipRehberger\CircuitBreaker\Tests;

use PhilipRehberger\CircuitBreaker\CircuitState;
use PhilipRehberger\CircuitBreaker\Storage\SlidingWindowStorage;
use PHPUnit\Framework\TestCase;

class SlidingWindowStorageTest extends TestCase
{
    public function test_starts_in_closed_state(): void
    {
        $storage = new SlidingWindowStorage(60);

        $this->assertSame(CircuitState::Closed, $storage->getState('test'));
    }

    public function test_tracks_failure_count(): void
    {
        $storage = new SlidingWindowStorage(60);

        $this->assertSame(0, $storage->getFailureCount('test'));

        $count = $storage->incrementFailures('test');
        $this->assertSame(1, $count);

        $count = $storage->incrementFailures('test');
        $this->assertSame(2, $count);

        $this->assertSame(2, $storage->getFailureCount('test'));
    }

    public function test_reset_failures_clears_count(): void
    {
        $storage = new SlidingWindowStorage(60);

        $storage->incrementFailures('test');
        $storage->incrementFailures('test');
        $storage->resetFailures('test');

        $this->assertSame(0, $storage->getFailureCount('test'));
    }

    public function test_old_failures_expire_outside_window(): void
    {
        // Use a very short window so failures expire quickly
        $storage = new SlidingWindowStorage(1);

        $storage->incrementFailures('test');
        $storage->incrementFailures('test');
        $this->assertSame(2, $storage->getFailureCount('test'));

        // Sleep just past the window
        usleep(1_100_000);

        // Old failures should be pruned
        $this->assertSame(0, $storage->getFailureCount('test'));
    }

    public function test_failures_within_window_are_counted(): void
    {
        $storage = new SlidingWindowStorage(60);

        $storage->incrementFailures('test');
        $storage->incrementFailures('test');
        $storage->incrementFailures('test');

        $this->assertSame(3, $storage->getFailureCount('test'));
    }

    public function test_set_and_get_state(): void
    {
        $storage = new SlidingWindowStorage(60);

        $storage->setState('test', CircuitState::Open);
        $this->assertSame(CircuitState::Open, $storage->getState('test'));

        $storage->setState('test', CircuitState::HalfOpen);
        $this->assertSame(CircuitState::HalfOpen, $storage->getState('test'));
    }

    public function test_last_failure_time(): void
    {
        $storage = new SlidingWindowStorage(60);

        $this->assertNull($storage->getLastFailureTime('test'));

        $time = microtime(true);
        $storage->setLastFailureTime('test', $time);

        $this->assertSame($time, $storage->getLastFailureTime('test'));
    }

    public function test_independent_services(): void
    {
        $storage = new SlidingWindowStorage(60);

        $storage->incrementFailures('service-a');
        $storage->incrementFailures('service-a');
        $storage->incrementFailures('service-b');

        $this->assertSame(2, $storage->getFailureCount('service-a'));
        $this->assertSame(1, $storage->getFailureCount('service-b'));
    }

    public function test_get_window_seconds(): void
    {
        $storage = new SlidingWindowStorage(120);

        $this->assertSame(120, $storage->getWindowSeconds());
    }
}
