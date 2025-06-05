<?php

namespace SidcorpResource\ApiMetricsExporter\Tests\Unit;

use SidcorpResource\ApiMetricsExporter\ApiMetricsServiceProvider;
use SidcorpResource\ApiMetricsExporter\Contracts\MetricsCollectorInterface;
use SidcorpResource\ApiMetricsExporter\MetricsCollector;
use SidcorpResource\ApiMetricsExporter\MetricsExporter;
use SidcorpResource\ApiMetricsExporter\Middleware\MetricsMiddleware;
use Orchestra\Testbench\TestCase;

class ServiceProviderTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [
            ApiMetricsServiceProvider::class,
        ];
    }
    
    protected function defineEnvironment($app): void
    {
        $app['config']->set('api-metrics.driver', 'memory');
    }
    
    public function testServiceBindings()
    {
        // Test binding for interface
        $collector = $this->app->make(MetricsCollectorInterface::class);
        $this->assertInstanceOf(MetricsCollector::class, $collector);
        
        // Test binding for concrete class
        $collector = $this->app->make(MetricsCollector::class);
        $this->assertInstanceOf(MetricsCollector::class, $collector);
        
        // Test exporter binding
        $exporter = $this->app->make(MetricsExporter::class);
        $this->assertInstanceOf(MetricsExporter::class, $exporter);
        
        // Test middleware binding
        $middleware = $this->app->make(MetricsMiddleware::class);
        $this->assertInstanceOf(MetricsMiddleware::class, $middleware);
    }
    
    public function testConfigIsLoaded()
    {
        $config = $this->app['config']->get('api-metrics');
        $this->assertIsArray($config);
        $this->assertArrayHasKey('driver', $config);
        $this->assertEquals('memory', $config['driver']);
    }
    
    public function testRouteIsRegistered()
    {
        $routes = $this->app['router']->getRoutes();
        $this->assertTrue($routes->hasNamedRoute('api-metrics.export'));
    }
    
    public function testMiddlewareAlias()
    {
        $router = $this->app['router'];
        $this->assertArrayHasKey('api-metrics', $router->getMiddleware());
    }
}
