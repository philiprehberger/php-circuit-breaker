<?php

declare(strict_types=1);

namespace PhilipRehberger\CircuitBreaker;

use PhilipRehberger\CircuitBreaker\Contracts\Storage;
use PhilipRehberger\CircuitBreaker\Storage\InMemoryStorage;

/**
 * Manages independent circuit breakers per key, sharing a common configuration.
 */
class KeyedCircuitBreaker
{
    /** @var array<string, CircuitBreaker> */
    private array $breakers = [];

    /**
     * Create a new keyed circuit breaker manager.
     */
    public function __construct(
        private readonly CircuitConfig $config = new CircuitConfig,
        private readonly Storage $storage = new InMemoryStorage,
    ) {}

    /**
     * Execute a callable with circuit breaker protection for the given key.
     *
     * @template T
     *
     * @param  callable(): T  $fn
     * @return T
     *
     * @throws CircuitOpenException When the circuit for the given key is open.
     */
    public function call(string $key, callable $fn): mixed
    {
        return $this->getBreaker($key)->call($fn);
    }

    /**
     * Get the current state for a specific key.
     */
    public function state(string $key): CircuitState
    {
        return $this->getBreaker($key)->state();
    }

    /**
     * Check if the circuit for a specific key is open.
     */
    public function isOpen(string $key): bool
    {
        return $this->getBreaker($key)->isOpen();
    }

    /**
     * Check if the circuit for a specific key is closed.
     */
    public function isClosed(string $key): bool
    {
        return $this->getBreaker($key)->isClosed();
    }

    /**
     * Check if the circuit for a specific key is half-open.
     */
    public function isHalfOpen(string $key): bool
    {
        return $this->getBreaker($key)->isHalfOpen();
    }

    /**
     * Reset the circuit breaker for a specific key.
     */
    public function reset(string $key): void
    {
        if (isset($this->breakers[$key])) {
            $this->breakers[$key]->reset();
        }
    }

    /**
     * Manually trip the circuit for a specific key.
     */
    public function trip(string $key): void
    {
        $this->getBreaker($key)->trip();
    }

    /**
     * Remove the circuit breaker for a specific key entirely.
     */
    public function remove(string $key): void
    {
        unset($this->breakers[$key]);
    }

    /**
     * Get all tracked keys.
     *
     * @return string[]
     */
    public function keys(): array
    {
        return array_keys($this->breakers);
    }

    /**
     * Get the number of tracked keys.
     */
    public function count(): int
    {
        return count($this->breakers);
    }

    private function getBreaker(string $key): CircuitBreaker
    {
        return $this->breakers[$key] ??= new CircuitBreaker(
            service: $key,
            config: $this->config,
            storage: $this->storage,
        );
    }
}
