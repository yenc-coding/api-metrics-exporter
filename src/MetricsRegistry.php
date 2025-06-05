<?php

declare(strict_types=1);

namespace SidcorpResource\ApiMetricsExporter;

use SidcorpResource\ApiMetricsExporter\Contracts\MetricInterface;
use SidcorpResource\ApiMetricsExporter\Contracts\CounterInterface;
use SidcorpResource\ApiMetricsExporter\Contracts\GaugeInterface;
use SidcorpResource\ApiMetricsExporter\Contracts\HistogramInterface;
use SidcorpResource\ApiMetricsExporter\Contracts\SummaryInterface;
use SidcorpResource\ApiMetricsExporter\Contracts\StorageDriverInterface;
use SidcorpResource\ApiMetricsExporter\Metrics\Counter;
use SidcorpResource\ApiMetricsExporter\Metrics\Gauge;
use SidcorpResource\ApiMetricsExporter\Metrics\Histogram;
use SidcorpResource\ApiMetricsExporter\Metrics\Summary;
use SidcorpResource\ApiMetricsExporter\Exceptions\StorageDriverException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Registry for all metrics
 */
class MetricsRegistry
{
    /**
     * Storage driver for metrics
     *
     * @var StorageDriverInterface
     */
    protected StorageDriverInterface $driver;

    /**
     * Logger instance
     *
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;
    
    /**
     * Registered metrics
     *
     * @var array<string, MetricInterface>
     */
    protected array $metrics = [];
    
    /**
     * Create a new metrics registry
     *
     * @param StorageDriverInterface $driver Storage driver
     * @param LoggerInterface|null $logger Logger instance
     */
    public function __construct(StorageDriverInterface $driver, ?LoggerInterface $logger = null)
    {
        $this->driver = $driver;
        $this->logger = $logger ?? new NullLogger();
    }
    
    /**
     * Register a counter metric
     *
     * @param string $name Metric name
     * @param string $help Help text describing the metric
     * @param array<string> $labelNames Names of labels for this metric
     * @return CounterInterface The registered counter
     */
    public function registerCounter(string $name, string $help, array $labelNames = []): CounterInterface
    {
        if (isset($this->metrics[$name])) {
            if ($this->metrics[$name] instanceof CounterInterface) {
                return $this->metrics[$name];
            } else {
                $this->logger->warning("Metric {$name} already registered as a different type");
                throw new StorageDriverException("Metric {$name} already registered as a different type");
            }
        }
        
        $counter = new Counter($name, $help, $labelNames, $this->driver);
        $this->metrics[$name] = $counter;
        $this->driver->registerCounter($name, $help, $labelNames);
        
        return $counter;
    }
    
    /**
     * Register a gauge metric
     *
     * @param string $name Metric name
     * @param string $help Help text describing the metric
     * @param array<string> $labelNames Names of labels for this metric
     * @return GaugeInterface The registered gauge
     */
    public function registerGauge(string $name, string $help, array $labelNames = []): GaugeInterface
    {
        if (isset($this->metrics[$name])) {
            if ($this->metrics[$name] instanceof GaugeInterface) {
                return $this->metrics[$name];
            } else {
                $this->logger->warning("Metric {$name} already registered as a different type");
                throw new StorageDriverException("Metric {$name} already registered as a different type");
            }
        }
        
        $gauge = new Gauge($name, $help, $labelNames, $this->driver);
        $this->metrics[$name] = $gauge;
        $this->driver->registerGauge($name, $help, $labelNames);
        
        return $gauge;
    }
    
    /**
     * Register a histogram metric
     *
     * @param string $name Metric name
     * @param string $help Help text describing the metric
     * @param array<string> $labelNames Names of labels for this metric
     * @param array<float> $buckets Histogram buckets
     * @return HistogramInterface The registered histogram
     */
    public function registerHistogram(string $name, string $help, array $labelNames = [], array $buckets = []): HistogramInterface
    {
        if (isset($this->metrics[$name])) {
            if ($this->metrics[$name] instanceof HistogramInterface) {
                return $this->metrics[$name];
            } else {
                $this->logger->warning("Metric {$name} already registered as a different type");
                throw new StorageDriverException("Metric {$name} already registered as a different type");
            }
        }
        
        $histogram = new Histogram($name, $help, $labelNames, $buckets, $this->driver);
        $this->metrics[$name] = $histogram;
        $this->driver->registerHistogram($name, $help, $labelNames, $buckets);
        
        return $histogram;
    }
    
    /**
     * Register a summary metric
     *
     * @param string $name Metric name
     * @param string $help Help text describing the metric
     * @param array<string> $labelNames Names of labels for this metric
     * @param array<float, float> $quantiles Map of quantiles to error tolerance
     * @return SummaryInterface The registered summary
     */
    public function registerSummary(string $name, string $help, array $labelNames = [], array $quantiles = []): SummaryInterface
    {
        if (isset($this->metrics[$name])) {
            if ($this->metrics[$name] instanceof SummaryInterface) {
                return $this->metrics[$name];
            } else {
                $this->logger->warning("Metric {$name} already registered as a different type");
                throw new StorageDriverException("Metric {$name} already registered as a different type");
            }
        }
        
        $summary = new Summary($name, $help, $labelNames, $quantiles, $this->driver);
        $this->metrics[$name] = $summary;
        $this->driver->registerSummary($name, $help, $labelNames, $quantiles);
        
        return $summary;
    }
    
    /**
     * Get a registered metric by name
     *
     * @param string $name Metric name
     * @return MetricInterface|null The metric or null if not found
     */
    public function getMetric(string $name): ?MetricInterface
    {
        return $this->metrics[$name] ?? null;
    }
    
    /**
     * Get all registered metrics
     *
     * @return array<string, MetricInterface> Map of metric name to metric instance
     */
    public function getMetrics(): array
    {
        return $this->metrics;
    }
    
    /**
     * Get the storage driver
     *
     * @return StorageDriverInterface
     */
    public function getDriver(): StorageDriverInterface
    {
        return $this->driver;
    }
}
