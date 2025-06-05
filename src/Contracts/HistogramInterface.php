<?php

declare(strict_types=1);

namespace SidcorpResource\ApiMetricsExporter\Contracts;

/**
 * Interface for histogram metrics
 */
interface HistogramInterface extends MetricInterface
{
    /**
     * Observe a value in the histogram
     *
     * @param float $value Value to observe
     * @param array<string, string> $labels Labels for this observation
     * @return void
     */
    public function observe(float $value, array $labels = []): void;
    
    /**
     * Get histogram buckets
     *
     * @return array<float>
     */
    public function getBuckets(): array;
    
    /**
     * Get the sum of all observations for the given labels
     *
     * @param array<string, string> $labels Labels for this metric
     * @return float Sum of observations
     */
    public function getSum(array $labels = []): float;
    
    /**
     * Get the count of observations for the given labels
     *
     * @param array<string, string> $labels Labels for this metric
     * @return int Count of observations
     */
    public function getCount(array $labels = []): int;
}
