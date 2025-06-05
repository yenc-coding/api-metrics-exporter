<?php

declare(strict_types=1);

namespace SidcorpResource\ApiMetricsExporter;

use Illuminate\Support\Facades\Facade;

/**
 * Facade for the API metrics collector
 * 
 * @method static \SidcorpResource\ApiMetricsExporter\Contracts\CounterInterface registerCounter(string $name, string $help, array $labelNames = [])
 * @method static \SidcorpResource\ApiMetricsExporter\Contracts\HistogramInterface registerHistogram(string $name, string $help, array $labelNames = [], array $buckets = [])
 * @method static \SidcorpResource\ApiMetricsExporter\Contracts\GaugeInterface registerGauge(string $name, string $help, array $labelNames = [])
 * @method static \SidcorpResource\ApiMetricsExporter\Contracts\SummaryInterface registerSummary(string $name, string $help, array $labelNames = [], array $quantiles = [])
 * @method static void incrementCounter(string $name, array $labels = [], int $value = 1)
 * @method static void observeHistogram(string $name, float $value, array $labels = [])
 * @method static void setGauge(string $name, float $value, array $labels = [])
 * @method static void trackUnique(string $name, string $identifier, array $labels = [])
 * @method static string getMetrics()
 * @method static \SidcorpResource\ApiMetricsExporter\MetricsRegistry getRegistry()
 * @method static \SidcorpResource\ApiMetricsExporter\Contracts\StorageDriverInterface getDriver()
 * 
 * @see \SidcorpResource\ApiMetricsExporter\MetricsCollector
 */
class ApiMetrics extends Facade
{
    /**
     * Get the registered name of the component
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return MetricsCollector::class;
    }
}
