<?php

declare(strict_types=1);

namespace SidcorpResource\ApiMetricsExporter\Decorators;

use SidcorpResource\ApiMetricsExporter\Contracts\StorageDriverInterface;
use SidcorpResource\ApiMetricsExporter\Contracts\StorageDriverDecoratorInterface;

/**
 * Abstract base class for storage driver decorators
 */
abstract class AbstractStorageDriverDecorator implements StorageDriverDecoratorInterface
{
    /**
     * The decorated storage driver
     *
     * @var StorageDriverInterface
     */
    protected StorageDriverInterface $driver;
    
    /**
     * Create a new storage driver decorator
     *
     * @param StorageDriverInterface $driver The storage driver to decorate
     */
    public function __construct(StorageDriverInterface $driver)
    {
        $this->driver = $driver;
    }
    
    /**
     * Get the decorated storage driver
     *
     * @return StorageDriverInterface
     */
    public function getDecoratedDriver(): StorageDriverInterface
    {
        return $this->driver;
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
        $this->driver->incrementCounter($name, $labels, $value);
    }

    /**
     * Get the current value of a counter metric
     * 
     * @param string $name Metric name
     * @param array<string, string> $labels Metric labels
     * @return int Current counter value
     */
    public function getCounterValue(string $name, array $labels = []): int
    {
        return $this->driver->getCounterValue($name, $labels);
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
        $this->driver->observeHistogram($name, $value, $labels, $buckets);
    }
    
    /**
     * Get the sum of all histogram observations for the given labels
     * 
     * @param string $name Metric name
     * @param array<string, string> $labels Metric labels
     * @return float Sum of observations
     */
    public function getHistogramSum(string $name, array $labels = []): float
    {
        return $this->driver->getHistogramSum($name, $labels);
    }
    
    /**
     * Get the count of histogram observations for the given labels
     * 
     * @param string $name Metric name
     * @param array<string, string> $labels Metric labels
     * @return int Count of observations
     */
    public function getHistogramCount(string $name, array $labels = []): int
    {
        return $this->driver->getHistogramCount($name, $labels);
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
        $this->driver->setGauge($name, $value, $labels);
    }
    
    /**
     * Increment a gauge metric
     * 
     * @param string $name Metric name
     * @param float $value Value to increment by
     * @param array<string, string> $labels Metric labels
     * @return void 
     */
    public function incrementGauge(string $name, float $value, array $labels = []): void
    {
        $this->driver->incrementGauge($name, $value, $labels);
    }
    
    /**
     * Decrement a gauge metric
     * 
     * @param string $name Metric name
     * @param float $value Value to decrement by
     * @param array<string, string> $labels Metric labels
     * @return void
     */
    public function decrementGauge(string $name, float $value, array $labels = []): void
    {
        $this->driver->decrementGauge($name, $value, $labels);
    }
    
    /**
     * Get the current value of a gauge metric
     * 
     * @param string $name Metric name
     * @param array<string, string> $labels Metric labels
     * @return float Current gauge value
     */
    public function getGaugeValue(string $name, array $labels = []): float
    {
        return $this->driver->getGaugeValue($name, $labels);
    }
    
    /**
     * Observe a value in a summary metric
     * 
     * @param string $name Metric name
     * @param float $value Value to observe
     * @param array<string, string> $labels Metric labels
     * @param array<float, float> $quantiles Quantile definitions
     * @return void
     */
    public function observeSummary(string $name, float $value, array $labels = [], array $quantiles = []): void
    {
        $this->driver->observeSummary($name, $value, $labels, $quantiles);
    }
    
    /**
     * Get the sum of all summary observations for the given labels
     * 
     * @param string $name Metric name
     * @param array<string, string> $labels Metric labels
     * @return float Sum of observations
     */
    public function getSummarySum(string $name, array $labels = []): float
    {
        return $this->driver->getSummarySum($name, $labels);
    }
    
    /**
     * Get the count of summary observations for the given labels
     * 
     * @param string $name Metric name
     * @param array<string, string> $labels Metric labels
     * @return int Count of observations
     */
    public function getSummaryCount(string $name, array $labels = []): int
    {
        return $this->driver->getSummaryCount($name, $labels);
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
        $this->driver->trackUnique($name, $identifier, $labels);
    }

    /**
     * Get all metrics in Prometheus exposition format
     *
     * @return string Metrics in OpenMetrics text format
     */
    public function getMetrics(): string
    {
        return $this->driver->getMetrics();
    }
    
    /**
     * Register a new counter metric
     *
     * @param string $name Metric name
     * @param string $help Help text describing the metric
     * @param array<string> $labelNames Names of labels for this metric
     * @return void
     */
    public function registerCounter(string $name, string $help, array $labelNames = []): void
    {
        $this->driver->registerCounter($name, $help, $labelNames);
    }
    
    /**
     * Register a new gauge metric
     *
     * @param string $name Metric name
     * @param string $help Help text describing the metric
     * @param array<string> $labelNames Names of labels for this metric
     * @return void
     */
    public function registerGauge(string $name, string $help, array $labelNames = []): void
    {
        $this->driver->registerGauge($name, $help, $labelNames);
    }
    
    /**
     * Register a new histogram metric
     *
     * @param string $name Metric name
     * @param string $help Help text describing the metric
     * @param array<string> $labelNames Names of labels for this metric
     * @param array<float> $buckets Histogram buckets
     * @return void
     */
    public function registerHistogram(string $name, string $help, array $labelNames = [], array $buckets = []): void
    {
        $this->driver->registerHistogram($name, $help, $labelNames, $buckets);
    }
    
    /**
     * Register a new summary metric
     *
     * @param string $name Metric name
     * @param string $help Help text describing the metric
     * @param array<string> $labelNames Names of labels for this metric
     * @param array<float, float> $quantiles Map of quantiles to error tolerance
     * @return void
     */
    public function registerSummary(string $name, string $help, array $labelNames = [], array $quantiles = []): void
    {
        $this->driver->registerSummary($name, $help, $labelNames, $quantiles);
    }
    
    /**
     * Clear all metrics (for testing)
     *
     * @return void
     */
    public function clear(): void
    {
        $this->driver->clear();
    }
}
