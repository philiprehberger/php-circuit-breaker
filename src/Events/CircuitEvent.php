<?php

declare(strict_types=1);

namespace PhilipRehberger\CircuitBreaker\Events;

/**
 * Events emitted by the circuit breaker during state transitions and calls.
 */
enum CircuitEvent: string
{
    case Opened = 'opened';
    case Closed = 'closed';
    case HalfOpened = 'half_opened';
    case CallSucceeded = 'call_succeeded';
    case CallFailed = 'call_failed';
}
