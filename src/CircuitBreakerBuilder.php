<?php

declare(strict_types=1);

namespace PhilipRehberger\CircuitBreaker;

use PhilipRehberger\CircuitBreaker\Contracts\Storage;
use PhilipRehberger\CircuitBreaker\Events\CircuitEvent;
use PhilipRehberger\CircuitBreaker\Storage\InMemoryStorage;

/**
 * Fluent builder for creating CircuitBreaker instances.
 */
class CircuitBreakerBuilder
{
    private int $failureThreshold = 5;

    private int $recoveryTimeout = 30;

    private int $successThreshold = 1;

    private ?float $timeout = null;

    private ?Storage $storage = null;

    /** @var (callable(CircuitEvent, CircuitBreaker): void)|null */
    private $stateChangeCallback = null;

    /**
     * Create a new builder instance.
     */
    public function __construct(
        private readonly string $service,
    ) {}

    /**
     * Set the number of failures before the circuit opens.
     */
    public function failAfter(int $failures): self
    {
        $this->failureThreshold = $failures;

        return $this;
    }

    /**
     * Set the recovery timeout in seconds.
     */
    public function recoverAfter(int $seconds): self
    {
        $this->recoveryTimeout = $seconds;

        return $this;
    }

    /**
     * Set the number of successes required in HalfOpen state to close the circuit.
     */
    public function succeedAfter(int $successes): self
    {
        $this->successThreshold = $successes;

        return $this;
    }

    /**
     * Set the call timeout in seconds.
     */
    public function timeout(float $seconds): self
    {
        $this->timeout = $seconds;

        return $this;
    }

    /**
     * Set the storage backend.
     */
    public function storage(Storage $storage): self
    {
        $this->storage = $storage;

        return $this;
    }

    /**
     * Set a callback for state change events.
     *
     * @param  callable(CircuitEvent, CircuitBreaker): void  $callback
     */
    public function onStateChange(callable $callback): self
    {
        $this->stateChangeCallback = $callback;

        return $this;
    }

    /**
     * Build and return the configured CircuitBreaker instance.
     */
    public function build(): CircuitBreaker
    {
        $config = new CircuitConfig(
            failureThreshold: $this->failureThreshold,
            recoveryTimeout: $this->recoveryTimeout,
            successThreshold: $this->successThreshold,
            timeout: $this->timeout,
        );

        $breaker = new CircuitBreaker(
            service: $this->service,
            config: $config,
            storage: $this->storage ?? new InMemoryStorage,
        );

        if ($this->stateChangeCallback !== null) {
            $breaker->onStateChange($this->stateChangeCallback);
        }

        return $breaker;
    }
}
