<?php

declare(strict_types=1);

namespace PhilipRehberger\CircuitBreaker\Storage;

use PhilipRehberger\CircuitBreaker\CircuitState;
use PhilipRehberger\CircuitBreaker\Contracts\Storage;

/**
 * Sliding window storage backend that tracks timestamped failures within a configurable time window.
 */
class SlidingWindowStorage implements Storage
{
    /** @var array<string, CircuitState> */
    private array $states = [];

    /** @var array<string, list<float>> */
    private array $failures = [];

    /** @var array<string, float> */
    private array $lastFailureTimes = [];

    /**
     * Create a new sliding window storage instance.
     *
     * @param  int  $windowSeconds  The time window in seconds for counting failures.
     */
    public function __construct(
        private readonly int $windowSeconds = 60,
    ) {}

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
     * Increment the failure count by adding a timestamped entry and return the count within the window.
     */
    public function incrementFailures(string $service): int
    {
        $this->failures[$service][] = microtime(true);
        $this->prune($service);

        return count($this->failures[$service]);
    }

    /**
     * Reset the failure entries for a service.
     */
    public function resetFailures(string $service): void
    {
        $this->failures[$service] = [];
    }

    /**
     * Get the current failure count within the sliding window for a service.
     */
    public function getFailureCount(string $service): int
    {
        $this->prune($service);

        return count($this->failures[$service] ?? []);
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

    /**
     * Get the window size in seconds.
     */
    public function getWindowSeconds(): int
    {
        return $this->windowSeconds;
    }

    /**
     * Prune failure entries outside the current time window.
     */
    private function prune(string $service): void
    {
        if (! isset($this->failures[$service])) {
            return;
        }

        $cutoff = microtime(true) - $this->windowSeconds;

        $this->failures[$service] = array_values(
            array_filter(
                $this->failures[$service],
                static fn (float $timestamp): bool => $timestamp >= $cutoff,
            )
        );
    }
}
