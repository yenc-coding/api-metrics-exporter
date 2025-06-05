<?php

namespace SidcorpResource\ApiMetricsExporter\Tests\Integration;

use Orchestra\Testbench\TestCase;
use SidcorpResource\ApiMetricsExporter\ApiMetricsServiceProvider;
use SidcorpResource\ApiMetricsExporter\Contracts\MetricsCollectorInterface;
use SidcorpResource\ApiMetricsExporter\MetricsExporter;
use Mockery;

/**
 * Integration tests for MetricsExporter
 * These tests verify that the service provider correctly wires up dependencies
 */
class MetricsExporterTest extends TestCase
{
    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array
     */
    protected function getPackageProviders($app): array
    {
        return [
            ApiMetricsServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function defineEnvironment($app): void
    {
        // Use memory driver for integration tests to avoid Redis dependencies
        $app['config']->set('api-metrics.driver', 'memory');
        $app['config']->set('api-metrics.endpoint.path', 'metrics');
        $app['config']->set('api-metrics.endpoint.middleware', []);
        $app['config']->set('api-metrics.collection.enabled', true);
    }

    /**
     * Test that services are properly wired up
     */
    public function testServiceResolution(): void
    {
        // Test that we can resolve the collector
        $collector = $this->app->make(MetricsCollectorInterface::class);
        $this->assertInstanceOf(MetricsCollectorInterface::class, $collector);
        
        // Test that we can resolve the exporter
        $exporter = $this->app->make(MetricsExporter::class);
        $this->assertInstanceOf(MetricsExporter::class, $exporter);
    }

    /**
     * Test that export method works directly
     */
    public function testExportMethodWorks(): void
    {
        // Mock collector
        $collector = Mockery::mock(MetricsCollectorInterface::class);
        $collector->shouldReceive('getMetrics')
            ->once()
            ->andReturn("# HELP test_metric Test metric\n# TYPE test_metric gauge\ntest_metric 123\n");
        
        // Create exporter with mocked collector
        $exporter = new MetricsExporter($collector);
        
        // Call handleRequest with request
        $request = request();
        $response = $exporter->handleRequest($request);
        
        // Assert response
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/plain; version=0.0.4; charset=UTF-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('test_metric 123', $response->getContent());
    }

    public function testExportWithBasicAuthSuccess(): void
    {
        $this->app['config']->set('api-metrics.basic_auth', [
            'enabled' => true,
            'username' => 'user',
            'password' => 'pass',
        ]);
        $collector = Mockery::mock(MetricsCollectorInterface::class);
        $collector->shouldReceive('getMetrics')
            ->once()
            ->andReturn("# HELP test_metric Test metric\n# TYPE test_metric gauge\ntest_metric 123\n");
        $exporter = new MetricsExporter($collector, null, [
            'enabled' => true,
            'username' => 'user',
            'password' => 'pass',
        ]);
        $auth = 'Basic ' . base64_encode('user:pass');
        $request = request()->duplicate([], [], [], [], [], [
            'HTTP_AUTHORIZATION' => $auth,
        ]);
        $response = $exporter->handleRequest($request);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('test_metric 123', $response->getContent());
    }

    public function testExportWithBasicAuthMissingCredentials(): void
    {
        $this->app['config']->set('api-metrics.basic_auth', [
            'enabled' => true,
            'username' => 'user',
            'password' => 'pass',
        ]);
        $collector = Mockery::mock(MetricsCollectorInterface::class);
        $exporter = new MetricsExporter($collector, null, [
            'enabled' => true,
            'username' => 'user',
            'password' => 'pass',
        ]);
        $request = request()->duplicate();
        // No Authorization header
        $response = $exporter->handleRequest($request);
        $this->assertEquals(401, $response->getStatusCode());
        $this->assertEquals('Basic realm="Metrics"', $response->headers->get('WWW-Authenticate'));
    }

    public function testExportWithBasicAuthWrongCredentials(): void
    {
        $this->app['config']->set('api-metrics.basic_auth', [
            'enabled' => true,
            'username' => 'user',
            'password' => 'pass',
        ]);
        $collector = Mockery::mock(MetricsCollectorInterface::class);
        $exporter = new MetricsExporter($collector, null, [
            'enabled' => true,
            'username' => 'user',
            'password' => 'pass',
        ]);
        $auth = 'Basic ' . base64_encode('wrong:wrong');
        $request = request()->duplicate([], [], [], [], [], [
            'HTTP_AUTHORIZATION' => $auth,
        ]);
        $response = $exporter->handleRequest($request);
        $this->assertEquals(401, $response->getStatusCode());
        $this->assertEquals('Basic realm="Metrics"', $response->headers->get('WWW-Authenticate'));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
