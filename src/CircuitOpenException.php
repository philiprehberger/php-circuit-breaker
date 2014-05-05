<?php

declare(strict_types=1);

namespace PhilipRehberger\CircuitBreaker;

use RuntimeException;

/**
 * Thrown when a call is attempted while the circuit is open.
 */
class CircuitOpenException extends RuntimeException
{
    /**
     * Create a new circuit open exception.
     */
    public function __construct(
        public readonly string $service,
        string $message = '',
    ) {
        parent::__construct($message ?: "Circuit breaker is open for service '{$service}'.");
    }
}
