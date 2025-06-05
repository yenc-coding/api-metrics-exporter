<?php

declare(strict_types=1);

namespace SidcorpResource\ApiMetricsExporter\Metrics;

use SidcorpResource\ApiMetricsExporter\Contracts\MetricInterface;

/**
 * Abstract base class for all metrics
 */
abstract class AbstractMetric implements MetricInterface
{
    /**
     * Metric name
     *
     * @var string
     */
    protected string $name;

    /**
     * Help text describing the metric
     *
     * @var string
     */
    protected string $help;

    /**
     * Names of labels supported by this metric
     *
     * @var array<string>
     */
    protected array $labelNames;
    
    /**
     * Create a new metric instance
     *
     * @param string $name Metric name
     * @param string $help Help text describing the metric
     * @param array<string> $labelNames Names of labels for this metric
     */
    public function __construct(string $name, string $help, array $labelNames = [])
    {
        $this->name = $name;
        $this->help = $help;
        $this->labelNames = $labelNames;
    }
    
    /**
     * Get the metric name
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }
    
    /**
     * Get the metric help text
     *
     * @return string
     */
    public function getHelp(): string
    {
        return $this->help;
    }
    
    /**
     * Get the metric label names
     *
     * @return array<string>
     */
    public function getLabelNames(): array
    {
        return $this->labelNames;
    }
    
    /**
     * Format a label set for Prometheus output
     *
     * @param array<string, string> $labels Labels to format
     * @return string Formatted labels string or empty string if no labels
     */
    protected function formatLabels(array $labels): string
    {
        if (empty($labels)) {
            return '';
        }
        
        $parts = [];
        
        foreach ($labels as $name => $value) {
            // Only include labels that are defined for this metric
            if (in_array($name, $this->labelNames)) {
                // Escape quotes in label values according to Prometheus format
                $value = str_replace('"', '\\"', $value);
                $parts[] = $name . '="' . $value . '"';
            }
        }
        
        if (empty($parts)) {
            return '';
        }
        
        return '{' . implode(',', $parts) . '}';
    }
    
    /**
     * Format this metric for Prometheus exposition format
     *
     * @return string
     */
    abstract public function getPrometheusMetricString(): string;
}
