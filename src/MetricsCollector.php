<?php

declare(strict_types=1);

namespace SidcorpResource\ApiMetricsExporter;

use SidcorpResource\ApiMetricsExporter\Contracts\MetricsCollectorInterface;
use SidcorpResource\ApiMetricsExporter\Contracts\StorageDriverInterface;
use SidcorpResource\ApiMetricsExporter\Contracts\CounterInterface;
use SidcorpResource\ApiMetricsExporter\Contracts\GaugeInterface;
use SidcorpResource\ApiMetricsExporter\Contracts\HistogramInterface;
use SidcorpResource\ApiMetricsExporter\Contracts\SummaryInterface;
use SidcorpResource\ApiMetricsExporter\Exceptions\StorageDriverException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

/**
 * Collects and stores API metrics
 */
class MetricsCollector implements MetricsCollectorInterface
{
    /**
     * Metrics registry
     *
     * @var MetricsRegistry
     */
    protected MetricsRegistry $registry;

    /**
     * Storage driver for metrics
     *
     * @var StorageDriverInterface
     */
    protected StorageDriverInterface $driver;

    /**
     * Logger instance
     *
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;

    /**
     * Whether metrics collection is enabled
     * 
     * @var bool
     */
    protected bool $enabled;

    /**
     * Excluded paths for metrics collection
     * 
     * @var array<string>
     */
    protected array $excludedPaths;

    /**
     * Excluded HTTP methods for metrics collection
     * 
     * @var array<string>
     */
    protected array $excludedMethods;

    /**
     * Create a new metrics collector
     *
     * @param StorageDriverInterface|string $driver Storage driver or driver type name
     * @param array<string, mixed>|null $config Configuration options
     * @param LoggerInterface|null $logger Logger instance
     */
    public function __construct(
        $driver = 'redis',
        ?array $config = null,
        ?LoggerInterface $logger = null
    ) {
        $this->logger = $logger ?? new NullLogger();
        $this->excludedPaths = $config['excluded_paths'] ?? [];
        $this->excludedMethods = $config['excluded_methods'] ?? [];
        $this->enabled = $config['enabled'] ?? true;
        
        try {
            if ($driver instanceof StorageDriverInterface) {
                $this->driver = $driver;
            } else {
                $this->driver = StorageDriverFactory::create($driver, $config ?? [], $this->logger);
            }
            
            $this->registry = new MetricsRegistry($this->driver, $this->logger);
            $this->registerDefaultMetrics();
        } catch (Throwable $e) {
            $this->logger->error('Failed to initialize metrics collector: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
            
            // Fallback to in-memory driver if the requested driver fails
            $this->driver = StorageDriverFactory::create('memory', [], $this->logger);
            $this->registry = new MetricsRegistry($this->driver, $this->logger);
            $this->registerDefaultMetrics();
        }
    }

    /**
     * Register default metrics
     *
     * @return void
     */
    protected function registerDefaultMetrics(): void
    {
        // Base API metrics
        $this->registerCounter(
            'api_requests_total',
            'Total number of API requests',
            ['endpoint', 'method', 'status', 'user_id']
        );
        
        $this->registerHistogram(
            'api_request_duration_seconds',
            'API request duration in seconds',
            ['endpoint', 'method', 'status', 'user_id'],
            [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10]
        );
        
        $this->registerCounter(
            'api_unique_users_total',
            'Number of unique users per day accessing the API',
            ['endpoint', 'date']
        );
        
        $this->registerCounter(
            'api_errors_total',
            'Total number of API errors',
            ['endpoint', 'method', 'status', 'error_type', 'user_id']
        );
    }

    /**
     * Get histogram buckets from configuration
     *
     * @return array<float>
     */
    protected function getHistogramBuckets(): array
    {
        return [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1.0, 2.5, 5.0, 10.0];
    }

    /**
     * Create a storage driver based on config
     * 
     * This method implements a sophisticated driver creation strategy with automatic
     * fallback handling to ensure metrics collection continues working even if the
     * primary storage driver fails. The fallback order is: Redis -> InMemory
     * 
     * For Redis driver, it automatically configures Laravel's Redis client settings
     * to prevent conflicts with application-level Redis prefixes that could interfere
     * with metrics storage.
     *
     * @param string $driver Driver name (redis, memory/inmemory)
     * @param array<string, mixed> $config Configuration options
     * @return StorageDriverInterface
     * @throws StorageDriverException If the driver could not be created
     */
    protected function createDriver(string $driver, array $config = []): StorageDriverInterface
    {
        try {
            switch (strtolower($driver)) {
                case 'redis':
                    // Get Redis client configuration
                    $client = $config['redis']['client'] ?? 'predis';
                    
                    // Configure Laravel's Redis client to prevent automatic prefixes
                    if ($client === 'predis') {
                        config(['database.redis.client' => 'predis']);
                        // Ensure no default prefix is set for predis
                        config(['database.redis.options.prefix' => '']);
                    } elseif ($client === 'phpredis') {
                        config(['database.redis.client' => 'phpredis']);
                        // Ensure no default prefix is set for phpredis
                        config(['database.redis.options.prefix' => '']);
                    }
                    
                    return new RedisDriver(
                        $config['redis']['connection'] ?? 'default',
                        $config['redis']['prefix'] ?? 'api_metrics:',
                        $config['redis']['ttl'] ?? 86400,
                        $this->logger,
                        $client
                    );
                    
                case 'memory':
                case 'inmemory':
                    return new InMemoryDriver($this->logger);
                    
                default:
                    throw new StorageDriverException("Unsupported driver: {$driver}");
            }
        } catch (Throwable $e) {
            if ($driver === 'redis') {
                // Fall back to InMemory if Redis fails
                $this->logger->warning('Failed to create Redis driver, falling back to InMemory', [
                    'exception' => $e->getMessage(),
                ]);
                return new InMemoryDriver($this->logger);
            }
            
            throw new StorageDriverException("Failed to create driver: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Register a custom counter metric
     *
     * @param string $name Metric name
     * @param string $help Help text describing the metric
     * @param array<string> $labelNames Names of labels for this metric
     * @return CounterInterface
     */
    public function registerCounter(string $name, string $help, array $labelNames = []): CounterInterface
    {
        if (!$this->enabled) {
            $this->logger->debug("Metrics disabled, skipping counter registration for {$name}");
            return $this->registry->registerCounter('disabled_counter', 'Disabled counter metric', []);
        }
        
        try {
            return $this->registry->registerCounter($name, $help, $labelNames);
        } catch (Throwable $e) {
            $this->logger->error("Failed to register counter {$name}: " . $e->getMessage(), [
                'exception' => $e,
            ]);
            
            // Return a dummy counter that does nothing
            return $this->registry->registerCounter('dummy_counter', 'Dummy counter metric', []);
        }
    }
    
    /**
     * Register a custom histogram metric
     *
     * @param string $name Metric name
     * @param string $help Help text describing the metric
     * @param array<string> $labelNames Names of labels for this metric
     * @param array<float> $buckets Histogram buckets
     * @return HistogramInterface
     */
    public function registerHistogram(string $name, string $help, array $labelNames = [], array $buckets = []): HistogramInterface
    {
        if (!$this->enabled) {
            $this->logger->debug("Metrics disabled, skipping histogram registration for {$name}");
            return $this->registry->registerHistogram('disabled_histogram', 'Disabled histogram metric', []);
        }
        
        try {
            return $this->registry->registerHistogram($name, $help, $labelNames, $buckets);
        } catch (Throwable $e) {
            $this->logger->error("Failed to register histogram {$name}: " . $e->getMessage(), [
                'exception' => $e,
            ]);
            
            // Return a dummy histogram that does nothing
            return $this->registry->registerHistogram('dummy_histogram', 'Dummy histogram metric', []);
        }
    }

    /**
     * Register a custom gauge metric
     *
     * @param string $name Metric name
     * @param string $help Help text describing the metric
     * @param array<string> $labelNames Names of labels for this metric
     * @return GaugeInterface
     */
    public function registerGauge(string $name, string $help, array $labelNames = []): GaugeInterface
    {
        if (!$this->enabled) {
            $this->logger->debug("Metrics disabled, skipping gauge registration for {$name}");
            return $this->registry->registerGauge('disabled_gauge', 'Disabled gauge metric', []);
        }
        
        try {
            return $this->registry->registerGauge($name, $help, $labelNames);
        } catch (Throwable $e) {
            $this->logger->error("Failed to register gauge {$name}: " . $e->getMessage(), [
                'exception' => $e,
            ]);
            
            // Return a dummy gauge that does nothing
            return $this->registry->registerGauge('dummy_gauge', 'Dummy gauge metric', []);
        }
    }
    
    /**
     * Register a custom summary metric
     *
     * @param string $name Metric name
     * @param string $help Help text describing the metric
     * @param array<string> $labelNames Names of labels for this metric
     * @param array<float, float> $quantiles Map of quantiles to error tolerance
     * @return SummaryInterface
     */
    public function registerSummary(string $name, string $help, array $labelNames = [], array $quantiles = []): SummaryInterface
    {
        if (!$this->enabled) {
            $this->logger->debug("Metrics disabled, skipping summary registration for {$name}");
            return $this->registry->registerSummary('disabled_summary', 'Disabled summary metric', []);
        }
        
        try {
            return $this->registry->registerSummary($name, $help, $labelNames, $quantiles);
        } catch (Throwable $e) {
            $this->logger->error("Failed to register summary {$name}: " . $e->getMessage(), [
                'exception' => $e,
            ]);
            
            // Return a dummy summary that does nothing
            return $this->registry->registerSummary('dummy_summary', 'Dummy summary metric', []);
        }
    }

    /**
     * Increment a counter metric
     *
     * @param string $name Metric name
     * @param array<string, string> $labels Metric labels
     * @param int $value Value to increment by
     * @return void
     */
    public function incrementCounter(string $name, array $labels = [], int $value = 1): void
    {
        if (!$this->enabled) {
            return;
        }
        
        try {
            $metric = $this->registry->getMetric($name);
            if ($metric instanceof CounterInterface) {
                $metric->increment($labels, $value);
            } else {
                $this->driver->incrementCounter($name, $labels, $value);
            }
        } catch (Throwable $e) {
            $this->logger->error("Failed to increment counter {$name}: " . $e->getMessage(), [
                'exception' => $e,
                'labels' => $labels,
                'value' => $value,
            ]);
        }
    }

    /**
     * Observe a value in a histogram metric
     *
     * @param string $name Metric name
     * @param float $value Value to observe
     * @param array<string, string> $labels Metric labels
     * @return void
     */
    public function observeHistogram(string $name, float $value, array $labels = []): void
    {
        if (!$this->enabled) {
            return;
        }
        
        try {
            $metric = $this->registry->getMetric($name);
            if ($metric instanceof HistogramInterface) {
                $metric->observe($value, $labels);
            } else {
                $this->driver->observeHistogram($name, $value, $labels);
            }
        } catch (Throwable $e) {
            $this->logger->error("Failed to observe histogram {$name}: " . $e->getMessage(), [
                'exception' => $e,
                'labels' => $labels,
                'value' => $value,
            ]);
        }
    }

    /**
     * Track a unique occurrence (e.g., unique user visits)
     *
     * @param string $name Metric name
     * @param string $identifier Unique identifier (user_id, IP, etc.)
     * @param array<string, string> $labels Metric labels
     * @return void
     */
    public function trackUnique(string $name, string $identifier, array $labels = []): void
    {
        if (!$this->enabled) {
            return;
        }
        
        try {
            $this->driver->trackUnique($name, $identifier, $labels);
        } catch (Throwable $e) {
            $this->logger->error("Failed to track unique occurrence for {$name}: " . $e->getMessage(), [
                'exception' => $e,
                'identifier' => $identifier,
                'labels' => $labels,
            ]);
        }
    }

    /**
     * Get all metrics in Prometheus exposition format
     *
     * @return string Metrics in OpenMetrics text format
     */
    public function getMetrics(): string
    {
        if (!$this->enabled) {
            return "# Metrics collection is disabled\n";
        }
        
        try {
            return $this->driver->getMetrics();
        } catch (Throwable $e) {
            $this->logger->error("Failed to get metrics: " . $e->getMessage(), [
                'exception' => $e,
            ]);
            
            return "# Error getting metrics: {$e->getMessage()}\n";
        }
    }

    /**
     * Check if a path should be excluded from metrics collection
     * 
     * This method implements flexible path exclusion logic supporting both exact
     * matches and wildcard patterns. It checks both HTTP method and request path
     * against the configured exclusion lists.
     * 
     * Wildcard support: Patterns ending with '*' will match any path starting 
     * with the pattern prefix (e.g., 'debug*' matches 'debug', 'debug/info', etc.)
     *
     * @param string $path Request path (without leading slash, as returned by Laravel's request->path())
     * @param string $method HTTP method (GET, POST, etc.)
     * @return bool True if the path should be excluded, false otherwise
     */
    public function shouldExcludePath(string $path, string $method): bool
    {
        // Check method exclusion
        if (in_array(strtoupper($method), array_map('strtoupper', $this->excludedMethods))) {
            return true;
        }
        
        // Check path exclusion
        foreach ($this->excludedPaths as $excludedPath) {
            if ($excludedPath === $path) {
                return true;
            }
            
            // Check for wildcard patterns
            if (str_ends_with($excludedPath, '*') && 
                str_starts_with($path, substr($excludedPath, 0, -1))) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get the storage driver
     *
     * @return StorageDriverInterface
     */
    public function getDriver(): StorageDriverInterface
    {
        return $this->driver;
    }
    
    /**
     * Get the metrics registry
     *
     * @return MetricsRegistry
     */
    public function getRegistry(): MetricsRegistry
    {
        return $this->registry;
    }
    
    /**
     * Check if metrics collection is enabled
     *
     * @return bool Whether metrics collection is enabled
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }
    
    /**
     * Set whether metrics collection is enabled
     *
     * @param bool $enabled Whether metrics collection is enabled
     * @return void
     */
    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }
    
    /**
     * Flush all metrics data
     * 
     * @return bool True if successful, false otherwise
     */
    public function flushMetrics(): bool
    {
        try {
            if ($this->driver instanceof \SidcorpResource\ApiMetricsExporter\Drivers\RedisDriver) {
                return $this->driver->flushMetrics();
            } elseif ($this->driver instanceof \SidcorpResource\ApiMetricsExporter\Drivers\InMemoryDriver) {
                return $this->driver->flushMetrics();
            }
            
            $this->logger->warning('Could not flush metrics: unknown driver type');
            return false;
        } catch (Throwable $e) {
            $this->logger->error('Failed to flush metrics', [
                'exception' => $e->getMessage(),
            ]);
            return false;
        }
    }
    
    /**
     * Set a gauge value
     *
     * @param string $name Metric name
     * @param float $value Value to set
     * @param array<string, string> $labels Metric labels
     * @return void
     */
    public function setGauge(string $name, float $value, array $labels = []): void
    {
        if (!$this->enabled) {
            return;
        }
        
        try {
            $metric = $this->registry->getMetric($name);
            if ($metric instanceof GaugeInterface) {
                $metric->set($value, $labels);
            } else {
                $this->driver->setGauge($name, $value, $labels);
            }
        } catch (Throwable $e) {
            $this->logger->error("Failed to set gauge {$name}: " . $e->getMessage(), [
                'exception' => $e,
                'labels' => $labels,
                'value' => $value,
            ]);
        }
    }
}
