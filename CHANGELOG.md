# Changelog

All notable changes to `php-circuit-breaker` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
