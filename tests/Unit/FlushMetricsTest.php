<?php

namespace Tests\Unit;

use Orchestra\Testbench\TestCase;
use SidcorpResource\ApiMetricsExporter\MetricsCollector;
use SidcorpResource\ApiMetricsExporter\Drivers\InMemoryDriver;
use SidcorpResource\ApiMetricsExporter\Drivers\RedisDriver;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Psr\Log\LoggerInterface;

class FlushMetricsTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
    
    public function testFlushMetricsWithInMemoryDriver()
    {
        // Create metrics collector with InMemory driver
        $collector = new MetricsCollector('memory');
        
        // Register metrics first
        $collector->registerCounter('test_counter', 'Test counter metric', ['label']);
        $collector->registerHistogram('test_histogram', 'Test histogram metric', ['label']);
        
        // Add some metrics
        $collector->incrementCounter('test_counter', ['label' => 'value'], 5);
        $collector->observeHistogram('test_histogram', 0.75, ['label' => 'value']);
        $collector->trackUnique('test_unique', 'user123', ['label' => 'value']);
        
        // Verify metrics exist
        $metricsOutput = $collector->getMetrics();
        
        // Check that our counter has a value of 5
        $this->assertMatchesRegularExpression('/test_counter_total\{label="value"\}\s+5/', $metricsOutput);
        $this->assertMatchesRegularExpression('/test_histogram_sum\{label="value"\}\s+0\.75/', $metricsOutput);
        $this->assertStringContainsString('unique_test_unique_total', $metricsOutput);
        
        // Flush metrics
        $result = $collector->flushMetrics();
        
        // Should return true
        $this->assertTrue($result);
        
        // Get metrics again - counters should be reset
        $metricsAfterFlush = $collector->getMetrics();
        
        // The metrics are still defined but their values should be gone or reset
        $this->assertDoesNotMatchRegularExpression('/test_counter_total\{label="value"\}\s+5/', $metricsAfterFlush);
        $this->assertDoesNotMatchRegularExpression('/test_histogram_sum\{label="value"\}\s+0\.75/', $metricsAfterFlush);
    }
    
    public function testFlushMetricsWithRedisDriver()
    {
        // Mock Redis connection
        $redisMock = Mockery::mock('Illuminate\Redis\Connections\Connection');
        $redisMock->shouldReceive('keys')->with('api_metrics:*')->andReturn(['api_metrics:counter:test_counter_total{label="value"}']);
        $redisMock->shouldReceive('del')->with('api_metrics:counter:test_counter_total{label="value"}')->andReturn(1);
        $redisMock->shouldReceive('client')->andReturnSelf();
        $redisMock->shouldReceive('setOption')->andReturn(true);
        
        // Mock Redis facade
        Redis::shouldReceive('connection')->andReturn($redisMock);
        
        // Create logger mock
        $loggerMock = Mockery::mock(LoggerInterface::class);
        $loggerMock->shouldReceive('info')->times(1); // Should log success
        $loggerMock->shouldReceive('error')->times(0); // Should not log errors
        
        // Create Redis driver with our mocks
        $driver = new RedisDriver('default', 'api_metrics:', 86400, $loggerMock);
        
        // Create metrics collector with our driver
        $collector = new MetricsCollector($driver);
        
        // Flush metrics
        $result = $collector->flushMetrics();
        
        // Should return true
        $this->assertTrue($result);
    }
    
    public function testFlushMetricsWithRedisDriverHandlesError()
    {
        // Mock Redis connection that throws an exception when keys() is called
        $redisMock = Mockery::mock('Illuminate\Redis\Connections\Connection');
        $redisMock->shouldReceive('keys')->with('api_metrics:*')->andThrow(new \Exception('Redis connection error'));
        $redisMock->shouldReceive('client')->andReturnSelf();
        $redisMock->shouldReceive('setOption')->andReturn(true);
        
        // Mock Redis facade
        Redis::shouldReceive('connection')->andReturn($redisMock);
        
        // Create logger mock
        $loggerMock = Mockery::mock(LoggerInterface::class);
        $loggerMock->shouldReceive('info')->times(0); // Should not log success
        $loggerMock->shouldReceive('error')->times(1); // Should log errors
        
        // Create Redis driver with our mocks
        $driver = new RedisDriver('default', 'api_metrics:', 86400, $loggerMock);
        
        // Create metrics collector with our driver
        $collector = new MetricsCollector($driver);
        
        // Flush metrics
        $result = $collector->flushMetrics();
        
        // Should return false on error
        $this->assertFalse($result);
    }
}
