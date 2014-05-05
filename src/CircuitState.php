<?php

declare(strict_types=1);

namespace PhilipRehberger\CircuitBreaker;

/**
 * Represents the possible states of a circuit breaker.
 */
enum CircuitState: string
{
    case Closed = 'closed';
    case Open = 'open';
    case HalfOpen = 'half_open';
}
