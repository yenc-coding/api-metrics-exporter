<?php

declare(strict_types=1);

namespace SidcorpResource\ApiMetricsExporter\Metrics;

use SidcorpResource\ApiMetricsExporter\Contracts\HistogramInterface;
use SidcorpResource\ApiMetricsExporter\Contracts\StorageDriverInterface;

/**
 * Histogram metric implementation
 */
class Histogram extends AbstractMetric implements HistogramInterface
{
    /**
     * Storage driver reference
     *
     * @var StorageDriverInterface
     */
    protected StorageDriverInterface $storage;

    /**
     * Histogram bucket definitions
     *
     * @var array<float>
     */
    protected array $buckets;
    
    /**
     * Create a new histogram metric instance
     *
     * @param string $name Metric name
     * @param string $help Help text describing the metric
     * @param array<string> $labelNames Names of labels for this metric
     * @param array<float> $buckets Histogram buckets
     * @param StorageDriverInterface $storage Storage driver
     */
    public function __construct(
        string $name,
        string $help,
        array $labelNames,
        array $buckets,
        StorageDriverInterface $storage
    ) {
        parent::__construct($name, $help, $labelNames);
        $this->storage = $storage;
        $this->buckets = !empty($buckets) ? $buckets : [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10];
    }
    
    /**
     * Observe a value in the histogram
     *
     * @param float $value Value to observe
     * @param array<string, string> $labels Labels for this observation
     * @return void
     */
    public function observe(float $value, array $labels = []): void
    {
        $this->storage->observeHistogram($this->name, $value, $labels, $this->buckets);
    }
    
    /**
     * Get histogram buckets
     *
     * @return array<float>
     */
    public function getBuckets(): array
    {
        return $this->buckets;
    }
    
    /**
     * Get the sum of all observations for the given labels
     *
     * @param array<string, string> $labels Labels for this metric
     * @return float Sum of observations
     */
    public function getSum(array $labels = []): float
    {
        return $this->storage->getHistogramSum($this->name, $labels);
    }
    
    /**
     * Get the count of observations for the given labels
     *
     * @param array<string, string> $labels Labels for this metric
     * @return int Count of observations
     */
    public function getCount(array $labels = []): int
    {
        return $this->storage->getHistogramCount($this->name, $labels);
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
