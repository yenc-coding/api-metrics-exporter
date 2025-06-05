<?php

namespace SidcorpResource\ApiMetricsExporter\Tests\Unit;

use SidcorpResource\ApiMetricsExporter\MetricsCollector;
use SidcorpResource\ApiMetricsExporter\Drivers\InMemoryDriver;
use PHPUnit\Framework\TestCase;

class MetricsCollectorTest extends TestCase
{
    private MetricsCollector $collector;
    private InMemoryDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->driver = new InMemoryDriver();
        $this->collector = new MetricsCollector($this->driver);
    }

    public function testIncrementCounter()
    {
        $labels = ['endpoint' => '/api/users', 'method' => 'GET', 'status' => '200', 'user_id' => '123'];
        
        $this->collector->incrementCounter('api_requests_total', $labels);
        $this->collector->incrementCounter('api_requests_total', $labels);
        
        $metrics = $this->collector->getMetrics();
        
        $this->assertStringContainsString('api_requests_total{endpoint="/api/users",method="GET",status="200",user_id="123"} 2', $metrics);
    }

    public function testObserveHistogram()
    {
        $labels = ['endpoint' => '/api/users', 'method' => 'GET', 'status' => '200', 'user_id' => '123'];
        
        $this->collector->observeHistogram('api_request_duration_seconds', 0.1, $labels);
        $this->collector->observeHistogram('api_request_duration_seconds', 0.2, $labels);
        
        $metrics = $this->collector->getMetrics();
        
        $this->assertStringContainsString('api_request_duration_seconds_sum', $metrics);
        $this->assertStringContainsString('api_request_duration_seconds_count', $metrics);
        $this->assertStringContainsString('api_request_duration_seconds_bucket', $metrics);
    }

    public function testTrackUnique()
    {
        $labels = ['endpoint' => '/api/users', 'method' => 'GET', 'status' => '200'];
        
        $this->collector->trackUnique('users', '123', $labels);
        $this->collector->trackUnique('users', '123', $labels); // Duplicate, should count once
        $this->collector->trackUnique('users', '456', $labels); // New user
        
        $metrics = $this->collector->getMetrics();
        
        $this->assertStringContainsString('unique_users_total', $metrics);
        // Should be 2 unique users
        $this->assertStringContainsString('2', $metrics);
    }
    
    public function testShouldExcludePath()
    {
        $collector = new MetricsCollector(
            'memory',
            [
                'excluded_paths' => ['/metrics', '/health*'],
                'excluded_methods' => ['OPTIONS'],
            ]
        );
        
        $this->assertTrue($collector->shouldExcludePath('/metrics', 'GET'));
        $this->assertTrue($collector->shouldExcludePath('/health', 'GET'));
        $this->assertTrue($collector->shouldExcludePath('/health/check', 'GET'));
        $this->assertTrue($collector->shouldExcludePath('/api/users', 'OPTIONS'));
        $this->assertFalse($collector->shouldExcludePath('/api/users', 'GET'));
    }
}
