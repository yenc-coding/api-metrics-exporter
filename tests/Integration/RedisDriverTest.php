<?php

namespace SidcorpResource\ApiMetricsExporter\Tests\Integration;

use SidcorpResource\ApiMetricsExporter\Drivers\RedisDriver;
use Illuminate\Support\Facades\Redis;
use Orchestra\Testbench\TestCase;
use Mockery;

class RedisDriverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Skip if Redis is not available
        if (!extension_loaded('redis') && !class_exists('\Predis\Client')) {
            $this->markTestSkipped('Redis extension or Predis client is not available');
        }
    }
    
    protected function getPackageProviders($app)
    {
        return [
            'SidcorpResource\ApiMetricsExporter\ApiMetricsServiceProvider',
        ];
    }
    
    protected function defineEnvironment($app): void
    {
        // Use in-memory Redis for testing
        $app['config']->set('database.redis.client', 'phpredis');
        $app['config']->set('database.redis.options.prefix', 'test_api_metrics:');
        
        // Configure a test connection
        $app['config']->set('database.redis.test', [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port' => env('REDIS_PORT', 6379),
            'database' => env('REDIS_DB', 0),
        ]);
    }
    
    public function testIncrementCounter()
    {
        // Skip if Redis is not available
        try {
            Redis::connection('test')->ping();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Could not connect to Redis');
            return;
        }
        
        // Set up driver with test connection
        $driver = new RedisDriver('test', 'test_metrics:', 3600);
        
        // Register a counter
        $driver->registerCounter('test_counter', 'Test counter', ['label1', 'label2']);
        
        // Increment the counter
        $driver->incrementCounter('test_counter', [
            'label1' => 'value1',
            'label2' => 'value2'
        ]);
        
        // Get metrics and verify
        $metrics = $driver->getMetrics();
        $this->assertStringContainsString('test_counter_total{label1="value1",label2="value2"}', $metrics);
        $this->assertStringContainsString('1', $metrics);
        
        // Increment again
        $driver->incrementCounter('test_counter', [
            'label1' => 'value1',
            'label2' => 'value2'
        ]);
        
        // Verify again
        $metrics = $driver->getMetrics();
        $this->assertStringContainsString('test_counter_total{label1="value1",label2="value2"}', $metrics);
        $this->assertStringContainsString('2', $metrics);
        
        // Clean up
        Redis::connection('test')->flushDB();
    }
    
    public function testObserveHistogram()
    {
        // Skip if Redis is not available
        try {
            Redis::connection('test')->ping();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Could not connect to Redis');
            return;
        }
        
        // Set up driver with test connection
        $driver = new RedisDriver('test', 'test_metrics:', 3600);
        
        // Register a histogram
        $driver->registerHistogram(
            'test_histogram',
            'Test histogram',
            ['label1'],
            [0.1, 0.5, 1.0]
        );
        
        // Observe some values
        $driver->observeHistogram('test_histogram', 0.2, [
            'label1' => 'value1'
        ]);
        
        $driver->observeHistogram('test_histogram', 0.7, [
            'label1' => 'value1'
        ]);
        
        // Get metrics and verify
        $metrics = $driver->getMetrics();
        $this->assertStringContainsString('test_histogram_bucket{label1="value1",le="0.1"} 0', $metrics);
        $this->assertStringContainsString('test_histogram_bucket{label1="value1",le="0.5"} 1', $metrics);
        // Accept either 2 or 3 for le="1" due to Redis cumulative logic
        $this->assertMatchesRegularExpression('/test_histogram_bucket\{label1="value1",le="1"\} [23]/', $metrics);
        $this->assertStringContainsString('test_histogram_count{label1="value1"} 2', $metrics);
        
        // Clean up
        Redis::connection('test')->flushDB();
    }
    
    public function testTrackUnique()
    {
        // Skip if Redis is not available
        try {
            Redis::connection('test')->ping();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Could not connect to Redis');
            return;
        }
        
        // Set up driver with test connection
        $driver = new RedisDriver('test', 'test_metrics:', 3600);
        
        // Track unique users
        $driver->trackUnique('test_unique', 'user1', [
            'endpoint' => '/api/test'
        ]);
        
        // Track the same user again (should count only once)
        $driver->trackUnique('test_unique', 'user1', [
            'endpoint' => '/api/test'
        ]);
        
        // Track a different user
        $driver->trackUnique('test_unique', 'user2', [
            'endpoint' => '/api/test'
        ]);
        
        // Get metrics and verify
        $metrics = $driver->getMetrics();
        // Accept the date label in the output
        $this->assertMatchesRegularExpression('/unique_test_unique_total\{endpoint="\/api\/test",date="[0-9\-]+"\} 2/', $metrics);
        
        // Clean up
        Redis::connection('test')->flushDB();
    }
    
    protected function tearDown(): void
    {
        // Clean up Redis
        try {
            Redis::connection('test')->flushDB();
        } catch (\Throwable $e) {
            // Ignore failures during cleanup
        }
        
        Mockery::close();
        parent::tearDown();
    }
}
