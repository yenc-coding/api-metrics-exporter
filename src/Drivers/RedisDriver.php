<?php

declare(strict_types=1);

namespace SidcorpResource\ApiMetricsExporter\Drivers;

use SidcorpResource\ApiMetricsExporter\Exceptions\StorageDriverException;
use Illuminate\Redis\Connections\Connection as RedisConnection;
use Illuminate\Support\Facades\Redis;
use Psr\Log\LoggerInterface;
use Exception;

/**
 * Redis storage driver for metrics (phpredis only)
 */
class RedisDriver extends AbstractStorageDriver
{
    /**
     * Redis connection
     *
     * @var RedisConnection
     */
    protected $redis;

    /**
     * Redis key prefix
     */
    protected string $prefix;

    /**
     * Redis TTL for unique tracking (in seconds)
     */
    protected int $ttl;

    /**
     * Create a new Redis driver instance
     */
    public function __construct(
        string $connection = 'default',
        string $prefix = 'api_metrics:',
        int $ttl = 86400,
        ?LoggerInterface $logger = null,
        ?string $clientType = null
    ) {
        parent::__construct($logger);
        try {
            $connectionInstance = Redis::connection($connection);

            // Determine which Redis client is being used
            $redisClient = $clientType ?: config('api-metrics.storage.redis.client', 'phpredis');

            $rawClient = $connectionInstance->client();

            if ($rawClient instanceof Redis) {
                if (defined('\Redis::OPT_PREFIX')) {
                    $rawClient->setOption(Redis::OPT_PREFIX, '');
                } else {
                    $rawClient->setOption(2, '');
                }
            } elseif ( $rawClient instanceof \Predis\Client) {
                $options = $rawClient->getOptions();
                if (isset($options->prefix)) {
                    $options->prefix->setPrefix('');
                }
            }
            $this->redis = $rawClient;


            if (!empty($prefix) && substr($prefix, -1) !== ':') {
                $prefix .= ':';
            }

            $this->prefix = $prefix;
            $this->ttl = $ttl;
        } catch (Exception $e) {
            throw new StorageDriverException('Failed to connect to Redis: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Increment a counter metric
     */
    public function incrementCounter(string $name, array $labels = [], int $value = 1): void
    {
        try {
            // Normalize the metric name to match the registered name (with _total suffix)
            $normalizedName = $this->validateMetricName($name);
            if (!str_ends_with($normalizedName, '_total')) {
                $normalizedName = $normalizedName . '_total';
            }

            $labelKey = $this->generateLabelKey($normalizedName, $labels);
            $key = $this->prefix . 'counter:' . $normalizedName . $labelKey;
            $this->redis->incrby($key, $value);
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
     */
    public function observeHistogram(string $name, float $value, array $labels = [], array $buckets = []): void
    {
        try {
            $labelKey = $this->generateLabelKey($name, $labels);
            $baseKey = $this->prefix . 'histogram:' . $name . $labelKey;

            $this->redis->incr($baseKey . ':count');

            $currentSum = floatval($this->redis->get($baseKey . ':sum') ?: 0);
            $newSum = $currentSum + $value;
            $this->redis->set($baseKey . ':sum', number_format($newSum, 10, '.', ''));

            // Use provided buckets if available, otherwise use registered ones or defaults
            $bucketsToUse = !empty($buckets)
                ? $buckets
                : ($this->histograms[$name]['buckets'] ?? $this->defaultBuckets);

            foreach ($bucketsToUse as $bucket) {
                if ($value <= $bucket) {
                    $bucketKey = $baseKey . ':bucket:' . $this->formatValue($bucket);
                    $this->redis->incr($bucketKey);
                }
            }
            $this->redis->incr($baseKey . ':bucket:+Inf');
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
     */
    public function trackUnique(string $name, string $identifier, array $labels = []): void
    {
        try {
            $today = date('Y-m-d');

            // Normalize the metric name for unique tracking (adds unique_ prefix and _total suffix)
            $name = $this->normalizeMetricName($name, 'unique');

            // Add date as a label rather than part of the key
            $labelsWithDate = array_merge($labels, ['date' => $today]);
            $labelKey = $this->generateLabelKey($name, $labelsWithDate);
            $baseKey = $this->prefix . 'unique:' . $name;

            $setKey = $baseKey . $labelKey;
            $countKey = $baseKey . ':count' . $labelKey;

            $result = $this->redis->sadd($setKey, $identifier);
            $this->redis->expire($setKey, $this->ttl);

            if ($result === 1) {
                $this->redis->incr($countKey);
                $this->redis->expire($countKey, $this->ttl);
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
     */
    public function getMetrics(): string
    {
        $output = [];

        try {
            // Process counters
            foreach ($this->counters as $name => $meta) {
                $output[] = "# HELP {$name} {$meta['help']}";
                $output[] = "# TYPE {$name} counter";

                $pattern = $this->prefix . 'counter:' . $name . '*';
                $keys = $this->redis->keys($pattern);

                foreach ($keys as $key) {
                    $value = $this->redis->get($key);
                    $labelPart = $this->extractLabelsFromKey($key, $name);

                    if ($value === null || $value === '') {
                        $value = 0;
                    }

                    $output[] = "{$name}{$labelPart} {$this->formatValue($value)}";
                }
            }

            // Process histograms
            foreach ($this->histograms as $name => $meta) {
                $output[] = "# HELP {$name} {$meta['help']}";
                $output[] = "# TYPE {$name} histogram";

                $pattern = $this->prefix . 'histogram:' . $name . '*';
                $keys = $this->redis->keys($pattern);

                // Group keys by label set
                $groupedKeys = [];
                foreach ($keys as $key) {
                    if (strpos($key, ':count') !== false) {
                        $labelPart = $this->extractLabelsFromKey($key, $name);
                        $groupedKeys[$labelPart][] = $key;
                    } elseif (strpos($key, ':sum') !== false) {
                        $labelPart = $this->extractLabelsFromKey($key, $name);
                        $groupedKeys[$labelPart][] = $key;
                    } elseif (strpos($key, ':bucket:') !== false) {
                        $labelPart = $this->extractLabelsFromKey($key, $name);
                        $groupedKeys[$labelPart][] = $key;
                    }
                }

                // Process each label set
                foreach ($groupedKeys as $labelPart => $keys) {
                    $baseKey = $this->prefix . 'histogram:' . $name . $labelPart;

                    $countKey = $baseKey . ':count';
                    $sumKey = $baseKey . ':sum';
                    $count = $this->redis->get($countKey) ?: 0;
                    $sum = $this->redis->get($sumKey) ?: 0;

                    $buckets = $meta['buckets'] ?? $this->defaultBuckets;
                    $cumulativeCount = 0;

                    // Get unique, sorted buckets
                    $uniqueBuckets = array_unique($buckets);
                    sort($uniqueBuckets, SORT_NUMERIC);
                    $processedBuckets = []; // Track processed buckets to prevent duplicates

                    foreach ($uniqueBuckets as $bucket) {
                        $formattedBucket = $this->formatValue($bucket);
                        // Skip if we've already processed this bucket value
                        if (in_array($formattedBucket, $processedBuckets)) {
                            continue;
                        }
                        $processedBuckets[] = $formattedBucket;

                        $bucketKey = $baseKey . ':bucket:' . $formattedBucket;
                        $bucketCount = (int)($this->redis->get($bucketKey) ?: 0);
                        $cumulativeCount += $bucketCount;

                        $cleanMetricName = $name . "_bucket";
                        // Extract the label content without braces
                        $labelsContent = substr($labelPart, 1, -1);
                        // If there are existing labels, add a comma before the le label
                        $leLabel = empty($labelsContent) ? "le=\"{$formattedBucket}\"" : "{$labelsContent},le=\"{$formattedBucket}\"";
                        $output[] = "{$cleanMetricName}{" . $leLabel . "} {$cumulativeCount}";
                    }

                    // Add the +Inf bucket ONLY if not already processed
                    if (!in_array('+Inf', $processedBuckets)) {
                        // For +Inf bucket, we use the count value which is the total count
                        // Extract the label content without braces
                        $labelsContent = substr($labelPart, 1, -1);
                        // If there are existing labels, add a comma before the le label
                        $leLabel = empty($labelsContent) ? "le=\"+Inf\"" : "{$labelsContent},le=\"+Inf\"";
                        $output[] = "{$name}_bucket{" . $leLabel . "} {$count}";
                    }

                    $output[] = "{$name}_sum{$labelPart} {$this->formatValue($sum)}";
                    $output[] = "{$name}_count{$labelPart} {$this->formatValue($count)}";
                }
            }

            // Process unique metrics
            $pattern = $this->prefix . 'unique:*:count*';
            $keys = $this->redis->keys($pattern);

            $uniqueMetrics = [];
            foreach ($keys as $key) {
                $parts = explode(':', $key);
                if (count($parts) >= 4) {
                    $name = $parts[2];

                    if (!isset($uniqueMetrics[$name])) {
                        $uniqueMetrics[$name] = [
                            'name' => $name,
                            'help' => "Unique {$name} count",
                        ];
                    }
                }
            }

            foreach ($uniqueMetrics as $name => $meta) {
                // The name extracted from the key is already normalized if stored by trackUnique
                // Check if it already has the unique_ prefix, if not, normalize it
                $normalizedName = str_starts_with($name, 'unique_') ? $name : $this->normalizeMetricName($name, 'unique');

                $output[] = "# HELP {$normalizedName} {$meta['help']}";
                $output[] = "# TYPE {$normalizedName} counter";

                // Get all keys for this unique metric (date is now in the label)
                $pattern = $this->prefix . "unique:{$name}:count*";
                $keys = $this->redis->keys($pattern);

                foreach ($keys as $key) {
                    $value = $this->redis->get($key);
                    // Extract just the label part by removing the known prefix and suffix
                    $labelPart = $this->extractUniqueLabelsFromKey($key, $name);
                    if ($value === null || $value === '') {
                        $value = 0;
                    }
                    $output[] = "{$normalizedName}{$labelPart} {$this->formatValue($value)}";
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
     * Extract label part from a Redis key for unique metrics
     */
    protected function extractUniqueLabelsFromKey(string $key, string $metricName): string
    {
        // Remove our application prefix
        if (!empty($this->prefix) && str_starts_with($key, $this->prefix)) {
            $key = substr($key, strlen($this->prefix));
        }

        // For unique metrics, the key structure is: unique:{metricName}:count{labelPart}
        $expectedPrefix = "unique:{$metricName}:count";
        if (str_starts_with($key, $expectedPrefix)) {
            $labelPart = substr($key, strlen($expectedPrefix));
            return $labelPart ?: '{}'; // Return empty braces if no labels
        }

        return '{}'; // Fallback to empty labels
    }

    /**
     * Extract label part from a Redis key
     */
    protected function extractLabelsFromKey(string $key, string $metricName): string
    {
        // Remove our application prefix
        if (!empty($this->prefix) && str_starts_with($key, $this->prefix)) {
            $key = substr($key, strlen($this->prefix));
        }

        // Remove metric type prefixes
        if (str_starts_with($key, 'counter:')) {
            $key = substr($key, strlen('counter:'));
        } elseif (str_starts_with($key, 'histogram:')) {
            $key = substr($key, strlen('histogram:'));
        } elseif (str_starts_with($key, 'unique:')) {
            $key = substr($key, strlen('unique:'));
        }

        // Remove the actual metric name
        if (!empty($metricName) && str_starts_with($key, $metricName)) {
            $key = substr($key, strlen($metricName));
        }

        // Handle special suffixes
        if (str_ends_with($key, ':count')) {
            $key = substr($key, 0, -6);
        } elseif (str_ends_with($key, ':sum')) {
            $key = substr($key, 0, -4);
        } elseif (strpos($key, ':bucket:') !== false) {
            $key = substr($key, 0, strpos($key, ':bucket:'));
        }

        return $key;
    }

    /**
     * Flush all metrics data from Redis
     */
    public function flushMetrics(): bool
    {
        try {
            $pattern = $this->prefix . '*';
            $keys = $this->redis->keys($pattern);

            if (empty($keys)) {
                return true;
            }

            $this->redis->del(...$keys);

            parent::flushMetrics();

            $this->logger->info('Successfully flushed metrics data from Redis');
            return true;
        } catch (Exception $e) {
            $this->logger->error('Failed to flush metrics from Redis', [
                'exception' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get the current value of a counter metric
     */
    public function getCounterValue(string $name, array $labels = []): int
    {
        try {
            // Normalize the metric name to match the registered name (with _total suffix)
            $normalizedName = $this->validateMetricName($name);
            if (!str_ends_with($normalizedName, '_total')) {
                $normalizedName = $normalizedName . '_total';
            }

            $labelKey = $this->generateLabelKey($normalizedName, $labels);
            $key = $this->prefix . 'counter:' . $normalizedName . $labelKey;

            $value = $this->redis->get($key);
            return is_numeric($value) ? (int) $value : 0;
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
     */
    public function getHistogramSum(string $name, array $labels = []): float
    {
        try {
            $labelKey = $this->generateLabelKey($name, $labels);
            $sumKey = $this->prefix . 'histogram:' . $name . $labelKey . ':sum';

            $value = $this->redis->get($sumKey);
            return is_numeric($value) ? (float) $value : 0.0;
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
     */
    public function getHistogramCount(string $name, array $labels = []): int
    {
        try {
            $labelKey = $this->generateLabelKey($name, $labels);
            $countKey = $this->prefix . 'histogram:' . $name . $labelKey . ':count';

            $value = $this->redis->get($countKey);
            return is_numeric($value) ? (int) $value : 0;
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
     */
    public function setGauge(string $name, float $value, array $labels = []): void
    {
        try {
            $labelKey = $this->generateLabelKey($name, $labels);
            $key = $this->prefix . 'gauge:' . $name . $labelKey;

            $this->redis->set($key, number_format($value, 10, '.', ''));
        } catch (Exception $e) {
            $this->logger->error('Failed to set gauge', [
                'metric' => $name,
                'value' => $value,
                'labels' => $labels,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Increment a gauge metric
     */
    public function incrementGauge(string $name, float $value, array $labels = []): void
    {
        try {
            $labelKey = $this->generateLabelKey($name, $labels);
            $key = $this->prefix . 'gauge:' . $name . $labelKey;

            $currentValue = (float)($this->redis->get($key) ?: 0);
            $newValue = $currentValue + $value;

            $this->redis->set($key, number_format($newValue, 10, '.', ''));
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
     */
    public function decrementGauge(string $name, float $value, array $labels = []): void
    {
        try {
            $labelKey = $this->generateLabelKey($name, $labels);
            $key = $this->prefix . 'gauge:' . $name . $labelKey;

            $currentValue = (float)($this->redis->get($key) ?: 0);
            $newValue = $currentValue - $value;

            $this->redis->set($key, number_format($newValue, 10, '.', ''));
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
     */
    public function getGaugeValue(string $name, array $labels = []): float
    {
        try {
            $labelKey = $this->generateLabelKey($name, $labels);
            $key = $this->prefix . 'gauge:' . $name . $labelKey;

            $value = $this->redis->get($key);
            return is_numeric($value) ? (float) $value : 0.0;
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
     */
    public function observeSummary(string $name, float $value, array $labels = [], array $quantiles = []): void
    {
        try {
            $labelKey = $this->generateLabelKey($name, $labels);
            $baseKey = $this->prefix . 'summary:' . $name . $labelKey;

            // Increment the count
            $this->redis->incr($baseKey . ':count');

            // Update the sum
            $currentSum = floatval($this->redis->get($baseKey . ':sum') ?: 0);
            $newSum = $currentSum + $value;
            $this->redis->set($baseKey . ':sum', number_format($newSum, 10, '.', ''));

            // Store the observation for quantile calculations
            // This is a simplified implementation and might need more sophisticated quantile estimation
            // for production use cases with high cardinality
            $this->redis->zadd($baseKey . ':observations', [$value => time()]);

            // Limit the size of the sorted set to prevent memory issues
            $this->redis->zremrangebyrank($baseKey . ':observations', 0, -1001);
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
     */
    public function getSummarySum(string $name, array $labels = []): float
    {
        try {
            $labelKey = $this->generateLabelKey($name, $labels);
            $sumKey = $this->prefix . 'summary:' . $name . $labelKey . ':sum';

            $value = $this->redis->get($sumKey);
            return is_numeric($value) ? (float) $value : 0.0;
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
     */
    public function getSummaryCount(string $name, array $labels = []): int
    {
        try {
            $labelKey = $this->generateLabelKey($name, $labels);
            $countKey = $this->prefix . 'summary:' . $name . $labelKey . ':count';

            $value = $this->redis->get($countKey);
            return is_numeric($value) ? (int) $value : 0;
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
