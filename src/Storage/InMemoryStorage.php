<?php

declare(strict_types=1);

namespace PhilipRehberger\CircuitBreaker\Storage;

use PhilipRehberger\CircuitBreaker\CircuitState;
use PhilipRehberger\CircuitBreaker\Contracts\Storage;

/**
 * In-memory storage backend scoped to the current process.
 */
class InMemoryStorage implements Storage
{
    /** @var array<string, CircuitState> */
    private array $states = [];

    /** @var array<string, int> */
    private array $failures = [];

    /** @var array<string, float> */
    private array $lastFailureTimes = [];

    /**
     * Get the current circuit state for a service.
     */
    public function getState(string $service): CircuitState
    {
        return $this->states[$service] ?? CircuitState::Closed;
    }

    /**
     * Set the circuit state for a service.
     */
    public function setState(string $service, CircuitState $state): void
    {
        $this->states[$service] = $state;
    }

    /**
     * Increment the failure count for a service and return the new count.
     */
    public function incrementFailures(string $service): int
    {
        $this->failures[$service] = ($this->failures[$service] ?? 0) + 1;

        return $this->failures[$service];
    }

    /**
     * Reset the failure count for a service to zero.
     */
    public function resetFailures(string $service): void
    {
        $this->failures[$service] = 0;
    }

    /**
     * Get the current failure count for a service.
     */
    public function getFailureCount(string $service): int
    {
        return $this->failures[$service] ?? 0;
    }

    /**
     * Get the timestamp of the last failure for a service, or null if none.
     */
    public function getLastFailureTime(string $service): ?float
    {
        return $this->lastFailureTimes[$service] ?? null;
    }

    /**
     * Set the timestamp of the last failure for a service.
     */
    public function setLastFailureTime(string $service, float $time): void
    {
        $this->lastFailureTimes[$service] = $time;
    }
}
