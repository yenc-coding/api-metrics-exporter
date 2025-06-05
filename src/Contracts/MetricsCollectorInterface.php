<?php

declare(strict_types=1);

namespace SidcorpResource\ApiMetricsExporter\Contracts;

/**
 * Interface for the metrics collector
 */
interface MetricsCollectorInterface
{
    /**
     * Register a custom counter metric
     *
     * @param string $name Metric name
     * @param string $help Help text describing the metric
     * @param array<string> $labelNames Names of labels for this metric
     * @return CounterInterface
     */
    public function registerCounter(string $name, string $help, array $labelNames = []): CounterInterface;
    
    /**
     * Register a custom histogram metric
     *
     * @param string $name Metric name
     * @param string $help Help text describing the metric
     * @param array<string> $labelNames Names of labels for this metric
     * @param array<float> $buckets Histogram buckets
     * @return HistogramInterface
     */
    public function registerHistogram(string $name, string $help, array $labelNames = [], array $buckets = []): HistogramInterface;
    
    /**
     * Register a custom gauge metric
     *
     * @param string $name Metric name
     * @param string $help Help text describing the metric
     * @param array<string> $labelNames Names of labels for this metric
     * @return GaugeInterface
     */
    public function registerGauge(string $name, string $help, array $labelNames = []): GaugeInterface;
    
    /**
     * Register a custom summary metric
     *
     * @param string $name Metric name
     * @param string $help Help text describing the metric
     * @param array<string> $labelNames Names of labels for this metric
     * @param array<float, float> $quantiles Map of quantiles to error tolerance
     * @return SummaryInterface
     */
    public function registerSummary(string $name, string $help, array $labelNames = [], array $quantiles = []): SummaryInterface;

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
     * Observe a value in a histogram metric
     *
     * @param string $name Metric name
     * @param float $value Value to observe
     * @param array<string, string> $labels Metric labels
     * @return void
     */
    public function observeHistogram(string $name, float $value, array $labels = []): void;

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
     * Check if a path should be excluded from metrics collection
     *
     * @param string $path Request path
     * @param string $method HTTP method
     * @return bool True if path should be excluded
     */
    public function shouldExcludePath(string $path, string $method): bool;

    /**
     * Get all metrics in Prometheus exposition format
     *
     * @return string Metrics in OpenMetrics text format
     */
    public function getMetrics(): string;

    /**
     * Set a gauge value
     *
     * @param string $name Metric name
     * @param float $value Value to set
     * @param array<string, string> $labels Metric labels
     * @return void
     */
    public function setGauge(string $name, float $value, array $labels = []): void;
}
