<?php

declare(strict_types=1);

namespace SidcorpResource\ApiMetricsExporter\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SidcorpResource\ApiMetricsExporter\MetricsCollector;
use SidcorpResource\ApiMetricsExporter\Drivers\InMemoryDriver;
use Psr\Log\LoggerInterface;

/**
 * Test case for Prometheus metric naming conventions
 */
class MetricNamingConventionsTest extends TestCase
{
    protected MetricsCollector $collector;
    protected LoggerInterface $logger;

    protected function setUp(): void
    {
        parent::setUp();
        // Create a mock logger to catch warnings
        $this->logger = $this->createMock(LoggerInterface::class);
        
        // Use the InMemoryDriver for testing
        $driver = new InMemoryDriver($this->logger);
        $this->collector = new MetricsCollector($driver, [], $this->logger);
    }

    /**
     * Test counter registration with proper naming
     */
    public function testValidCounterNaming(): void
    {
        // Logger should not get any warning calls for valid metric names
        $this->logger->expects($this->never())
            ->method('warning');

        $this->collector->registerCounter(
            'valid_counter_name_total',
            'A valid counter name with total suffix',
            ['valid_label']
        );
        
        // Get the metrics output and verify it has the correct format
        $output = $this->collector->getMetrics();
        
        $this->assertStringContainsString('# HELP valid_counter_name_total', $output);
        $this->assertStringContainsString('# TYPE valid_counter_name_total counter', $output);
    }
    
    /**
     * Test counter auto-adding _total suffix
     */
    public function testCounterAutoTotal(): void
    {
        // Logger should log an info message about adding the _total suffix
        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                $this->stringContains('should have _total suffix'),
                $this->arrayHasKey('name')
            );

        $this->collector->registerCounter(
            'counter_without_suffix',
            'A counter without total suffix',
            ['valid_label']
        );
        
        // Get the metrics output and verify it added the _total suffix
        $output = $this->collector->getMetrics();
        
        $this->assertStringContainsString('# HELP counter_without_suffix_total', $output);
        $this->assertStringContainsString('# TYPE counter_without_suffix_total counter', $output);
    }
    
    /**
     * Test invalid metric name warning
     */
    public function testInvalidMetricName(): void
    {
        // Logger should log a warning about invalid metric name
        $this->logger->expects($this->once())
            ->method('warning')
            ->with(
                $this->stringContains('Invalid metric name'),
                $this->arrayHasKey('name')
            );

        $this->collector->registerCounter(
            'invalid-metric-name',  // Contains hyphens which are not allowed
            'An invalid metric name',
            ['valid_label']
        );
    }
    
    /**
     * Test invalid label name warning
     */
    public function testInvalidLabelName(): void
    {
        // Logger should log a warning about invalid label name
        $this->logger->expects($this->once())
            ->method('warning')
            ->with(
                $this->stringContains('Invalid label name'),
                $this->arrayHasKey('label')
            );

        $this->collector->registerCounter(
            'valid_name_total',
            'A valid metric name',
            ['invalid-label'] // Contains hyphens which are not allowed
        );
    }
    
    /**
     * Test histogram with proper unit suffix
     */
    public function testHistogramUnitSuffix(): void
    {
        // Logger should not get any warning calls for valid histogram names with units
        $this->logger->expects($this->never())
            ->method('warning');

        $this->collector->registerHistogram(
            'request_duration_seconds',
            'A histogram with seconds unit',
            ['endpoint', 'method']
        );
        
        // Get the metrics output and verify it has the correct format
        $output = $this->collector->getMetrics();
        
        $this->assertStringContainsString('# HELP request_duration_seconds', $output);
        $this->assertStringContainsString('# TYPE request_duration_seconds histogram', $output);
    }
    
    /**
     * Test valid label format in output
     */
    public function testLabelFormat(): void
    {
        $this->collector->registerCounter(
            'test_counter_total',
            'Test counter',
            ['label1', 'label2']
        );
        
        $this->collector->incrementCounter('test_counter_total', [
            'label1' => 'value1',
            'label2' => 'value2"with quotes'
        ]);
        
        // Get the metrics output
        $output = $this->collector->getMetrics();
        
        // Verify labels are properly formatted with double quotes and escaping
        // The regex needs to account for escaped quotes which appear as \" in the output
        $this->assertMatchesRegularExpression(
            '/test_counter_total\{label1="value1",label2="value2\\\\"with quotes"\} \d+/',
            $output
        );
    }
    
    /**
     * Test date in label not in metric name
     */
    public function testDateInLabel(): void
    {
        // Logger should log a warning about date in metric name
        $this->logger->expects($this->once())
            ->method('warning')
            ->with(
                $this->stringContains('date'),
                $this->arrayHasKey('name')
            );

        $this->collector->registerCounter(
            'metrics_20250604_total', // Date in name - bad practice
            'Metric with date in name',
            ['label']
        );
    }
    
    /**
     * Test histogram bucket format
     */
    public function testHistogramBucketFormat(): void
    {
        $this->collector->registerHistogram(
            'test_histogram_seconds',
            'Test histogram',
            ['label'],
            [0.1, 0.5, 1.0]
        );
        
        $this->collector->observeHistogram('test_histogram_seconds', 0.3, [
            'label' => 'value'
        ]);
        
        // Get the metrics output
        $output = $this->collector->getMetrics();
        
        // Verify buckets are properly formatted
        $this->assertMatchesRegularExpression(
            '/test_histogram_seconds_bucket{label="value",le="0.1"} \d+/',
            $output
        );
        $this->assertMatchesRegularExpression(
            '/test_histogram_seconds_bucket{label="value",le="0.5"} \d+/',
            $output
        );
        $this->assertMatchesRegularExpression(
            '/test_histogram_seconds_bucket{label="value",le="1"} \d+/',
            $output
        );
        $this->assertMatchesRegularExpression(
            '/test_histogram_seconds_bucket{label="value",le="\+Inf"} \d+/',
            $output
        );
        $this->assertMatchesRegularExpression(
            '/test_histogram_seconds_sum{label="value"} \d+(\.\d+)?/',
            $output
        );
        $this->assertMatchesRegularExpression(
            '/test_histogram_seconds_count{label="value"} \d+/',
            $output
        );
    }
}
