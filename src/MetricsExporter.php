<?php

declare(strict_types=1);

namespace SidcorpResource\ApiMetricsExporter;

use SidcorpResource\ApiMetricsExporter\Contracts\ExporterInterface;
use SidcorpResource\ApiMetricsExporter\Contracts\MetricsCollectorInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

/**
 * Exports collected metrics in Prometheus format
 */
class MetricsExporter implements ExporterInterface
{
    /**
     * Metrics collector instance
     *
     * @var MetricsCollectorInterface
     */
    protected MetricsCollectorInterface $collector;

    /**
     * Logger instance
     *
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;

    /**
     * Authentication config
     * 
     * @var array
     */
    protected array $authConfig;
    
    /**
     * Create a new metrics exporter
     *
     * @param MetricsCollectorInterface $collector Metrics collector
     * @param LoggerInterface|null $logger Logger instance
     * @param array $authConfig Authentication configuration
     */
    public function __construct(
        MetricsCollectorInterface $collector, 
        ?LoggerInterface $logger = null,
        array $authConfig = []
    ) {
        $this->collector = $collector;
        $this->logger = $logger ?? new NullLogger();
        $this->authConfig = $authConfig;
    }

    /**
     * Export metrics in Prometheus OpenMetrics format
     *
     * @return string Metrics in Prometheus exposition format
     */
    public function export(): string
    {
        try {
            return $this->collector->getMetrics();
        } catch (Throwable $e) {
           $this->logger->error('Failed to export metrics: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
            
            return "# Error exporting metrics: {$e->getMessage()}\n";
        }
    }
    
    /**
     * Export metrics in Prometheus format with optional HTTP Basic Auth
     *
     * @param Request $request The request instance
     * @return Response The response with metrics
     */
    public function handleRequest(Request $request): Response
    {
        // Basic Auth check
        if (($this->authConfig['enabled'] ?? false) === true) {
            $user = $request->getUser();
            $pass = $request->getPassword();
            $expectedUser = $this->authConfig['username'] ?? '';
            $expectedPass = $this->authConfig['password'] ?? '';
            if ($user !== $expectedUser || $pass !== $expectedPass) {
                return new Response(
                    'Unauthorized',
                    401,
                    [
                        'WWW-Authenticate' => 'Basic realm="Metrics"',
                        'Content-Type' => 'text/plain; charset=UTF-8',
                    ]
                );
            }
        }

        try {
            $metrics = $this->export();

            return new Response(
                $metrics,
                200,
                [
                    'Content-Type' => $this->getContentType() . '; charset=UTF-8',
                    'Cache-Control' => 'no-cache, no-store, must-revalidate',
                ]
            );
        } catch (Throwable $e) {
            $this->logger->error('Error exporting metrics', [
                'exception' => $e->getMessage(),
            ]);

            return new Response(
                "# Error exporting metrics: {$e->getMessage()}\n",
                500,
                ['Content-Type' => $this->getContentType() . '; charset=UTF-8']
            );
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
