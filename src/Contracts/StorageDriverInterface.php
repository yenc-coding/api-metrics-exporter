<?php

declare(strict_types=1);

namespace SidcorpResource\ApiMetricsExporter\Contracts;

/**
 * Interface for metric storage drivers
 */
interface StorageDriverInterface
{
    /**
     * Increment a counter metric
     *
     * @param string $name Metric name
     * @param array<string, string> $labels Metric labels
     * @param int $value Value to increment by
     * @return void
     */
    public function incrementCounter(string $name, array $labels = [], int $value = 1): void;

    /**
     * Get the current value of a counter metric
     * 
     * @param string $name Metric name
     * @param array<string, string> $labels Metric labels
     * @return int Current counter value
     */
    public function getCounterValue(string $name, array $labels = []): int;

    /**
     * Observe a value in a histogram metric
     *
     * @param string $name Metric name
     * @param float $value Value to observe
     * @param array<string, string> $labels Metric labels
     * @param array<float> $buckets Bucket definitions
     * @return void
     */
    public function observeHistogram(string $name, float $value, array $labels = [], array $buckets = []): void;
    
    /**
     * Get the sum of all histogram observations for the given labels
     * 
     * @param string $name Metric name
     * @param array<string, string> $labels Metric labels
     * @return float Sum of observations
     */
    public function getHistogramSum(string $name, array $labels = []): float;
    
    /**
     * Get the count of histogram observations for the given labels
     * 
     * @param string $name Metric name
     * @param array<string, string> $labels Metric labels
     * @return int Count of observations
     */
    public function getHistogramCount(string $name, array $labels = []): int;

    /**
     * Set a gauge metric value
     * 
     * @param string $name Metric name
     * @param float $value Value to set
     * @param array<string, string> $labels Metric labels
     * @return void
     */
    public function setGauge(string $name, float $value, array $labels = []): void;
    
    /**
     * Increment a gauge metric
     * 
     * @param string $name Metric name
     * @param float $value Value to increment by
     * @param array<string, string> $labels Metric labels
     * @return void 
     */
    public function incrementGauge(string $name, float $value, array $labels = []): void;
    
    /**
     * Decrement a gauge metric
     * 
     * @param string $name Metric name
     * @param float $value Value to decrement by
     * @param array<string, string> $labels Metric labels
     * @return void
     */
    public function decrementGauge(string $name, float $value, array $labels = []): void;
    
    /**
     * Get the current value of a gauge metric
     * 
     * @param string $name Metric name
     * @param array<string, string> $labels Metric labels
     * @return float Current gauge value
     */
    public function getGaugeValue(string $name, array $labels = []): float;
    
    /**
     * Observe a value in a summary metric
     * 
     * @param string $name Metric name
     * @param float $value Value to observe
     * @param array<string, string> $labels Metric labels
     * @param array<float, float> $quantiles Quantile definitions
     * @return void
     */
    public function observeSummary(string $name, float $value, array $labels = [], array $quantiles = []): void;
    
    /**
     * Get the sum of all summary observations for the given labels
     * 
     * @param string $name Metric name
     * @param array<string, string> $labels Metric labels
     * @return float Sum of observations
     */
    public function getSummarySum(string $name, array $labels = []): float;
    
    /**
     * Get the count of summary observations for the given labels
     * 
     * @param string $name Metric name
     * @param array<string, string> $labels Metric labels
     * @return int Count of observations
     */
    public function getSummaryCount(string $name, array $labels = []): int;

    /**
     * Track a unique occurrence (e.g., unique user visits)
     *
     * @param string $name Metric name
     * @param string $identifier Unique identifier (user_id, IP, etc.)
     * @param array<string, string> $labels Metric labels
     * @return void
     */
    public function trackUnique(string $name, string $identifier, array $labels = []): void;

    /**
     * Get all metrics in Prometheus exposition format
     *
     * @return string Metrics in OpenMetrics text format
     */
    public function getMetrics(): string;

    /**
     * Register a new counter metric
     *
     * @param string $name Metric name
     * @param string $help Help text describing the metric
     * @param array<string> $labelNames Names of labels for this metric
     * @return void
     */
    public function registerCounter(string $name, string $help, array $labelNames = []): void;

    /**
     * Register a new histogram metric
     *
     * @param string $name Metric name
     * @param string $help Help text describing the metric
     * @param array<string> $labelNames Names of labels for this metric
     * @param array<float> $buckets Histogram buckets
     * @return void
     */
    public function registerHistogram(string $name, string $help, array $labelNames = [], array $buckets = []): void;
    
    /**
     * Flush all metrics data
     * 
     * @return bool True if successful, false otherwise
     */
    public function flushMetrics(): bool;
}
