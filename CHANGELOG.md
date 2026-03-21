# Changelog

All notable changes to `php-circuit-breaker` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.1.2] - 2026-03-20

### Added
- Expanded test suite with dedicated tests for KeyedCircuitBreaker and FileStorage

## [1.1.1] - 2026-03-17

### Changed
- Standardized package metadata, README structure, and CI workflow per package guide

## [1.1.0] - 2026-03-16

### Added
- `KeyedCircuitBreaker` class for managing independent circuit breakers per key
- Supports per-key `call()`, `state()`, `isOpen()`, `isClosed()`, `isHalfOpen()`, `reset()`, `trip()`, `remove()`, `keys()`, and `count()` methods
- All keyed breakers share a common `CircuitConfig` and `Storage` backend

## [1.0.2] - 2026-03-16

### Changed
- Standardize composer.json: add type, homepage, scripts

## [1.0.1] - 2026-03-15

### Changed
- Standardize README badges

## [1.0.0] - 2026-03-15

### Added
- Initial release
- Circuit breaker pattern with Closed, Open, and HalfOpen states
- Configurable failure threshold, recovery timeout, and success threshold
- In-memory and file-based storage backends
- Storage interface for custom backends
- Fluent builder API via `CircuitBreaker::for()`
- State change event callbacks
- Optional call timeout support
- `CircuitOpenException` for rejected calls
