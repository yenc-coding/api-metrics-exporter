<?php

declare(strict_types=1);

namespace SidcorpResource\ApiMetricsExporter\Contracts;

/**
 * Interface for exporter services
 */
interface ExporterInterface
{
    /**
     * Export metrics in Prometheus OpenMetrics format
     *
     * @return string Metrics in Prometheus exposition format
     */
    public function export(): string;
    
    /**
     * Get the Content-Type header value for the metrics response
     *
     * @return string Content-Type header value
     */
    public function getContentType(): string;
}
