# PHP Circuit Breaker

[![Tests](https://github.com/philiprehberger/php-circuit-breaker/actions/workflows/tests.yml/badge.svg)](https://github.com/philiprehberger/php-circuit-breaker/actions/workflows/tests.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/philiprehberger/php-circuit-breaker.svg)](https://packagist.org/packages/philiprehberger/php-circuit-breaker)
[![License](https://img.shields.io/github/license/philiprehberger/php-circuit-breaker)](LICENSE)

Circuit breaker pattern with configurable thresholds and multiple storage backends.

## Requirements

| Dependency | Version |
|------------|---------|
| PHP        | ^8.2    |

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

## Development

```bash
composer install
vendor/bin/phpunit
vendor/bin/pint --test
vendor/bin/phpstan analyse
```

## License

MIT
