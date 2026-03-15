<?php

declare(strict_types=1);

namespace PhilipRehberger\CircuitBreaker\Storage;

use PhilipRehberger\CircuitBreaker\CircuitState;
use PhilipRehberger\CircuitBreaker\Contracts\Storage;
use RuntimeException;

/**
 * JSON file-based storage backend for persisting circuit breaker state across requests.
 */
class FileStorage implements Storage
{
    /**
     * Create a new file storage instance.
     *
     * @param  string  $directory  Directory path where state files will be stored.
     */
    public function __construct(
        private readonly string $directory,
    ) {
        if (! is_dir($this->directory) && ! mkdir($this->directory, 0755, true)) {
            throw new RuntimeException("Unable to create storage directory: {$this->directory}");
        }
    }

    /**
     * Get the current circuit state for a service.
     */
    public function getState(string $service): CircuitState
    {
        $data = $this->read($service);

        return CircuitState::tryFrom($data['state'] ?? '') ?? CircuitState::Closed;
    }

    /**
     * Set the circuit state for a service.
     */
    public function setState(string $service, CircuitState $state): void
    {
        $data = $this->read($service);
        $data['state'] = $state->value;
        $this->write($service, $data);
    }

    /**
     * Increment the failure count for a service and return the new count.
     */
    public function incrementFailures(string $service): int
    {
        $data = $this->read($service);
        $data['failures'] = ($data['failures'] ?? 0) + 1;
        $this->write($service, $data);

        return $data['failures'];
    }

    /**
     * Reset the failure count for a service to zero.
     */
    public function resetFailures(string $service): void
    {
        $data = $this->read($service);
        $data['failures'] = 0;
        $this->write($service, $data);
    }

    /**
     * Get the current failure count for a service.
     */
    public function getFailureCount(string $service): int
    {
        $data = $this->read($service);

        return $data['failures'] ?? 0;
    }

    /**
     * Get the timestamp of the last failure for a service, or null if none.
     */
    public function getLastFailureTime(string $service): ?float
    {
        $data = $this->read($service);

        return isset($data['last_failure_time']) ? (float) $data['last_failure_time'] : null;
    }

    /**
     * Set the timestamp of the last failure for a service.
     */
    public function setLastFailureTime(string $service, float $time): void
    {
        $data = $this->read($service);
        $data['last_failure_time'] = $time;
        $this->write($service, $data);
    }

    /**
     * Get the file path for a service.
     */
    private function filePath(string $service): string
    {
        return $this->directory.DIRECTORY_SEPARATOR.md5($service).'.json';
    }

    /**
     * Read the stored data for a service.
     *
     * @return array<string, mixed>
     */
    private function read(string $service): array
    {
        $path = $this->filePath($service);

        if (! file_exists($path)) {
            return [];
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return [];
        }

        /** @var array<string, mixed>|null $data */
        $data = json_decode($contents, true);

        return is_array($data) ? $data : [];
    }

    /**
     * Write data for a service to disk.
     *
     * @param  array<string, mixed>  $data
     */
    private function write(string $service, array $data): void
    {
        $path = $this->filePath($service);
        $json = json_encode($data, JSON_PRETTY_PRINT);

        if ($json === false || file_put_contents($path, $json, LOCK_EX) === false) {
            throw new RuntimeException("Unable to write circuit breaker state to: {$path}");
        }
    }
}
