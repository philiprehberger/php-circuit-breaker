# PHP Circuit Breaker

[![CI](https://github.com/philiprehberger/php-circuit-breaker/actions/workflows/tests.yml/badge.svg)](https://github.com/philiprehberger/php-circuit-breaker/actions/workflows/tests.yml)
[![Packagist Version](https://img.shields.io/packagist/v/philiprehberger/php-circuit-breaker)](https://packagist.org/packages/philiprehberger/php-circuit-breaker)
[![GitHub Release](https://img.shields.io/github/v/release/philiprehberger/php-circuit-breaker)](https://github.com/philiprehberger/php-circuit-breaker/releases)
[![Last Updated](https://img.shields.io/github/last-commit/philiprehberger/php-circuit-breaker)](https://github.com/philiprehberger/php-circuit-breaker/commits/main)
[![License](https://img.shields.io/github/license/philiprehberger/php-circuit-breaker)](LICENSE)
[![Bug Reports](https://img.shields.io/github/issues/philiprehberger/php-circuit-breaker/bug)](https://github.com/philiprehberger/php-circuit-breaker/issues?q=label%3Abug)
[![Feature Requests](https://img.shields.io/github/issues/philiprehberger/php-circuit-breaker/enhancement)](https://github.com/philiprehberger/php-circuit-breaker/issues?q=label%3Aenhancement)
[![Sponsor](https://img.shields.io/badge/sponsor-GitHub%20Sponsors-ec6cb9)](https://github.com/sponsors/philiprehberger)

Circuit breaker pattern with configurable thresholds and multiple storage backends.

## Requirements

- PHP 8.2+

## Installation

```bash
composer require philiprehberger/php-circuit-breaker
```

## Usage

### Basic Usage

```php
use PhilipRehberger\CircuitBreaker\CircuitBreaker;
use PhilipRehberger\CircuitBreaker\CircuitConfig;
use PhilipRehberger\CircuitBreaker\Storage\InMemoryStorage;

$breaker = new CircuitBreaker(
    service: 'payment-api',
    config: new CircuitConfig(
        failureThreshold: 5,
        recoveryTimeout: 30,
        successThreshold: 1,
    ),
    storage: new InMemoryStorage(),
);

$result = $breaker->call(function () {
    return file_get_contents('https://api.example.com/charge');
});
```

### Fluent Builder

```php
use PhilipRehberger\CircuitBreaker\CircuitBreaker;
use PhilipRehberger\CircuitBreaker\Storage\FileStorage;

$breaker = CircuitBreaker::for('payment-api')
    ->failAfter(3)
    ->recoverAfter(60)
    ->storage(new FileStorage('/tmp/circuits'))
    ->onStateChange(function ($event, $breaker) {
        echo "Circuit event: {$event->value}\n";
    })
    ->build();

try {
    $result = $breaker->call(fn () => callExternalService());
} catch (\PhilipRehberger\CircuitBreaker\CircuitOpenException $e) {
    // Circuit is open, use fallback
}
```

### Per-Key Circuit Breakers

Use `KeyedCircuitBreaker` to manage independent circuit breakers per key:

```php
use PhilipRehberger\CircuitBreaker\KeyedCircuitBreaker;
use PhilipRehberger\CircuitBreaker\CircuitConfig;
use PhilipRehberger\CircuitBreaker\Storage\InMemoryStorage;

$breakers = new KeyedCircuitBreaker(
    config: new CircuitConfig(failureThreshold: 3, recoveryTimeout: 60),
    storage: new InMemoryStorage(),
);

$userResult  = $breakers->call('user-api',  fn () => fetchUsers());
$orderResult = $breakers->call('order-api', fn () => fetchOrders());
```

### Fallback

Register a fallback to return a default value when the circuit is open instead of throwing:

```php
$breaker = CircuitBreaker::for('payment-api')
    ->failAfter(3)
    ->recoverAfter(60)
    ->fallback(fn () => ['status' => 'unavailable'])
    ->build();

// Returns the fallback value when the circuit is open
$result = $breaker->call(fn () => callExternalService());
```

### Health Check Probes

Register a health check probe for smarter recovery from the Open state:

```php
$breaker = new CircuitBreaker(
    service: 'payment-api',
    config: new CircuitConfig(failureThreshold: 3, recoveryTimeout: 30),
);

$breaker->setHealthCheck(function (): bool {
    // Check if the external service is reachable
    return @file_get_contents('https://api.example.com/health') !== false;
});
```

When the recovery timeout elapses, the health check runs before transitioning to HalfOpen. If the probe fails, the recovery timer resets.

### Metrics

Inspect detailed metrics about circuit breaker usage:

```php
$breaker = new CircuitBreaker('payment-api');

$breaker->call(fn () => fetchData());

$metrics = $breaker->metrics();
// [
//     'total_calls'          => 1,
//     'successful_calls'     => 1,
//     'failed_calls'         => 0,
//     'success_rate'         => 1.0,
//     'current_state'        => 'closed',
//     'state_changed_at'     => null,
//     'consecutive_failures' => 0,
// ]
```

### Stats

Inspect circuit breaker usage statistics at any time:

```php
$breaker = new CircuitBreaker('payment-api');

$breaker->call(fn () => fetchData());

$stats = $breaker->getStats();
// [
//     'total_calls'     => 1,
//     'successes'       => 1,
//     'failures'        => 0,
//     'last_failure_at' => null,
//     'current_state'   => 'closed',
// ]
```

### Custom Storage Backend

```php
use PhilipRehberger\CircuitBreaker\Contracts\Storage;
use PhilipRehberger\CircuitBreaker\CircuitState;

class RedisStorage implements Storage
{
    public function getState(string $service): CircuitState { /* ... */ }
    public function setState(string $service, CircuitState $state): void { /* ... */ }
    public function incrementFailures(string $service): int { /* ... */ }
    public function resetFailures(string $service): void { /* ... */ }
    public function getFailureCount(string $service): int { /* ... */ }
    public function getLastFailureTime(string $service): ?float { /* ... */ }
    public function setLastFailureTime(string $service, float $time): void { /* ... */ }
}
```

## API

### CircuitBreaker

| Method | Description |
|--------|-------------|
| `new CircuitBreaker(string $service, CircuitConfig $config, Storage $storage)` | Create a circuit breaker |
| `CircuitBreaker::for(string $service)` | Create a fluent builder |
| `->call(callable $fn): mixed` | Execute a callable through the circuit breaker |
| `->isOpen(): bool` | Check if the circuit is open |
| `->isClosed(): bool` | Check if the circuit is closed |
| `->isHalfOpen(): bool` | Check if the circuit is half-open |
| `->state(): CircuitState` | Get the current circuit state |
| `->reset(): void` | Reset the circuit to closed |
| `->trip(): void` | Manually open the circuit |
| `->getStats(): array` | Get usage statistics (total calls, successes, failures, last failure timestamp, current state) |
| `->metrics(): array` | Get detailed metrics (total, successful, failed calls, success rate, state, state change time, consecutive failures) |
| `->setFallback(callable $fallback): void` | Register a fallback invoked when the circuit is open |
| `->setHealthCheck(callable $probe): self` | Register a health check probe for smarter Open-to-HalfOpen recovery |

### CircuitBreakerBuilder

| Method | Description |
|--------|-------------|
| `->failAfter(int $failures): self` | Set the failure threshold |
| `->recoverAfter(int $seconds): self` | Set the recovery timeout |
| `->succeedAfter(int $successes): self` | Set the success threshold for half-open |
| `->timeout(float $seconds): self` | Set the call timeout |
| `->storage(Storage $storage): self` | Set the storage backend |
| `->fallback(callable $fallback): self` | Register a fallback for when the circuit is open |
| `->onStateChange(callable $callback): self` | Set a state change callback |
| `->build(): CircuitBreaker` | Build the configured instance |

### KeyedCircuitBreaker

| Method | Description |
|--------|-------------|
| `new KeyedCircuitBreaker(CircuitConfig $config, Storage $storage)` | Create a keyed circuit breaker manager |
| `->call(string $key, callable $fn): mixed` | Execute through the breaker for the given key |
| `->state(string $key): CircuitState` | Get the state for a specific key |
| `->isOpen(string $key): bool` | Check if the circuit for a key is open |
| `->isClosed(string $key): bool` | Check if the circuit for a key is closed |
| `->isHalfOpen(string $key): bool` | Check if the circuit for a key is half-open |
| `->reset(string $key): void` | Reset the circuit for a specific key |
| `->trip(string $key): void` | Manually open the circuit for a specific key |
| `->remove(string $key): void` | Remove the breaker for a key entirely |
| `->keys(): string[]` | Get all tracked keys |
| `->count(): int` | Get the number of tracked keys |

### CircuitConfig

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `failureThreshold` | `int` | `5` | Failures before opening |
| `recoveryTimeout` | `int` | `30` | Seconds before half-open transition |
| `successThreshold` | `int` | `1` | Successes in half-open to close |
| `timeout` | `?float` | `null` | Optional call timeout in seconds |

### Storage Backends

| Class | Description |
|-------|-------------|
| `InMemoryStorage` | In-memory, scoped to the current process |
| `FileStorage` | JSON file-based, persists across requests |
| `SlidingWindowStorage` | Time-window-based failure tracking with automatic pruning |

## Development

```bash
composer install
vendor/bin/phpunit
vendor/bin/pint --test
```

## Support

[![LinkedIn](https://img.shields.io/badge/LinkedIn-Philip%20Rehberger-blue?logo=linkedin)](https://www.linkedin.com/in/philiprehberger/)
[![Packages](https://img.shields.io/badge/All%20Packages-philiprehberger.com-blue)](https://philiprehberger.com)

## License

[MIT](LICENSE)
