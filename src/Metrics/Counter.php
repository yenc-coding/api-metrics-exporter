<?php

declare(strict_types=1);

namespace SidcorpResource\ApiMetricsExporter\Metrics;

use SidcorpResource\ApiMetricsExporter\Contracts\CounterInterface;
use SidcorpResource\ApiMetricsExporter\Contracts\StorageDriverInterface;

/**
 * Counter metric implementation
 */
class Counter extends AbstractMetric implements CounterInterface
{
    /**
     * Storage driver reference
     *
     * @var StorageDriverInterface
     */
    protected StorageDriverInterface $storage;
    
    /**
     * Create a new counter metric instance
     *
     * @param string $name Metric name
     * @param string $help Help text describing the metric
     * @param array<string> $labelNames Names of labels for this metric
     * @param StorageDriverInterface $storage Storage driver
     */
    public function __construct(
        string $name,
        string $help,
        array $labelNames,
        StorageDriverInterface $storage
    ) {
        parent::__construct($name, $help, $labelNames);
        $this->storage = $storage;
    }
    
    /**
     * Increment the counter value
     *
     * @param array<string, string> $labels Labels for this metric
     * @param int $value Value to increment by
     * @return void
     */
    public function increment(array $labels = [], int $value = 1): void
    {
        $this->storage->incrementCounter($this->name, $labels, $value);
    }
    
    /**
     * Get current counter value for given labels
     *
     * @param array<string, string> $labels Labels for this metric
     * @return int Current counter value
     */
    public function getValue(array $labels = []): int
    {
        return $this->storage->getCounterValue($this->name, $labels);
    }
    
    /**
     * Format this metric for Prometheus exposition format
     *
     * @return string
     */
    public function getPrometheusMetricString(): string
    {
        // This is implemented in the storage driver for efficiency
        // We don't need to implement it here as the storage driver has all the data
        return '';
    }
}
