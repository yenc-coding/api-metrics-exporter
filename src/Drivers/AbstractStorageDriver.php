<?php

declare(strict_types=1);

namespace SidcorpResource\ApiMetricsExporter\Drivers;

use SidcorpResource\ApiMetricsExporter\Contracts\StorageDriverInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Abstract base class for all storage drivers
 */
abstract class AbstractStorageDriver implements StorageDriverInterface
{
    /**
     * Logger instance
     *
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;

    /**
     * Registered counters
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $counters = [];

    /**
     * Registered histograms
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $histograms = [];

    /**
     * Default histogram buckets
     *
     * @var array<float>
     */
    protected array $defaultBuckets = [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1.0, 2.5, 5.0, 10.0];

    /**
     * Create a new driver instance
     *
     * @param LoggerInterface|null $logger Logger instance
     */
    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Register a new counter metric
     *
     * @param string $name Metric name
     * @param string $help Help text describing the metric
     * @param array<string> $labelNames Names of labels for this metric
     * @return void
     */
    /**
     * Register a new counter metric
     *
     * @param string $name Metric name
     * @param string $help Help text describing the metric
     * @param array<string> $labelNames Names of labels for this metric
     * @return void
     * @throws \InvalidArgumentException If the metric name is invalid
     */
    public function registerCounter(string $name, string $help, array $labelNames = []): void
    {
        $validatedName = $this->validateMetricName($name);
        $validatedLabelNames = $this->validateLabelNames($labelNames);
        
        // Add _total suffix if this is a counter without it (Prometheus standard for counters)
        if (!str_ends_with($validatedName, '_total')) {
            $message = sprintf(
                'Counter metric "%s" should have _total suffix according to Prometheus standards. Adding automatically.',
                $validatedName
            );
            $this->logger->info($message, ['name' => $validatedName]);
            $validatedName = $validatedName . '_total';
        }
        
        // Check if counter name contains _count or _sum which might indicate wrong metric type
        if (str_ends_with($validatedName, '_count_total') || str_ends_with($validatedName, '_sum_total')) {
            $message = sprintf(
                'Counter metric "%s" appears to contain _count or _sum which are reserved for histogram suffixes. Consider using a different name.',
                $validatedName
            );
            $this->logger->warning($message);
        }
        
        // Register the counter with validated name and label names
        $this->counters[$validatedName] = [
            'name' => $validatedName,
            'help' => $help,
            'labelNames' => $validatedLabelNames,
        ];
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
    /**
     * Register a new histogram metric
     *
     * @param string $name Metric name
     * @param string $help Help text describing the metric
     * @param array<string> $labelNames Names of labels for this metric
     * @param array<float> $buckets Histogram buckets
     * @return void
     * @throws \InvalidArgumentException If the metric name is invalid
     */
    public function registerHistogram(string $name, string $help, array $labelNames = [], array $buckets = []): void
    {
        $validatedName = $this->validateMetricName($name);
        $validatedLabelNames = $this->validateLabelNames($labelNames);
        
        // Ensure histogram metric name follows unit naming conventions
        // Common patterns for units include _seconds, _bytes, _ratio, etc.
        $commonUnitSuffixes = [
            '_seconds', '_bytes', '_ratio', '_percent', '_count', '_info',
            '_total', '_celsius', '_meters', '_volts', '_amperes', '_joules', '_grams'
        ];
        $hasUnitSuffix = false;
        foreach ($commonUnitSuffixes as $suffix) {
            if (str_ends_with($validatedName, $suffix)) {
                $hasUnitSuffix = true;
                break;
            }
        }
        
        if (!$hasUnitSuffix) {
            $message = sprintf(
                'Histogram metric "%s" should include a unit suffix (e.g., _seconds, _bytes) according to Prometheus standards.',
                $validatedName
            );
            $this->logger->warning($message);
        }
        
        // Check if 'le' is mistakenly included in the labels
        foreach ($validatedLabelNames as $index => $labelName) {
            if ($labelName === 'le') {
                $message = 'The "le" label is reserved for histogram buckets and should not be manually specified';
                $this->logger->error($message);
                throw new \InvalidArgumentException($message);
            }
        }
        
        // Validate that buckets are in ascending order (required by Prometheus)
        $bucketsCopy = $buckets ?: $this->defaultBuckets;
        
        // Remove duplicates and sort (Prometheus requires sorted, unique buckets)
        $bucketsCopy = array_unique($bucketsCopy);
        sort($bucketsCopy, SORT_NUMERIC);
        
        // Ensure +Inf bucket is included (required by Prometheus)
        if (!in_array(INF, $bucketsCopy)) {
            $bucketsCopy[] = INF;
        }
        
        $this->histograms[$validatedName] = [
            'name' => $validatedName,
            'help' => $help,
            'labelNames' => $validatedLabelNames,
            'buckets' => $bucketsCopy,
        ];
    }

    /**
     * Generate a storage key for labels
     *
     * @param string $name Metric name
     * @param array<string, string> $labels Label key-value pairs
     * @return string
     * @throws \InvalidArgumentException If any label name is invalid
     */
    protected function generateLabelKey(string $name, array $labels): string
    {
        if (empty($labels)) {
            // If no labels, don't return the name to avoid duplication in keys
            // The calling code should handle adding the name separately
            return '';
        }

        $labelString = [];
        foreach ($labels as $key => $value) {
            // Validate label name first
            $validatedKey = $this->validateLabelName($key);
            
            // Value must not be null
            if ($value === null) {
                $value = '';
            }
            
            // Convert value to string
            $value = (string)$value;
            
            // Escape all special characters in label values per Prometheus format:
            // - Backslash (\) must be escaped as \\
            // - Double-quote (") must be escaped as \"
            // - Line feed (\n) must be escaped as \n
            $value = str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '\\n', '\\r'], $value);
            $labelString[] = $validatedKey . '=' . '"' . $value . '"';
        }

        // Return just the label part without the metric name
        // All labels (including special ones like 'le' for histograms or 'quantile' for summaries)
        // must be included inside these curly braces according to Prometheus exposition format
        return '{' . implode(',', $labelString) . '}';
    }
    
    /**
     * Validate a single label name
     *
     * @param string $labelName Label name to validate
     * @return string Validated label name
     * @throws \InvalidArgumentException If the label name is invalid
     */
    protected function validateLabelName(string $labelName): string
    {
        // Correct dashes to underscores for strict compliance
        $correctedName = preg_replace('/[-\.]/', '_', $labelName);
        
        // Log warning if name was changed by dash/dot conversion
        if ($correctedName !== $labelName) {
            $message = sprintf(
                'Invalid label name: "%s". Label names must match [a-zA-Z_][a-zA-Z0-9_]*. Dashes and dots converted to underscores automatically.',
                $labelName
            );
            $this->logger->warning($message, ['label' => $labelName]);
        }
        
        // Label names can only have letters, numbers, and underscores
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $correctedName)) {
            $message = sprintf(
                'Invalid label name: "%s". Label names must match [a-zA-Z_][a-zA-Z0-9_]*. Attempting to fix automatically.',
                $labelName
            );
            $this->logger->warning($message, ['label' => $labelName]);
            
            // Try to fix the label name by replacing invalid characters
            $correctedName = preg_replace('/[^a-zA-Z0-9_]/', '_', $correctedName);
            
            // If it still doesn't start with a letter or underscore, prepend 'label_'
            if (!preg_match('/^[a-zA-Z_]/', $correctedName)) {
                $correctedName = 'label_' . $correctedName;
            }
        }
        
        // Labels starting with __ are reserved
        if (strpos($correctedName, '__') === 0) {
            $message = sprintf(
                'Invalid label name: "%s". Label names starting with __ are reserved for internal use. Fixing automatically.',
                $labelName
            );
            $this->logger->warning($message, ['label' => $labelName]);
            $correctedName = 'label_' . substr($correctedName, 2);
        }
        
        // __name__ is a special reserved label name
        if ($correctedName === '__name__') {
            $message = 'Label name "__name__" is reserved for internal use. Fixing automatically.';
            $this->logger->warning($message, ['label' => $labelName]);
            $correctedName = 'label_name';
        }
        
        return $correctedName;
    }

    /**
     * Format value for OpenMetrics output according to Prometheus exposition format
     * 
     * @param mixed $value The value to format
     * @return string The formatted value
     */
    protected function formatValue($value): string
    {
        // For null or empty values, return 0 since Prometheus doesn't support null metrics
        if ($value === null || $value === '') {
            return '0';
        }
        
        // Convert boolean to 1/0
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        // Special values handling
        if (is_string($value)) {
            $lowercaseValue = strtolower($value);
            if ($lowercaseValue === 'nan') {
                return 'NaN';
            } elseif ($lowercaseValue === 'inf' || $lowercaseValue === '+inf' || $lowercaseValue === 'infinity') {
                return '+Inf';
            } elseif ($lowercaseValue === '-inf' || $lowercaseValue === '-infinity') {
                return '-Inf';
            }
        }
        
        // Special infinity handling for float values
        if (is_float($value) && is_infinite($value)) {
            return $value > 0 ? '+Inf' : '-Inf';
        }

        // Format floats per Prometheus standard
        if (is_float($value)) {
            // Use scientific notation for very large or small numbers
            if (abs($value) > 1e21 || (abs($value) < 1e-6 && abs($value) > 0)) {
                return sprintf('%.16e', $value);
            }
            
            // Otherwise use decimal notation with trimmed trailing zeros
            return rtrim(rtrim(sprintf('%.16g', $value), '0'), '.');
        }

        // Integers and other values
        return (string)$value;
    }
    
    /**
     * Default implementation of flushMetrics (to be overridden by specific drivers)
     * 
     * @return bool True if successful, false otherwise
     */
    public function flushMetrics(): bool
    {
        // Reset internal arrays
        $this->counters = [];
        $this->histograms = [];
        
        // By default, just return true as the data is gone
        // More specific implementations should actually clear their storage
        return true;
    }

    /**
     * Validate that a metric name follows Prometheus naming conventions
     * 
     * @param string $name The metric name to validate
     * @return string The validated metric name or throws exception if invalid
     * @throws \InvalidArgumentException If the metric name is invalid
     */
    protected function validateMetricName(string $name): string
    {
        // Convert dashes and dots to underscores (strict compliance)
        $correctedName = preg_replace('/[-\.]/', '_', $name);
        
        // Log warning if name was changed by dash/dot conversion
        if ($correctedName !== $name) {
            $message = sprintf(
                'Invalid metric name: "%s". Metric names must match [a-zA-Z_][a-zA-Z0-9_]* with optional leading colon. Dashes and dots converted to underscores automatically.', 
                $name
            );
            $this->logger->warning($message, ['name' => $name]);
        }
        
        // Metric names must only have letters, numbers, and underscores
        // with optional colon at beginning only
        if (!preg_match('/^[a-zA-Z_](:)?[a-zA-Z0-9_]*$/', $correctedName)) {
            $message = sprintf(
                'Invalid metric name: "%s". Metric names must match [a-zA-Z_][a-zA-Z0-9_]* with optional leading colon. Attempting to fix automatically.', 
                $name
            );
            $this->logger->warning($message, ['name' => $name]);
            
            // Try to fix the name by replacing invalid characters
            $correctedName = preg_replace('/[^a-zA-Z0-9_:]/', '_', $correctedName);
            
            // If it still doesn't start with a letter or underscore, prepend 'metric_'
            if (!preg_match('/^[a-zA-Z_]/', $correctedName)) {
                $correctedName = 'metric_' . $correctedName;
            }
        }
        
        // Check if name contains a colon that's not at the beginning (namespace)
        if (substr_count($correctedName, ':') > 0 && strpos($correctedName, ':') !== 0) {
            $message = sprintf(
                'Invalid metric name: "%s". Colons are only allowed at the beginning for namespaces. Fixing automatically.', 
                $name
            );
            $this->logger->warning($message, ['name' => $name]);
            
            // Replace colons with underscores except at the beginning
            if (strpos($correctedName, ':') === 0) {
                $correctedName = ':' . str_replace(':', '_', substr($correctedName, 1));
            } else {
                $correctedName = str_replace(':', '_', $correctedName);
            }
        }
        
        // Check if the name encodes a date
        if (preg_match('/(20\d{2}[-_]?\d{2}[-_]?\d{2})/', $correctedName)) {
            $this->logger->warning(
                'Metric name "{name}" appears to contain a date. Dates should be in labels, not in metric names.', 
                ['name' => $name]
            );
        }
        
        return $correctedName;
    }

    /**
     * Normalize metric name to follow Prometheus conventions
     * 
     * @param string $name The metric name to normalize
     * @param string $metricType The type of metric ('counter', 'histogram', 'gauge', 'unique')
     * @return string The normalized metric name
     */
    protected function normalizeMetricName(string $name, string $metricType = 'counter'): string
    {
        // Remove any potential duplication patterns
        $cleanName = preg_replace('/(.+)\1+/', '$1', $name);
        
        // Add appropriate suffix for counters if missing
        if ($metricType === 'counter' && !str_ends_with($cleanName, '_total')) {
            $cleanName .= '_total';
        }
        
        // Remove any duplicate _total suffixes
        $cleanName = preg_replace('/_total_total$/', '_total', $cleanName);
        
        // Strip any unwanted suffixes often appended during processing
        $unwantedSuffixes = [':count', '_count', '_sum:'];
        foreach ($unwantedSuffixes as $suffix) {
            if (str_ends_with($cleanName, $suffix)) {
                $cleanName = substr($cleanName, 0, -strlen($suffix));
            }
        }
        
        // Ensure unique metric names have correct prefix and suffix
        if ($metricType === 'unique') {
            // Add unique_ prefix if not already present
            if (!str_starts_with($cleanName, 'unique_')) {
                $cleanName = 'unique_' . $cleanName;
            }
            
            // Unique metrics are counters, so they should have _total suffix
            if (!str_ends_with($cleanName, '_total')) {
                $cleanName .= '_total';
            }
        }
        
        return $cleanName;
    }

    /**
     * Validate label names according to Prometheus conventions
     * 
     * @param array<string> $labelNames Array of label names
     * @return array<string> Validated label names
     * @throws \InvalidArgumentException If any label name is invalid
     */
    protected function validateLabelNames(array $labelNames): array
    {
        $validatedLabels = [];
        
        foreach ($labelNames as $index => $labelName) {
            // Special case for 'le' (less than or equal) label used in histograms
            if ($labelName === 'le') {
                $validatedLabels[] = 'le';
                continue;
            }
            
            // Special case for 'quantile' label used in summaries
            if ($labelName === 'quantile') {
                $validatedLabels[] = 'quantile';
                continue;
            }
            
            // Use the single label validation method for consistency
            $validatedLabels[$index] = $this->validateLabelName($labelName);
        }
        
        return $validatedLabels;
    }
}
