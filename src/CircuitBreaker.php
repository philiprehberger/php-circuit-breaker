<?php

declare(strict_types=1);

namespace PhilipRehberger\CircuitBreaker;

use PhilipRehberger\CircuitBreaker\Contracts\Storage;
use PhilipRehberger\CircuitBreaker\Events\CircuitEvent;
use PhilipRehberger\CircuitBreaker\Storage\InMemoryStorage;
use Throwable;

/**
 * Circuit breaker implementation with configurable thresholds and storage backends.
 */
class CircuitBreaker
{
    /** @var int Tracks consecutive successes in HalfOpen state. */
    private int $halfOpenSuccesses = 0;

    private int $totalCalls = 0;

    private int $successCount = 0;

    private int $failureCount = 0;

    private ?string $lastFailureAt = null;

    /** @var (callable(CircuitEvent, self): void)|null */
    private $onStateChange = null;

    /** @var callable|null */
    private $fallback = null;

    /**
     * Create a new circuit breaker instance.
     */
    public function __construct(
        private readonly string $service,
        private readonly CircuitConfig $config = new CircuitConfig,
        private readonly Storage $storage = new InMemoryStorage,
    ) {}

    /**
     * Create a fluent builder for a circuit breaker.
     */
    public static function for(string $service): CircuitBreakerBuilder
    {
        return new CircuitBreakerBuilder($service);
    }

    /**
     * Execute a callable through the circuit breaker.
     *
     * @template T
     *
     * @param  callable(): T  $fn
     * @return T
     *
     * @throws CircuitOpenException When the circuit is open and the recovery timeout has not elapsed.
     * @throws Throwable When the callable throws and the circuit records the failure.
     */
    public function call(callable $fn): mixed
    {
        $this->evaluateState();

        $state = $this->storage->getState($this->service);

        if ($state === CircuitState::Open) {
            if ($this->fallback !== null) {
                $this->totalCalls++;

                return ($this->fallback)();
            }

            throw new CircuitOpenException($this->service);
        }

        $this->totalCalls++;

        try {
            $result = $this->executeWithTimeout($fn);
            $this->successCount++;
            $this->recordSuccess();
            $this->emit(CircuitEvent::CallSucceeded);

            return $result;
        } catch (Throwable $e) {
            $this->failureCount++;
            $this->lastFailureAt = date('Y-m-d\TH:i:sP');
            $this->recordFailure();
            $this->emit(CircuitEvent::CallFailed);

            throw $e;
        }
    }

    /**
     * Check if the circuit is currently open.
     */
    public function isOpen(): bool
    {
        $this->evaluateState();

        return $this->storage->getState($this->service) === CircuitState::Open;
    }

    /**
     * Check if the circuit is currently closed.
     */
    public function isClosed(): bool
    {
        $this->evaluateState();

        return $this->storage->getState($this->service) === CircuitState::Closed;
    }

    /**
     * Check if the circuit is currently half-open.
     */
    public function isHalfOpen(): bool
    {
        $this->evaluateState();

        return $this->storage->getState($this->service) === CircuitState::HalfOpen;
    }

    /**
     * Get the current state of the circuit.
     */
    public function state(): CircuitState
    {
        $this->evaluateState();

        return $this->storage->getState($this->service);
    }

    /**
     * Reset the circuit breaker to the closed state.
     */
    public function reset(): void
    {
        $previousState = $this->storage->getState($this->service);
        $this->storage->setState($this->service, CircuitState::Closed);
        $this->storage->resetFailures($this->service);
        $this->halfOpenSuccesses = 0;

        if ($previousState !== CircuitState::Closed) {
            $this->emit(CircuitEvent::Closed);
        }
    }

    /**
     * Manually trip the circuit to the open state.
     */
    public function trip(): void
    {
        $previousState = $this->storage->getState($this->service);
        $this->storage->setState($this->service, CircuitState::Open);
        $this->storage->setLastFailureTime($this->service, microtime(true));
        $this->halfOpenSuccesses = 0;

        if ($previousState !== CircuitState::Open) {
            $this->emit(CircuitEvent::Opened);
        }
    }

    /**
     * Set a callback for state change events.
     *
     * @param  callable(CircuitEvent, self): void  $callback
     */
    public function onStateChange(callable $callback): void
    {
        $this->onStateChange = $callback;
    }

    /**
     * Register a fallback callable invoked when the circuit is open instead of throwing.
     *
     * @template T
     *
     * @param  callable(): T  $fallback
     */
    public function setFallback(callable $fallback): void
    {
        $this->fallback = $fallback;
    }

    /**
     * Get statistics about circuit breaker usage.
     *
     * @return array{total_calls: int, successes: int, failures: int, last_failure_at: ?string, current_state: string}
     */
    public function getStats(): array
    {
        $this->evaluateState();

        return [
            'total_calls' => $this->totalCalls,
            'successes' => $this->successCount,
            'failures' => $this->failureCount,
            'last_failure_at' => $this->lastFailureAt,
            'current_state' => $this->storage->getState($this->service)->value,
        ];
    }

    /**
     * Evaluate whether the circuit should transition from Open to HalfOpen.
     */
    private function evaluateState(): void
    {
        $state = $this->storage->getState($this->service);

        if ($state !== CircuitState::Open) {
            return;
        }

        $lastFailure = $this->storage->getLastFailureTime($this->service);

        if ($lastFailure === null) {
            return;
        }

        if ((microtime(true) - $lastFailure) >= $this->config->recoveryTimeout) {
            $this->storage->setState($this->service, CircuitState::HalfOpen);
            $this->halfOpenSuccesses = 0;
            $this->emit(CircuitEvent::HalfOpened);
        }
    }

    /**
     * Record a successful call.
     */
    private function recordSuccess(): void
    {
        $state = $this->storage->getState($this->service);

        if ($state === CircuitState::HalfOpen) {
            $this->halfOpenSuccesses++;

            if ($this->halfOpenSuccesses >= $this->config->successThreshold) {
                $this->storage->setState($this->service, CircuitState::Closed);
                $this->storage->resetFailures($this->service);
                $this->halfOpenSuccesses = 0;
                $this->emit(CircuitEvent::Closed);
            }
        }
    }

    /**
     * Record a failed call.
     */
    private function recordFailure(): void
    {
        $this->storage->setLastFailureTime($this->service, microtime(true));
        $failures = $this->storage->incrementFailures($this->service);

        $state = $this->storage->getState($this->service);

        if ($state === CircuitState::HalfOpen) {
            $this->storage->setState($this->service, CircuitState::Open);
            $this->halfOpenSuccesses = 0;
            $this->emit(CircuitEvent::Opened);

            return;
        }

        if ($failures >= $this->config->failureThreshold) {
            $this->storage->setState($this->service, CircuitState::Open);
            $this->halfOpenSuccesses = 0;
            $this->emit(CircuitEvent::Opened);
        }
    }

    /**
     * Execute the callable, optionally enforcing a timeout.
     *
     * @template T
     *
     * @param  callable(): T  $fn
     * @return T
     */
    private function executeWithTimeout(callable $fn): mixed
    {
        if ($this->config->timeout === null) {
            return $fn();
        }

        $startTime = microtime(true);
        $result = $fn();
        $elapsed = microtime(true) - $startTime;

        if ($elapsed > $this->config->timeout) {
            throw new \RuntimeException(
                "Circuit breaker call exceeded timeout of {$this->config->timeout}s (took {$elapsed}s)."
            );
        }

        return $result;
    }

    /**
     * Emit a circuit event to the registered callback.
     */
    private function emit(CircuitEvent $event): void
    {
        if ($this->onStateChange !== null) {
            ($this->onStateChange)($event, $this);
        }
    }
}
