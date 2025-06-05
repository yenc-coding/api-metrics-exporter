<?php

declare(strict_types=1);

namespace SidcorpResource\ApiMetricsExporter\Metrics;

use SidcorpResource\ApiMetricsExporter\Contracts\GaugeInterface;
use SidcorpResource\ApiMetricsExporter\Contracts\StorageDriverInterface;

/**
 * Gauge metric implementation
 */
class Gauge extends AbstractMetric implements GaugeInterface
{
    /**
     * Storage driver reference
     *
     * @var StorageDriverInterface
     */
    protected StorageDriverInterface $storage;
    
    /**
     * Create a new gauge metric instance
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
     * Set the gauge value
     *
     * @param float $value Value to set
     * @param array<string, string> $labels Labels for this metric
     * @return void
     */
    public function set(float $value, array $labels = []): void
    {
        $this->storage->setGauge($this->name, $value, $labels);
    }
    
    /**
     * Increment the gauge value
     *
     * @param float $value Value to increment by
     * @param array<string, string> $labels Labels for this metric
     * @return void
     */
    public function increment(float $value, array $labels = []): void
    {
        $this->storage->incrementGauge($this->name, $value, $labels);
    }
    
    /**
     * Decrement the gauge value
     *
     * @param float $value Value to decrement by
     * @param array<string, string> $labels Labels for this metric
     * @return void
     */
    public function decrement(float $value, array $labels = []): void
    {
        $this->storage->decrementGauge($this->name, $value, $labels);
    }
    
    /**
     * Get the current gauge value for the given labels
     *
     * @param array<string, string> $labels Labels for this metric
     * @return float Current gauge value
     */
    public function getValue(array $labels = []): float
    {
        return $this->storage->getGaugeValue($this->name, $labels);
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
