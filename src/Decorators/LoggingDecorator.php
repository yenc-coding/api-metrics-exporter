<?php

declare(strict_types=1);

namespace SidcorpResource\ApiMetricsExporter\Decorators;

use SidcorpResource\ApiMetricsExporter\Contracts\StorageDriverInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Decorator that logs metric operations
 */
class LoggingDecorator extends AbstractStorageDriverDecorator
{
    /**
     * Logger instance
     *
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;
    
    /**
     * Log level to use
     *
     * @var string
     */
    protected string $logLevel;
    
    /**
     * Create a new logging decorator
     *
     * @param StorageDriverInterface $driver The storage driver to decorate
     * @param LoggerInterface|null $logger Logger instance
     * @param string $logLevel Log level to use
     */
    public function __construct(
        StorageDriverInterface $driver,
        ?LoggerInterface $logger = null,
        string $logLevel = 'debug'
    ) {
        parent::__construct($driver);
        $this->logger = $logger ?? new NullLogger();
        $this->logLevel = $logLevel;
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
        $this->logger->{$this->logLevel}(
            "Incrementing counter {$name} by {$value}",
            ['labels' => $labels]
        );
        parent::incrementCounter($name, $labels, $value);
    }
    
    /**
     * Observe a value in a histogram metric
     *
     * @param string $name Metric name
     * @param float $value Value to observe
     * @param array<string, string> $labels Metric labels
     * @param array<float> $buckets Bucket definitions
     * @return void
     */
    public function observeHistogram(string $name, float $value, array $labels = [], array $buckets = []): void
    {
        $this->logger->{$this->logLevel}(
            "Observing histogram {$name} with value {$value}",
            ['labels' => $labels]
        );
        parent::observeHistogram($name, $value, $labels, $buckets);
    }
    
    /**
     * Set a gauge metric value
     * 
     * @param string $name Metric name
     * @param float $value Value to set
     * @param array<string, string> $labels Metric labels
     * @return void
     */
    public function setGauge(string $name, float $value, array $labels = []): void
    {
        $this->logger->{$this->logLevel}(
            "Setting gauge {$name} to {$value}",
            ['labels' => $labels]
        );
        parent::setGauge($name, $value, $labels);
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
        $this->logger->{$this->logLevel}(
            "Tracking unique occurrence for {$name}",
            ['identifier' => $identifier, 'labels' => $labels]
        );
        parent::trackUnique($name, $identifier, $labels);
    }
}
