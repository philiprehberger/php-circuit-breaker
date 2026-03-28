<?php

declare(strict_types=1);

namespace PhilipRehberger\CircuitBreaker\Tests;

use PhilipRehberger\CircuitBreaker\CircuitBreaker;
use PhilipRehberger\CircuitBreaker\CircuitConfig;
use PhilipRehberger\CircuitBreaker\CircuitOpenException;
use PhilipRehberger\CircuitBreaker\Storage\InMemoryStorage;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class HealthCheckTest extends TestCase
{
    public function test_health_check_probe_is_called_before_half_open_transition(): void
    {
        $probeCalled = false;

        $storage = new InMemoryStorage;
        $breaker = new CircuitBreaker(
            'test-service',
            new CircuitConfig(failureThreshold: 1, recoveryTimeout: 0),
            $storage,
        );

        $breaker->setHealthCheck(function () use (&$probeCalled): bool {
            $probeCalled = true;

            return true;
        });

        // Trip the circuit
        try {
            $breaker->call(fn () => throw new RuntimeException('fail'));
        } catch (RuntimeException) {
        }

        // With recoveryTimeout=0, evaluateState runs the health check
        $this->assertTrue($breaker->isHalfOpen());
        $this->assertTrue($probeCalled);
    }

    public function test_health_check_failure_prevents_half_open_transition(): void
    {
        $storage = new InMemoryStorage;
        $breaker = new CircuitBreaker(
            'test-service',
            new CircuitConfig(failureThreshold: 1, recoveryTimeout: 0),
            $storage,
        );

        $breaker->setHealthCheck(function (): bool {
            return false;
        });

        // Trip the circuit
        try {
            $breaker->call(fn () => throw new RuntimeException('fail'));
        } catch (RuntimeException) {
        }

        // Health check returns false, circuit stays Open
        $this->assertTrue($breaker->isOpen());
    }

    public function test_health_check_exception_prevents_half_open_transition(): void
    {
        $storage = new InMemoryStorage;
        $breaker = new CircuitBreaker(
            'test-service',
            new CircuitConfig(failureThreshold: 1, recoveryTimeout: 0),
            $storage,
        );

        $breaker->setHealthCheck(function (): bool {
            throw new RuntimeException('health check failed');
        });

        // Trip the circuit
        try {
            $breaker->call(fn () => throw new RuntimeException('fail'));
        } catch (RuntimeException) {
        }

        // Health check threw, circuit stays Open
        $this->assertTrue($breaker->isOpen());
    }

    public function test_health_check_resets_recovery_timer(): void
    {
        $storage = new InMemoryStorage;
        $breaker = new CircuitBreaker(
            'test-service',
            new CircuitConfig(failureThreshold: 1, recoveryTimeout: 0),
            $storage,
        );

        $attempts = 0;
        $breaker->setHealthCheck(function () use (&$attempts): bool {
            $attempts++;

            return $attempts >= 2;
        });

        // Trip the circuit
        try {
            $breaker->call(fn () => throw new RuntimeException('fail'));
        } catch (RuntimeException) {
        }

        // First check: probe fails, stays Open
        $this->assertTrue($breaker->isOpen());
        $this->assertSame(1, $attempts);

        // Second check: probe succeeds, transitions to HalfOpen
        $this->assertTrue($breaker->isHalfOpen());
        $this->assertSame(2, $attempts);
    }

    public function test_no_health_check_transitions_normally(): void
    {
        $storage = new InMemoryStorage;
        $breaker = new CircuitBreaker(
            'test-service',
            new CircuitConfig(failureThreshold: 1, recoveryTimeout: 0),
            $storage,
        );

        // No health check set - should transition normally
        try {
            $breaker->call(fn () => throw new RuntimeException('fail'));
        } catch (RuntimeException) {
        }

        $this->assertTrue($breaker->isHalfOpen());
    }

    public function test_set_health_check_returns_self(): void
    {
        $breaker = new CircuitBreaker('test-service');

        $result = $breaker->setHealthCheck(fn () => true);

        $this->assertSame($breaker, $result);
    }

    public function test_health_check_with_open_circuit_prevents_call(): void
    {
        $storage = new InMemoryStorage;
        $breaker = new CircuitBreaker(
            'test-service',
            new CircuitConfig(failureThreshold: 1, recoveryTimeout: 0),
            $storage,
        );

        $breaker->setHealthCheck(fn () => false);

        // Trip the circuit
        try {
            $breaker->call(fn () => throw new RuntimeException('fail'));
        } catch (RuntimeException) {
        }

        // Circuit is Open and health check fails, so it stays Open
        $this->expectException(CircuitOpenException::class);
        $breaker->call(fn () => 'should not execute');
    }
}
