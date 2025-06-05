<?php

declare(strict_types=1);

namespace SidcorpResource\ApiMetricsExporter\Contracts;

/**
 * Interface for gauge metrics
 */
interface GaugeInterface extends MetricInterface
{
    /**
     * Set the gauge value
     *
     * @param float $value Value to set
     * @param array<string, string> $labels Labels for this metric
     * @return void
     */
    public function set(float $value, array $labels = []): void;
    
    /**
     * Increment the gauge value
     *
     * @param float $value Value to increment by
     * @param array<string, string> $labels Labels for this metric
     * @return void
     */
    public function increment(float $value, array $labels = []): void;
    
    /**
     * Decrement the gauge value
     *
     * @param float $value Value to decrement by
     * @param array<string, string> $labels Labels for this metric
     * @return void
     */
    public function decrement(float $value, array $labels = []): void;
    
    /**
     * Get the current gauge value for the given labels
     *
     * @param array<string, string> $labels Labels for this metric
     * @return float Current gauge value
     */
    public function getValue(array $labels = []): float;
}
