<?php

declare(strict_types=1);

namespace PhilipRehberger\CircuitBreaker\Tests;

use PhilipRehberger\CircuitBreaker\CircuitState;
use PhilipRehberger\CircuitBreaker\Storage\FileStorage;
use PHPUnit\Framework\TestCase;

class FileStorageTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir().'/file_storage_test_'.uniqid();
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $files = glob($this->tempDir.'/*');
            if ($files !== false) {
                array_map('unlink', $files);
            }
            rmdir($this->tempDir);
        }
        parent::tearDown();
    }

    public function test_creates_directory_if_missing(): void
    {
        $this->assertDirectoryDoesNotExist($this->tempDir);

        new FileStorage($this->tempDir);

        $this->assertDirectoryExists($this->tempDir);
    }

    public function test_get_state_returns_closed_for_unknown_service(): void
    {
        $storage = new FileStorage($this->tempDir);

        $this->assertSame(CircuitState::Closed, $storage->getState('unknown'));
    }

    public function test_set_and_get_state(): void
    {
        $storage = new FileStorage($this->tempDir);

        $storage->setState('my-service', CircuitState::Open);

        $this->assertSame(CircuitState::Open, $storage->getState('my-service'));
    }

    public function test_set_state_to_half_open(): void
    {
        $storage = new FileStorage($this->tempDir);

        $storage->setState('my-service', CircuitState::HalfOpen);

        $this->assertSame(CircuitState::HalfOpen, $storage->getState('my-service'));
    }

    public function test_set_state_to_closed(): void
    {
        $storage = new FileStorage($this->tempDir);

        $storage->setState('my-service', CircuitState::Open);
        $storage->setState('my-service', CircuitState::Closed);

        $this->assertSame(CircuitState::Closed, $storage->getState('my-service'));
    }

    public function test_increment_failures_starts_at_one(): void
    {
        $storage = new FileStorage($this->tempDir);

        $count = $storage->incrementFailures('my-service');

        $this->assertSame(1, $count);
    }

    public function test_increment_failures_accumulates(): void
    {
        $storage = new FileStorage($this->tempDir);

        $storage->incrementFailures('my-service');
        $storage->incrementFailures('my-service');
        $count = $storage->incrementFailures('my-service');

        $this->assertSame(3, $count);
    }

    public function test_reset_failures_sets_count_to_zero(): void
    {
        $storage = new FileStorage($this->tempDir);

        $storage->incrementFailures('my-service');
        $storage->incrementFailures('my-service');
        $storage->resetFailures('my-service');

        $this->assertSame(0, $storage->getFailureCount('my-service'));
    }

    public function test_get_failure_count_returns_zero_for_unknown_service(): void
    {
        $storage = new FileStorage($this->tempDir);

        $this->assertSame(0, $storage->getFailureCount('unknown'));
    }

    public function test_get_failure_count_reflects_increments(): void
    {
        $storage = new FileStorage($this->tempDir);

        $storage->incrementFailures('my-service');
        $storage->incrementFailures('my-service');

        $this->assertSame(2, $storage->getFailureCount('my-service'));
    }

    public function test_get_last_failure_time_returns_null_for_unknown_service(): void
    {
        $storage = new FileStorage($this->tempDir);

        $this->assertNull($storage->getLastFailureTime('unknown'));
    }

    public function test_set_and_get_last_failure_time(): void
    {
        $storage = new FileStorage($this->tempDir);

        $time = 1700000000.123;
        $storage->setLastFailureTime('my-service', $time);

        $this->assertSame($time, $storage->getLastFailureTime('my-service'));
    }

    public function test_state_persists_across_instances(): void
    {
        $storage1 = new FileStorage($this->tempDir);
        $storage1->setState('my-service', CircuitState::Open);
        $storage1->incrementFailures('my-service');
        $storage1->incrementFailures('my-service');
        $storage1->setLastFailureTime('my-service', 1700000000.0);

        // Create a new instance pointing to the same directory
        $storage2 = new FileStorage($this->tempDir);

        $this->assertSame(CircuitState::Open, $storage2->getState('my-service'));
        $this->assertSame(2, $storage2->getFailureCount('my-service'));
        $this->assertSame(1700000000.0, $storage2->getLastFailureTime('my-service'));
    }

    public function test_different_services_have_separate_files(): void
    {
        $storage = new FileStorage($this->tempDir);

        $storage->setState('service-a', CircuitState::Open);
        $storage->setState('service-b', CircuitState::Closed);
        $storage->incrementFailures('service-a');

        $this->assertSame(CircuitState::Open, $storage->getState('service-a'));
        $this->assertSame(CircuitState::Closed, $storage->getState('service-b'));
        $this->assertSame(1, $storage->getFailureCount('service-a'));
        $this->assertSame(0, $storage->getFailureCount('service-b'));
    }

    public function test_handles_corrupted_file_gracefully(): void
    {
        $storage = new FileStorage($this->tempDir);

        // Write invalid JSON to the file
        $service = 'my-service';
        $path = $this->tempDir.DIRECTORY_SEPARATOR.md5($service).'.json';
        file_put_contents($path, 'not valid json{{{');

        // Should fall back to defaults
        $this->assertSame(CircuitState::Closed, $storage->getState($service));
        $this->assertSame(0, $storage->getFailureCount($service));
        $this->assertNull($storage->getLastFailureTime($service));
    }

    public function test_handles_invalid_state_in_file_gracefully(): void
    {
        $storage = new FileStorage($this->tempDir);

        // Write valid JSON with an invalid state value
        $service = 'my-service';
        $path = $this->tempDir.DIRECTORY_SEPARATOR.md5($service).'.json';
        file_put_contents($path, json_encode(['state' => 'invalid_state']));

        // Should fall back to Closed
        $this->assertSame(CircuitState::Closed, $storage->getState($service));
    }

    public function test_reset_failures_on_unknown_service_creates_file(): void
    {
        $storage = new FileStorage($this->tempDir);

        $storage->resetFailures('new-service');

        $this->assertSame(0, $storage->getFailureCount('new-service'));
    }

    public function test_multiple_fields_coexist_in_same_file(): void
    {
        $storage = new FileStorage($this->tempDir);

        $storage->setState('my-service', CircuitState::Open);
        $storage->incrementFailures('my-service');
        $storage->setLastFailureTime('my-service', 1700000000.5);

        // All fields should be readable
        $this->assertSame(CircuitState::Open, $storage->getState('my-service'));
        $this->assertSame(1, $storage->getFailureCount('my-service'));
        $this->assertSame(1700000000.5, $storage->getLastFailureTime('my-service'));
    }

    public function test_overwriting_last_failure_time(): void
    {
        $storage = new FileStorage($this->tempDir);

        $storage->setLastFailureTime('my-service', 1000.0);
        $storage->setLastFailureTime('my-service', 2000.0);

        $this->assertSame(2000.0, $storage->getLastFailureTime('my-service'));
    }
}
