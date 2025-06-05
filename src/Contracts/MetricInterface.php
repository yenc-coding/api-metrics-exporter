<?php

declare(strict_types=1);

namespace SidcorpResource\ApiMetricsExporter\Contracts;

/**
 * Base interface for all metric types
 */
interface MetricInterface
{
    /**
     * Get the metric name
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Get the metric help text
     *
     * @return string
     */
    public function getHelp(): string;
    
    /**
     * Get the metric label names
     *
     * @return array<string>
     */
    public function getLabelNames(): array;
    
    /**
     * Format this metric for Prometheus exposition format
     *
     * @return string
     */
    public function getPrometheusMetricString(): string;
}
