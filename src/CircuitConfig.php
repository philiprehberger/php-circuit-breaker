<?php

declare(strict_types=1);

namespace PhilipRehberger\CircuitBreaker;

/**
 * Configuration for a circuit breaker instance.
 */
readonly class CircuitConfig
{
    /**
     * Create a new circuit breaker configuration.
     *
     * @param  int  $failureThreshold  Number of failures before opening the circuit.
     * @param  int  $recoveryTimeout  Seconds to wait before transitioning from Open to HalfOpen.
     * @param  int  $successThreshold  Number of consecutive successes in HalfOpen to close the circuit.
     * @param  float|null  $timeout  Optional timeout in seconds for the callable execution.
     */
    public function __construct(
        public int $failureThreshold = 5,
        public int $recoveryTimeout = 30,
        public int $successThreshold = 1,
        public ?float $timeout = null,
    ) {}
}
