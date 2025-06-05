<?php

declare(strict_types=1);

namespace SidcorpResource\ApiMetricsExporter\Drivers;

use Psr\Log\LoggerInterface;
use Exception;

/**
 * In-memory storage driver for metrics (suitable for testing and development)
 * 
 * This driver stores all metrics data in PHP arrays within the current request.
 * Data is lost when the request ends, making it suitable only for testing,
 * development, or when used as a fallback driver. It provides fast, zero-dependency
 * metrics storage with full OpenMetrics format export support.
 */
class InMemoryDriver extends AbstractStorageDriver
{
    /**
     * Counter metrics data
     *
     * @var array<string, int>
     */
    protected array $counterData = [];

    /**
     * Histogram metrics data
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $histogramData = [];

    /**
     * Unique tracking data
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $uniqueData = [];
    
    /**
     * Gauge metrics data
     * 
     * @var array<string, float>
     */
    protected array $gaugeData = [];
    
    /**
     * Summary metrics data
     * 
     * @var array<string, array<string, mixed>>
     */
    protected array $summaryData = [];

    /**
     * Create a new in-memory driver instance
     *
     * @param LoggerInterface|null $logger Logger instance
     */
    public function __construct(?LoggerInterface $logger = null)
    {
        parent::__construct($logger);
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
        try {
            // Normalize the metric name to match the registered name (with _total suffix)
            $normalizedName = $this->validateMetricName($name);
            if (!str_ends_with($normalizedName, '_total')) {
                $normalizedName = $normalizedName . '_total';
            }
            
            $labelPart = $this->generateLabelKey($normalizedName, $labels);
            $key = 'counter:' . $normalizedName . $labelPart;
            if (!isset($this->counterData[$key])) {
                $this->counterData[$key] = 0;
            }
            $this->counterData[$key] += $value;
        } catch (Exception $e) {
            $this->logger->error('Failed to increment counter', [
                'metric' => $name,
                'labels' => $labels,
                'exception' => $e->getMessage(),
            ]);
        }
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
        try {
            $labelPart = $this->generateLabelKey($name, $labels);
            $key = $name . $labelPart;
            
            if (!isset($this->histogramData[$key])) {
                $this->histogramData[$key] = [
                    'count' => 0,
                    'sum' => 0,
                    'buckets' => [],
                ];
                
                // Use provided buckets if available, otherwise use registered ones or defaults
                $bucketsToUse = !empty($buckets) 
                    ? $buckets 
                    : ($this->histograms[$name]['buckets'] ?? $this->defaultBuckets);
                
                // Unique buckets only, sorted
                $uniqueBuckets = array_unique($bucketsToUse);
                sort($uniqueBuckets, SORT_NUMERIC);
                
                foreach ($uniqueBuckets as $bucket) {
                    $this->histogramData[$key]['buckets'][$this->formatValue($bucket)] = 0;
                }
                $this->histogramData[$key]['buckets']['+Inf'] = 0;
            }
            
            // Increment the count
            $this->histogramData[$key]['count']++;
            
            // Add to sum
            $this->histogramData[$key]['sum'] += $value;
            
            // Increment appropriate buckets
            $buckets = $this->histograms[$name]['buckets'] ?? $this->defaultBuckets;
            foreach ($buckets as $bucket) {
                if ($value <= $bucket) {
                    $this->histogramData[$key]['buckets'][$this->formatValue($bucket)]++;
                }
            }
            // Always increment the +Inf bucket
            $this->histogramData[$key]['buckets']['+Inf']++;
        } catch (Exception $e) {
            $this->logger->error('Failed to observe histogram', [
                'metric' => $name,
                'value' => $value,
                'labels' => $labels,
                'exception' => $e->getMessage(),
            ]);
        }
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
        try {
            $today = date('Y-m-d');
            // Add date as a label rather than part of the key
            $labelsWithDate = array_merge($labels, ['date' => $today]);
            
            // Normalize the metric name to follow Prometheus conventions
            $normalizedName = $this->normalizeMetricName($name, 'unique');
            
            $baseKey = 'unique:' . $normalizedName;
            $labelKey = $this->generateLabelKey('', $labelsWithDate);
            $key = $baseKey . $labelKey;
            
            if (!isset($this->uniqueData[$key])) {
                $this->uniqueData[$key] = [
                    'identifiers' => [],
                    'count' => 0,
                ];
            }
            
            // If this is new, add it and increment counter
            if (!in_array($identifier, $this->uniqueData[$key]['identifiers'])) {
                $this->uniqueData[$key]['identifiers'][] = $identifier;
                $this->uniqueData[$key]['count']++;
            }
        } catch (Exception $e) {
            $this->logger->error('Failed to track unique', [
                'metric' => $name,
                'identifier' => $identifier,
                'labels' => $labels,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get all metrics in Prometheus exposition format
     *
     * @return string Metrics in OpenMetrics text format
     */
    public function getMetrics(): string
    {
        $output = [];

        try {
            // Process counters
            foreach ($this->counters as $name => $meta) {
                $output[] = "# HELP {$name} {$meta['help']}";
                $output[] = "# TYPE {$name} counter";
                
                // Find all counter keys matching this metric
                $prefix = 'counter:' . $name;
                $foundMetrics = false;
                foreach ($this->counterData as $key => $value) {
                    if (strpos($key, $prefix) === 0) {
                        $labelPart = str_replace('counter:' . $name, '', $key);
                        // If no labels, use empty braces for consistency
                        if (empty($labelPart)) {
                            $labelPart = '';
                        }
                        $output[] = "{$name}{$labelPart} {$this->formatValue($value)}";
                        $foundMetrics = true;
                    }
                }
                
                // If no data found for this counter, output with value 0
                if (!$foundMetrics) {
                    $output[] = "{$name} 0";
                }
            }
            
            // Process histograms
            foreach ($this->histograms as $name => $meta) {
                $output[] = "# HELP {$name} {$meta['help']}";
                $output[] = "# TYPE {$name} histogram";
                
                // Group by label set
                $groups = [];
                foreach ($this->histogramData as $key => $data) {
                    if (strpos($key, $name) === 0) {
                        $labelPart = str_replace($name, '', $key);
                        $groups[$labelPart] = $data;
                    }
                }
                
                // Output for each group
                foreach ($groups as $labelPart => $data) {
                    $count = $data['count'] ?? 0;
                    $sum = $data['sum'] ?? 0;
                    $buckets = $data['buckets'] ?? [];
                    
                    // Ensure we have all buckets in the right order
                    $bucketValues = $meta['buckets'] ?? $this->defaultBuckets;
                    $cumulativeCount = 0;
                    
                    // Get unique, sorted buckets
                    $uniqueBuckets = array_unique($bucketValues);
                    sort($uniqueBuckets, SORT_NUMERIC);
                    $processedBuckets = []; // Track processed buckets to prevent duplicates
                    
                    foreach ($uniqueBuckets as $bucket) {
                        $formattedBucket = $this->formatValue($bucket);
                        // Skip if we've already processed this bucket value
                        if (in_array($formattedBucket, $processedBuckets)) {
                            continue;
                        }
                        $processedBuckets[] = $formattedBucket;
                        
                        $bucketCount = $buckets[$formattedBucket] ?? 0;
                        $cumulativeCount += (int)$bucketCount;
                        // Extract the label content without braces
                        $labelsContent = substr($labelPart, 1, -1);
                        // If there are existing labels, add a comma before the le label
                        $leLabel = empty($labelsContent) ? "le=\"{$formattedBucket}\"" : "{$labelsContent},le=\"{$formattedBucket}\"";
                        $output[] = "{$name}_bucket{" . $leLabel . "} {$cumulativeCount}";
                    }
                    
                    // +Inf bucket - only add if not already processed
                    if (!in_array('+Inf', $processedBuckets)) {
                        // Extract the label content without braces
                        $labelsContent = substr($labelPart, 1, -1);
                        // If there are existing labels, add a comma before the le label
                        $leLabel = empty($labelsContent) ? "le=\"+Inf\"" : "{$labelsContent},le=\"+Inf\"";
                        $output[] = "{$name}_bucket{" . $leLabel . "} {$count}";
                    }
                    
                    // Sum and count
                    $output[] = "{$name}_sum{$labelPart} {$this->formatValue($sum)}";
                    $output[] = "{$name}_count{$labelPart} {$this->formatValue($count)}";
                }
            }
            
            // Process unique metrics
            $uniqueMetrics = [];
            foreach ($this->uniqueData as $key => $data) {
                if (strpos($key, 'unique:') === 0) {
                    $parts = explode(':', $key, 2); // Only split on the first ':'
                    if (count($parts) >= 2) {
                        $remaining = $parts[1];
                        
                        // Find where the metric name ends and labels begin
                        if (strpos($remaining, '{') !== false) {
                            list($metricName, $labelPart) = explode('{', $remaining, 2);
                            $labelPart = '{' . $labelPart; // Add back the opening brace
                        } else {
                            $metricName = $remaining;
                            $labelPart = '';
                        }
                        
                        // Ensure metric name has _total suffix
                        if (substr($metricName, -6) !== '_total') {
                            $metricName .= '_total';
                        }
                        
                        // Add unique_ prefix if not already present
                        if (substr($metricName, 0, 7) !== 'unique_') {
                            $metricName = 'unique_' . $metricName;
                        }
                        
                        // Register the metric type once
                        if (!isset($uniqueMetrics[$metricName])) {
                            $uniqueMetrics[$metricName] = true;
                            $baseName = str_replace(['unique_', '_total'], '', $metricName);
                            $output[] = "# HELP {$metricName} Unique {$baseName} count";
                            $output[] = "# TYPE {$metricName} counter";
                        }
                        
                        // Output the metric line
                        $output[] = "{$metricName}{$labelPart} {$this->formatValue($data['count'])}";
                    }
                }
            }
        } catch (Exception $e) {
            $this->logger->error('Failed to get metrics', [
                'exception' => $e->getMessage(),
            ]);
            return "# Error generating metrics: {$e->getMessage()}\n";
        }
        
        return implode("\n", $output) . "\n";
    }
    
    /**
     * Flush all metrics data by resetting the internal arrays
     * 
     * @return bool True if successful, false otherwise
     */
    public function flushMetrics(): bool
    {
        try {
            // Clear all metrics data
            $this->counterData = [];
            $this->histogramData = [];
            $this->uniqueData = [];
            $this->gaugeData = [];
            $this->summaryData = [];
            
            // Call parent to reset the internal metadata arrays
            parent::flushMetrics();
            
            $this->logger->info('Successfully flushed metrics data from InMemoryDriver');
            return true;
        } catch (Exception $e) {
            $this->logger->error('Failed to flush metrics from InMemoryDriver', [
                'exception' => $e->getMessage(),
            ]);
            return false;
        }
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
        try {
            // Normalize the metric name to match the registered name (with _total suffix)
            $normalizedName = $this->validateMetricName($name);
            if (!str_ends_with($normalizedName, '_total')) {
                $normalizedName = $normalizedName . '_total';
            }
            
            $labelPart = $this->generateLabelKey($normalizedName, $labels);
            $key = 'counter:' . $normalizedName . $labelPart;
            
            return $this->counterData[$key] ?? 0;
        } catch (Exception $e) {
            $this->logger->error('Failed to get counter value', [
                'metric' => $name,
                'labels' => $labels,
                'exception' => $e->getMessage(),
            ]);
            return 0;
        }
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
        try {
            $labelPart = $this->generateLabelKey($name, $labels);
            $key = $name . $labelPart;
            
            return $this->histogramData[$key]['sum'] ?? 0.0;
        } catch (Exception $e) {
            $this->logger->error('Failed to get histogram sum', [
                'metric' => $name,
                'labels' => $labels,
                'exception' => $e->getMessage(),
            ]);
            return 0.0;
        }
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
        try {
            $labelPart = $this->generateLabelKey($name, $labels);
            $key = $name . $labelPart;
            
            return $this->histogramData[$key]['count'] ?? 0;
        } catch (Exception $e) {
            $this->logger->error('Failed to get histogram count', [
                'metric' => $name,
                'labels' => $labels,
                'exception' => $e->getMessage(),
            ]);
            return 0;
        }
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
        try {
            $normalizedName = $this->validateMetricName($name);
            $labelPart = $this->generateLabelKey($normalizedName, $labels);
            $key = 'gauge:' . $normalizedName . $labelPart;
            
            $this->gaugeData[$key] = $value;
        } catch (Exception $e) {
            $this->logger->error('Failed to set gauge value', [
                'metric' => $name,
                'value' => $value,
                'labels' => $labels,
                'exception' => $e->getMessage(),
            ]);
        }
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
        try {
            $normalizedName = $this->validateMetricName($name);
            $labelPart = $this->generateLabelKey($normalizedName, $labels);
            $key = 'gauge:' . $normalizedName . $labelPart;
            
            if (!isset($this->gaugeData[$key])) {
                $this->gaugeData[$key] = 0.0;
            }
            
            $this->gaugeData[$key] += $value;
        } catch (Exception $e) {
            $this->logger->error('Failed to increment gauge', [
                'metric' => $name,
                'value' => $value,
                'labels' => $labels,
                'exception' => $e->getMessage(),
            ]);
        }
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
        try {
            $normalizedName = $this->validateMetricName($name);
            $labelPart = $this->generateLabelKey($normalizedName, $labels);
            $key = 'gauge:' . $normalizedName . $labelPart;
            
            if (!isset($this->gaugeData[$key])) {
                $this->gaugeData[$key] = 0.0;
            }
            
            $this->gaugeData[$key] -= $value;
        } catch (Exception $e) {
            $this->logger->error('Failed to decrement gauge', [
                'metric' => $name,
                'value' => $value,
                'labels' => $labels,
                'exception' => $e->getMessage(),
            ]);
        }
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
        try {
            $normalizedName = $this->validateMetricName($name);
            $labelPart = $this->generateLabelKey($normalizedName, $labels);
            $key = 'gauge:' . $normalizedName . $labelPart;
            
            return $this->gaugeData[$key] ?? 0.0;
        } catch (Exception $e) {
            $this->logger->error('Failed to get gauge value', [
                'metric' => $name,
                'labels' => $labels,
                'exception' => $e->getMessage(),
            ]);
            return 0.0;
        }
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
        try {
            $labelPart = $this->generateLabelKey($name, $labels);
            $key = $name . $labelPart;
            
            if (!isset($this->summaryData[$key])) {
                $this->summaryData[$key] = [
                    'count' => 0,
                    'sum' => 0,
                    'values' => [],
                    'quantiles' => $quantiles,
                ];
            }
            
            // Increment count and sum
            $this->summaryData[$key]['count']++;
            $this->summaryData[$key]['sum'] += $value;
            
            // Store value for quantile calculation
            $this->summaryData[$key]['values'][] = $value;
        } catch (Exception $e) {
            $this->logger->error('Failed to observe summary', [
                'metric' => $name,
                'value' => $value,
                'labels' => $labels,
                'exception' => $e->getMessage(),
            ]);
        }
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
        try {
            $labelPart = $this->generateLabelKey($name, $labels);
            $key = $name . $labelPart;
            
            return $this->summaryData[$key]['sum'] ?? 0.0;
        } catch (Exception $e) {
            $this->logger->error('Failed to get summary sum', [
                'metric' => $name,
                'labels' => $labels,
                'exception' => $e->getMessage(),
            ]);
            return 0.0;
        }
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
        try {
            $labelPart = $this->generateLabelKey($name, $labels);
            $key = $name . $labelPart;
            
            return $this->summaryData[$key]['count'] ?? 0;
        } catch (Exception $e) {
            $this->logger->error('Failed to get summary count', [
                'metric' => $name,
                'labels' => $labels,
                'exception' => $e->getMessage(),
            ]);
            return 0;
        }
    }
}
