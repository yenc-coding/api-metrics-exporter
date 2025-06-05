<?php

declare(strict_types=1);

namespace SidcorpResource\ApiMetricsExporter\Metrics;

use SidcorpResource\ApiMetricsExporter\Contracts\SummaryInterface;
use SidcorpResource\ApiMetricsExporter\Contracts\StorageDriverInterface;

/**
 * Summary metric implementation
 */
class Summary extends AbstractMetric implements SummaryInterface
{
    /**
     * Storage driver reference
     *
     * @var StorageDriverInterface
     */
    protected StorageDriverInterface $storage;

    /**
     * Configured quantiles
     *
     * @var array<float, float>
     */
    protected array $quantiles;
    
    /**
     * Create a new summary metric instance
     *
     * @param string $name Metric name
     * @param string $help Help text describing the metric
     * @param array<string> $labelNames Names of labels for this metric
     * @param array<float, float> $quantiles Map of quantiles to their error margins
     * @param StorageDriverInterface $storage Storage driver
     */
    public function __construct(
        string $name,
        string $help,
        array $labelNames,
        array $quantiles,
        StorageDriverInterface $storage
    ) {
        parent::__construct($name, $help, $labelNames);
        $this->storage = $storage;
        // Default quantiles if none provided (0.5, 0.9, 0.99)
        $this->quantiles = !empty($quantiles) ? $quantiles : [
            0.5 => 0.05,
            0.9 => 0.01,
            0.99 => 0.001
        ];
    }
    
    /**
     * Observe a value in the summary
     *
     * @param float $value Value to observe
     * @param array<string, string> $labels Labels for this observation
     * @return void
     */
    public function observe(float $value, array $labels = []): void
    {
        $this->storage->observeSummary($this->name, $value, $labels, $this->quantiles);
    }
    
    /**
     * Get the sum of all observations for the given labels
     *
     * @param array<string, string> $labels Labels for this metric
     * @return float Sum of observations
     */
    public function getSum(array $labels = []): float
    {
        return $this->storage->getSummarySum($this->name, $labels);
    }
    
    /**
     * Get the count of observations for the given labels
     *
     * @param array<string, string> $labels Labels for this metric
     * @return int Count of observations
     */
    public function getCount(array $labels = []): int
    {
        return $this->storage->getSummaryCount($this->name, $labels);
    }
    
    /**
     * Get the configured quantiles for this summary
     *
     * @return array<float, float> Map of quantiles to their values
     */
    public function getQuantiles(): array
    {
        return $this->quantiles;
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
