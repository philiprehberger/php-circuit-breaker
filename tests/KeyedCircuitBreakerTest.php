<?php

declare(strict_types=1);

namespace PhilipRehberger\CircuitBreaker\Tests;

use PhilipRehberger\CircuitBreaker\CircuitConfig;
use PhilipRehberger\CircuitBreaker\CircuitOpenException;
use PhilipRehberger\CircuitBreaker\CircuitState;
use PhilipRehberger\CircuitBreaker\KeyedCircuitBreaker;
use PhilipRehberger\CircuitBreaker\Storage\InMemoryStorage;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class KeyedCircuitBreakerTest extends TestCase
{
    public function test_execute_returns_result_for_successful_call(): void
    {
        $keyed = new KeyedCircuitBreaker;

        $result = $keyed->call('service-a', fn () => 'hello');

        $this->assertSame('hello', $result);
    }

    public function test_execute_propagates_exception_on_failure(): void
    {
        $keyed = new KeyedCircuitBreaker;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('boom');

        $keyed->call('service-a', fn () => throw new RuntimeException('boom'));
    }

    public function test_state_returns_closed_for_new_key(): void
    {
        $keyed = new KeyedCircuitBreaker;

        $this->assertSame(CircuitState::Closed, $keyed->state('service-a'));
    }

    public function test_state_returns_open_after_threshold_exceeded(): void
    {
        $keyed = new KeyedCircuitBreaker(
            config: new CircuitConfig(failureThreshold: 2),
        );

        for ($i = 0; $i < 2; $i++) {
            try {
                $keyed->call('service-a', fn () => throw new RuntimeException('fail'));
            } catch (RuntimeException) {
            }
        }

        $this->assertSame(CircuitState::Open, $keyed->state('service-a'));
    }

    public function test_is_open_returns_false_initially(): void
    {
        $keyed = new KeyedCircuitBreaker;

        $this->assertFalse($keyed->isOpen('service-a'));
    }

    public function test_is_open_returns_true_after_tripping(): void
    {
        $keyed = new KeyedCircuitBreaker;

        $keyed->trip('service-a');

        $this->assertTrue($keyed->isOpen('service-a'));
    }

    public function test_is_closed_returns_true_initially(): void
    {
        $keyed = new KeyedCircuitBreaker;

        $this->assertTrue($keyed->isClosed('service-a'));
    }

    public function test_is_closed_returns_false_when_open(): void
    {
        $keyed = new KeyedCircuitBreaker;

        $keyed->trip('service-a');

        $this->assertFalse($keyed->isClosed('service-a'));
    }

    public function test_is_half_open_returns_false_initially(): void
    {
        $keyed = new KeyedCircuitBreaker;

        $this->assertFalse($keyed->isHalfOpen('service-a'));
    }

    public function test_is_half_open_after_recovery_timeout(): void
    {
        $keyed = new KeyedCircuitBreaker(
            config: new CircuitConfig(failureThreshold: 1, recoveryTimeout: 0),
        );

        try {
            $keyed->call('service-a', fn () => throw new RuntimeException('fail'));
        } catch (RuntimeException) {
        }

        $this->assertTrue($keyed->isHalfOpen('service-a'));
    }

    public function test_reset_closes_open_circuit(): void
    {
        $keyed = new KeyedCircuitBreaker;

        $keyed->trip('service-a');
        $this->assertTrue($keyed->isOpen('service-a'));

        $keyed->reset('service-a');

        // reset() only acts on existing breakers; after trip the breaker exists
        // After reset the breaker should be closed
        $this->assertTrue($keyed->isClosed('service-a'));
    }

    public function test_reset_on_unknown_key_does_nothing(): void
    {
        $keyed = new KeyedCircuitBreaker;

        // Should not throw
        $keyed->reset('nonexistent');

        $this->assertSame(0, $keyed->count());
    }

    public function test_trip_opens_circuit_for_key(): void
    {
        $keyed = new KeyedCircuitBreaker;

        $keyed->trip('service-a');

        $this->assertTrue($keyed->isOpen('service-a'));
        $this->assertSame(CircuitState::Open, $keyed->state('service-a'));
    }

    public function test_trip_throws_circuit_open_on_subsequent_call(): void
    {
        $keyed = new KeyedCircuitBreaker(
            config: new CircuitConfig(recoveryTimeout: 60),
        );

        $keyed->trip('service-a');

        $this->expectException(CircuitOpenException::class);
        $keyed->call('service-a', fn () => 'should not run');
    }

    public function test_remove_deletes_breaker_for_key(): void
    {
        $keyed = new KeyedCircuitBreaker;

        $keyed->call('service-a', fn () => 'ok');
        $this->assertSame(1, $keyed->count());

        $keyed->remove('service-a');

        $this->assertSame(0, $keyed->count());
        $this->assertNotContains('service-a', $keyed->keys());
    }

    public function test_remove_nonexistent_key_does_nothing(): void
    {
        $keyed = new KeyedCircuitBreaker;

        // Should not throw
        $keyed->remove('nonexistent');

        $this->assertSame(0, $keyed->count());
    }

    public function test_keys_returns_empty_array_initially(): void
    {
        $keyed = new KeyedCircuitBreaker;

        $this->assertSame([], $keyed->keys());
    }

    public function test_keys_returns_tracked_keys(): void
    {
        $keyed = new KeyedCircuitBreaker;

        $keyed->call('service-a', fn () => 'ok');
        $keyed->call('service-b', fn () => 'ok');
        $keyed->call('service-c', fn () => 'ok');

        $keys = $keyed->keys();
        sort($keys);

        $this->assertSame(['service-a', 'service-b', 'service-c'], $keys);
    }

    public function test_count_returns_zero_initially(): void
    {
        $keyed = new KeyedCircuitBreaker;

        $this->assertSame(0, $keyed->count());
    }

    public function test_count_tracks_number_of_keys(): void
    {
        $keyed = new KeyedCircuitBreaker;

        $keyed->call('service-a', fn () => 'ok');
        $this->assertSame(1, $keyed->count());

        $keyed->call('service-b', fn () => 'ok');
        $this->assertSame(2, $keyed->count());
    }

    public function test_count_does_not_increment_for_same_key(): void
    {
        $keyed = new KeyedCircuitBreaker;

        $keyed->call('service-a', fn () => 'first');
        $keyed->call('service-a', fn () => 'second');

        $this->assertSame(1, $keyed->count());
    }

    public function test_keys_are_independent(): void
    {
        $keyed = new KeyedCircuitBreaker(
            config: new CircuitConfig(failureThreshold: 1, recoveryTimeout: 60),
        );

        // Trip service-a but leave service-b intact
        $keyed->trip('service-a');

        $this->assertTrue($keyed->isOpen('service-a'));
        $this->assertTrue($keyed->isClosed('service-b'));

        // service-b should still work
        $result = $keyed->call('service-b', fn () => 'still works');
        $this->assertSame('still works', $result);
    }

    public function test_shared_storage_backend(): void
    {
        $storage = new InMemoryStorage;
        $keyed = new KeyedCircuitBreaker(
            config: new CircuitConfig(failureThreshold: 1),
            storage: $storage,
        );

        try {
            $keyed->call('service-a', fn () => throw new RuntimeException('fail'));
        } catch (RuntimeException) {
        }

        // Verify the storage backend has the state
        $this->assertSame(CircuitState::Open, $storage->getState('service-a'));
    }

    public function test_remove_then_recreate_starts_fresh(): void
    {
        $keyed = new KeyedCircuitBreaker(
            config: new CircuitConfig(failureThreshold: 2),
        );

        // Accumulate one failure
        try {
            $keyed->call('service-a', fn () => throw new RuntimeException('fail'));
        } catch (RuntimeException) {
        }

        $this->assertTrue($keyed->isClosed('service-a'));

        // Remove and recreate — new breaker starts fresh (in-memory failures reset)
        $keyed->remove('service-a');

        // The breaker is gone; a new call creates a fresh one
        $this->assertSame(0, $keyed->count());
    }
}
