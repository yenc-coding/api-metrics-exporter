<?php

declare(strict_types=1);

namespace SidcorpResource\ApiMetricsExporter\Exporter;

use SidcorpResource\ApiMetricsExporter\Contracts\ExporterInterface;
use SidcorpResource\ApiMetricsExporter\Contracts\StorageDriverInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

/**
 * Prometheus format metrics exporter
 */
class PrometheusExporter implements ExporterInterface
{
    /**
     * Storage driver instance
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
     * Create a new Prometheus exporter instance
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
     * Export metrics in Prometheus OpenMetrics format
     *
     * @return string Metrics in Prometheus exposition format
     */
    public function export(): string
    {
        try {
            return $this->driver->getMetrics();
        } catch (Throwable $e) {
            $this->logger->error('Failed to export metrics: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
            
            return "# Error exporting metrics: {$e->getMessage()}\n";
        }
    }
    
    /**
     * Get the Content-Type header value for the metrics response
     *
     * @return string Content-Type header value
     */
    public function getContentType(): string
    {
        return 'text/plain; version=0.0.4';
    }
}
