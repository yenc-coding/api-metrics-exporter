<?php

declare(strict_types=1);

namespace SidcorpResource\ApiMetricsExporter\Contracts;

/**
 * Interface for counter metrics
 */
interface CounterInterface extends MetricInterface
{
    /**
     * Increment the counter value
     *
     * @param array<string, string> $labels Labels for this metric observation
     * @param int $value Value to increment by
     * @return void
     */
    public function increment(array $labels = [], int $value = 1): void;
    
    /**
     * Get current counter value for given labels
     *
     * @param array<string, string> $labels Labels for this metric
     * @return int Current counter value
     */
    public function getValue(array $labels = []): int;
}
