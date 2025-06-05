<?php

declare(strict_types=1);

namespace SidcorpResource\ApiMetricsExporter\Tests\Unit;

use SidcorpResource\ApiMetricsExporter\ApiMetrics;
use SidcorpResource\ApiMetricsExporter\ApiMetricsServiceProvider;
use SidcorpResource\ApiMetricsExporter\MetricsCollector;
use Orchestra\Testbench\TestCase;

class ApiMetricsFacadeTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ApiMetricsServiceProvider::class,
        ];
    }
    
    protected function defineEnvironment($app): void
    {
        $app['config']->set('api-metrics.driver', 'memory');
    }

    public function testFacadeRegistersAndIncrementsCounter(): void
    {
        ApiMetrics::registerCounter('test_facade_counter', 'Test facade counter', ['label']);
        ApiMetrics::incrementCounter('test_facade_counter', ['label' => 'value']);
        
        $metrics = ApiMetrics::getMetrics();
        
        $this->assertStringContainsString('test_facade_counter_total', $metrics);
        $this->assertStringContainsString('label="value"', $metrics);
    }

    public function testFacadeRegistersAndObservesHistogram(): void
    {
        ApiMetrics::registerHistogram('test_facade_histogram', 'Test facade histogram', ['label']);
        ApiMetrics::observeHistogram('test_facade_histogram', 0.5, ['label' => 'value']);
        
        $metrics = ApiMetrics::getMetrics();
        
        $this->assertStringContainsString('test_facade_histogram', $metrics);
        $this->assertStringContainsString('label="value"', $metrics);
    }

    public function testFacadeTracksUnique(): void
    {
        ApiMetrics::trackUnique('test_facade_unique', 'user123', ['endpoint' => '/api/test']);
        
        $metrics = ApiMetrics::getMetrics();
        
        $this->assertStringContainsString('unique_test_facade_unique_total', $metrics);
        $this->assertStringContainsString('endpoint="/api/test"', $metrics);
    }

    public function testFacadeAccessorReturnsCorrectClass(): void
    {
        // Test facade binding by checking if it resolves to correct class
        $instance = ApiMetrics::getFacadeRoot();
        $this->assertInstanceOf(MetricsCollector::class, $instance);
    }
}
