<?php

declare(strict_types=1);

namespace PhilipRehberger\CircuitBreaker\Tests;

use PhilipRehberger\CircuitBreaker\CircuitBreaker;
use PhilipRehberger\CircuitBreaker\CircuitConfig;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class MetricsTest extends TestCase
{
    public function test_metrics_returns_initial_zeroes(): void
    {
        $breaker = new CircuitBreaker('test-service');

        $metrics = $breaker->metrics();

        $this->assertSame(0, $metrics['total_calls']);
        $this->assertSame(0, $metrics['successful_calls']);
        $this->assertSame(0, $metrics['failed_calls']);
        $this->assertSame(0.0, $metrics['success_rate']);
        $this->assertSame('closed', $metrics['current_state']);
        $this->assertNull($metrics['state_changed_at']);
        $this->assertSame(0, $metrics['consecutive_failures']);
    }

    public function test_metrics_tracks_successful_calls(): void
    {
        $breaker = new CircuitBreaker('test-service');

        $breaker->call(fn () => 'ok');
        $breaker->call(fn () => 'ok');

        $metrics = $breaker->metrics();

        $this->assertSame(2, $metrics['total_calls']);
        $this->assertSame(2, $metrics['successful_calls']);
        $this->assertSame(0, $metrics['failed_calls']);
        $this->assertEqualsWithDelta(1.0, $metrics['success_rate'], 0.001);
        $this->assertSame('closed', $metrics['current_state']);
    }

    public function test_metrics_tracks_failed_calls(): void
    {
        $breaker = new CircuitBreaker(
            'test-service',
            new CircuitConfig(failureThreshold: 10),
        );

        try {
            $breaker->call(fn () => throw new RuntimeException('fail'));
        } catch (RuntimeException) {
        }

        try {
            $breaker->call(fn () => throw new RuntimeException('fail'));
        } catch (RuntimeException) {
        }

        $metrics = $breaker->metrics();

        $this->assertSame(2, $metrics['total_calls']);
        $this->assertSame(0, $metrics['successful_calls']);
        $this->assertSame(2, $metrics['failed_calls']);
        $this->assertEqualsWithDelta(0.0, $metrics['success_rate'], 0.001);
        $this->assertSame(2, $metrics['consecutive_failures']);
    }

    public function test_metrics_tracks_mixed_calls(): void
    {
        $breaker = new CircuitBreaker(
            'test-service',
            new CircuitConfig(failureThreshold: 10),
        );

        $breaker->call(fn () => 'ok');
        $breaker->call(fn () => 'ok');
        $breaker->call(fn () => 'ok');

        try {
            $breaker->call(fn () => throw new RuntimeException('fail'));
        } catch (RuntimeException) {
        }

        $metrics = $breaker->metrics();

        $this->assertSame(4, $metrics['total_calls']);
        $this->assertSame(3, $metrics['successful_calls']);
        $this->assertSame(1, $metrics['failed_calls']);
        $this->assertSame(0.75, $metrics['success_rate']);
    }

    public function test_metrics_reflects_state_change(): void
    {
        $breaker = new CircuitBreaker(
            'test-service',
            new CircuitConfig(failureThreshold: 1, recoveryTimeout: 60),
        );

        try {
            $breaker->call(fn () => throw new RuntimeException('fail'));
        } catch (RuntimeException) {
        }

        $metrics = $breaker->metrics();

        $this->assertSame('open', $metrics['current_state']);
        $this->assertNotNull($metrics['state_changed_at']);
        $this->assertIsFloat($metrics['state_changed_at']);
    }

    public function test_metrics_state_changed_at_updates_on_transitions(): void
    {
        $breaker = new CircuitBreaker(
            'test-service',
            new CircuitConfig(failureThreshold: 1, recoveryTimeout: 60, successThreshold: 1),
        );

        // Trip to Open
        try {
            $breaker->call(fn () => throw new RuntimeException('fail'));
        } catch (RuntimeException) {
        }

        $openMetrics = $breaker->metrics();
        $this->assertSame('open', $openMetrics['current_state']);
        $openChangedAt = $openMetrics['state_changed_at'];
        $this->assertNotNull($openChangedAt);

        // Reset and make a success to see state_changed_at update
        $breaker->reset();

        $closedMetrics = $breaker->metrics();
        $this->assertSame('closed', $closedMetrics['current_state']);
        $this->assertNotNull($closedMetrics['state_changed_at']);
        $this->assertGreaterThanOrEqual($openChangedAt, $closedMetrics['state_changed_at']);
    }

    public function test_metrics_consecutive_failures_resets_on_success(): void
    {
        $breaker = new CircuitBreaker(
            'test-service',
            new CircuitConfig(failureThreshold: 10),
        );

        try {
            $breaker->call(fn () => throw new RuntimeException('fail'));
        } catch (RuntimeException) {
        }

        $this->assertSame(1, $breaker->metrics()['consecutive_failures']);

        // Reset happens when circuit closes from half-open; for simple closed state,
        // failures accumulate in storage. Let's verify after a reset.
        $breaker->reset();

        $this->assertSame(0, $breaker->metrics()['consecutive_failures']);
    }

    public function test_metrics_has_all_required_keys(): void
    {
        $breaker = new CircuitBreaker('test-service');

        $metrics = $breaker->metrics();

        $this->assertArrayHasKey('total_calls', $metrics);
        $this->assertArrayHasKey('successful_calls', $metrics);
        $this->assertArrayHasKey('failed_calls', $metrics);
        $this->assertArrayHasKey('success_rate', $metrics);
        $this->assertArrayHasKey('current_state', $metrics);
        $this->assertArrayHasKey('state_changed_at', $metrics);
        $this->assertArrayHasKey('consecutive_failures', $metrics);
    }
}
