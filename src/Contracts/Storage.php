<?php

declare(strict_types=1);

namespace PhilipRehberger\CircuitBreaker\Contracts;

use PhilipRehberger\CircuitBreaker\CircuitState;

/**
 * Storage backend interface for persisting circuit breaker state.
 */
interface Storage
{
    /**
     * Get the current circuit state for a service.
     */
    public function getState(string $service): CircuitState;

    /**
     * Set the circuit state for a service.
     */
    public function setState(string $service, CircuitState $state): void;

    /**
     * Increment the failure count for a service and return the new count.
     */
    public function incrementFailures(string $service): int;

    /**
     * Reset the failure count for a service to zero.
     */
    public function resetFailures(string $service): void;

    /**
     * Get the current failure count for a service.
     */
    public function getFailureCount(string $service): int;

    /**
     * Get the timestamp of the last failure for a service, or null if none.
     */
    public function getLastFailureTime(string $service): ?float;

    /**
     * Set the timestamp of the last failure for a service.
     */
    public function setLastFailureTime(string $service, float $time): void;
}
