<?php

namespace SidcorpResource\ApiMetricsExporter\Tests\Integration;

use SidcorpResource\ApiMetricsExporter\MetricsCollector;
use SidcorpResource\ApiMetricsExporter\MetricsExporter;
use SidcorpResource\ApiMetricsExporter\Middleware\MetricsMiddleware;
use SidcorpResource\ApiMetricsExporter\ApiMetricsServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Orchestra\Testbench\TestCase;
use Closure;
use Mockery;

class MetricsMiddlewareTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [
            ApiMetricsServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app)
    {
        $app['config']->set('api-metrics.driver', 'memory');
        $app['config']->set('api-metrics.collection.enabled', true);
    }

    public function testMiddlewareRecordsMetrics()
    {
        // Set up collector with in-memory driver
        $collector = $this->app->make(MetricsCollector::class);
        $middleware = new MetricsMiddleware($collector);
        
        // Create a request
        $request = Request::create('/api/users', 'GET');
        
        // Define a simple next closure
        $next = function ($req) {
            return new Response('OK', 200);
        };
        
        // Process the request through middleware
        $response = $middleware->handle($request, $next);
        
        // Get metrics to verify they were recorded
        $metrics = $collector->getMetrics();
        
        // Verify counter was incremented
        $this->assertStringContainsString('api_requests_total', $metrics);
        $this->assertStringContainsString('endpoint="/api/users"', $metrics);
        
        // Verify histogram was recorded
        $this->assertStringContainsString('api_request_duration_seconds', $metrics);
    }
    
    public function testMetricsEndpoint()
    {
        // Configure routes
        $this->app['router']->get('/metrics', function () {
            $exporter = $this->app->make(MetricsExporter::class);
            return $exporter->handleRequest(request());
        });
        
        // Make a request to generate some metrics
        $this->app['router']->get('/api/test', function () {
            return response('Test OK');
        })->middleware(MetricsMiddleware::class);
        
        // Generate some metrics
        $this->get('/api/test');
        $this->get('/api/test');
        
        // Hit the metrics endpoint
        $response = $this->get('/metrics');
        
        // Verify response
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/plain; version=0.0.4; charset=UTF-8');
        
        // Check metrics content
        $content = $response->getContent();
        $this->assertStringContainsString('api_requests_total', $content);
        $this->assertStringContainsString('api_request_duration_seconds', $content);
    }

    public function testExcludedPathsAreSkipped()
    {
        // Configure the collector with exclusions first, before resolving the service
        $this->app['config']->set('api-metrics.collection.excluded_paths', ['excluded']);
        
        // Create a fresh collector instance directly instead of using the container
        $collector = new MetricsCollector(
            'memory',
            [
                'excluded_paths' => ['excluded'],  // Note: no leading slash as request->path() removes it
                'excluded_methods' => [],
                'enabled' => true
            ]
        );
        $middleware = new MetricsMiddleware($collector);
        
        // Test a normal request - should be recorded
        $normalRequest = Request::create('/api/normal', 'GET');
        $middleware->handle($normalRequest, function ($req) {
            return new Response('OK', 200);
        });
        
        // Test an excluded request - should NOT be recorded
        $excludedRequest = Request::create('/excluded', 'GET');
        $middleware->handle($excludedRequest, function ($req) {
            return new Response('OK', 200);
        });
        
        // Get metrics
        $metrics = $collector->getMetrics();
        
        // Should contain the normal path but not the excluded one
        $this->assertStringContainsString('endpoint="/api/normal"', $metrics);
        $this->assertStringNotContainsString('endpoint="/excluded"', $metrics);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
