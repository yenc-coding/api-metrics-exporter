<?php

declare(strict_types=1);

namespace SidcorpResource\ApiMetricsExporter\Tests\Unit;

use SidcorpResource\ApiMetricsExporter\MetricsExporter;
use SidcorpResource\ApiMetricsExporter\Contracts\MetricsCollectorInterface;
use Illuminate\Http\Request;
use Mockery;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class MetricsExporterTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testExportReturnsPrometheusFormat(): void
    {
        $collector = Mockery::mock(MetricsCollectorInterface::class);
        $collector->shouldReceive('getMetrics')
            ->once()
            ->andReturn("# HELP test_metric Test metric\n# TYPE test_metric counter\ntest_metric 42\n");
        
        $exporter = new MetricsExporter($collector);
        $metrics = $exporter->export();
        
        $this->assertStringContainsString('test_metric 42', $metrics);
    }

    public function testExportHandlesExceptions(): void
    {
        $collector = Mockery::mock(MetricsCollectorInterface::class);
        $collector->shouldReceive('getMetrics')
            ->once()
            ->andThrow(new \Exception('Test exception'));
        
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('error')
            ->once()
            ->with('Failed to export metrics: Test exception', \Mockery::any());
        
        $exporter = new MetricsExporter($collector, $logger);
        $metrics = $exporter->export();
        
        $this->assertStringContainsString('Error exporting metrics', $metrics);
    }

    public function testHandleRequestSetsCorrectHeaders(): void
    {
        $collector = Mockery::mock(MetricsCollectorInterface::class);
        $collector->shouldReceive('getMetrics')
            ->once()
            ->andReturn("# Metrics\n");
        
        $exporter = new MetricsExporter($collector);
        $request = Request::create('/metrics');
        
        $response = $exporter->handleRequest($request);
        
        $this->assertEquals('text/plain; version=0.0.4; charset=UTF-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('no-cache', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));  
        $this->assertStringContainsString('must-revalidate', $response->headers->get('Cache-Control'));
    }

    public function testHandleRequestReturnsCorrectContentType(): void
    {
        $collector = Mockery::mock(MetricsCollectorInterface::class);
        
        $exporter = new MetricsExporter($collector);
        $contentType = $exporter->getContentType();
        
        $this->assertEquals('text/plain; version=0.0.4', $contentType);
    }

    public function testHandleRequestWithException(): void
    {
        $collector = Mockery::mock(MetricsCollectorInterface::class);
        $collector->shouldReceive('getMetrics')
            ->once()
            ->andThrow(new \Exception('Test exception'));
        
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('error')
            ->once()
            ->with('Error exporting metrics', \Mockery::any());
        
        $exporter = new MetricsExporter($collector, $logger);
        $request = Request::create('/metrics');
        
        $response = $exporter->handleRequest($request);
        
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('text/plain; version=0.0.4; charset=UTF-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('Error exporting metrics', $response->getContent());
    }

    public function testHandleRequestWithBasicAuth(): void
    {
        $authConfig = [
            'enabled' => true,
            'username' => 'user',
            'password' => 'pass',
        ];
        
        $collector = Mockery::mock(MetricsCollectorInterface::class);
        $collector->shouldReceive('getMetrics')
            ->never(); // We should never reach this point with invalid auth
        
        $exporter = new MetricsExporter($collector, null, $authConfig);
        
        // Test with invalid auth
        $request = Request::create('/metrics');
        $response = $exporter->handleRequest($request);
        
        $this->assertEquals(401, $response->getStatusCode());
        $this->assertEquals('Basic realm="Metrics"', $response->headers->get('WWW-Authenticate'));
        
        // Test with valid auth
        $request = Request::create('/metrics', 'GET', [], [], [], [
            'PHP_AUTH_USER' => 'user',
            'PHP_AUTH_PW' => 'pass',
        ]);
        
        // We need to re-mock this for the successful case
        $collector = Mockery::mock(MetricsCollectorInterface::class);
        $collector->shouldReceive('getMetrics')
            ->once()
            ->andReturn("# HELP test_metric Test metric\n");
        
        $exporter = new MetricsExporter($collector, null, $authConfig);
        $response = $exporter->handleRequest($request);
        
        $this->assertEquals(200, $response->getStatusCode());
    }
}
