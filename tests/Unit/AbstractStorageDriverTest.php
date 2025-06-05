<?php

declare(strict_types=1);

namespace SidcorpResource\ApiMetricsExporter\Tests\Unit;

use SidcorpResource\ApiMetricsExporter\Drivers\AbstractStorageDriver;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class AbstractStorageDriverTest extends TestCase
{
    private TestableAbstractStorageDriver $driver;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->driver = new TestableAbstractStorageDriver($this->logger);
    }

    public function testValidateMetricNameWithValidName(): void
    {
        $this->logger->expects($this->never())->method('warning');
        
        $result = $this->driver->testValidateMetricName('valid_metric_name');
        $this->assertEquals('valid_metric_name', $result);
    }

    public function testValidateMetricNameWithInvalidCharacters(): void
    {
        $this->logger->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('Invalid metric name'));
        
        $result = $this->driver->testValidateMetricName('invalid-metric-name');
        $this->assertEquals('invalid_metric_name', $result);
    }

    public function testValidateMetricNameWithDateInName(): void
    {
        $this->logger->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('appears to contain a date'));
        
        $result = $this->driver->testValidateMetricName('metric_20240604_total');
        $this->assertEquals('metric_20240604_total', $result);
    }

    public function testValidateLabelNameWithValidName(): void
    {
        $this->logger->expects($this->never())->method('warning');
        
        $result = $this->driver->testValidateLabelName('valid_label');
        $this->assertEquals('valid_label', $result);
    }

    public function testValidateLabelNameWithInvalidCharacters(): void
    {
        $this->logger->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('Invalid label name'));
        
        $result = $this->driver->testValidateLabelName('invalid-label');
        $this->assertEquals('invalid_label', $result);
    }

    public function testValidateLabelNameFixesReservedNames(): void
    {
        $this->logger->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('Label names starting with __ are reserved'));
        
        $result = $this->driver->testValidateLabelName('__reserved');
        $this->assertEquals('label_reserved', $result);
    }

    public function testValidateLabelNameFixesSpecialReservedName(): void
    {
        $this->logger->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('Label names starting with __ are reserved'));
        
        $result = $this->driver->testValidateLabelName('__name__');
        $this->assertEquals('label_name__', $result);
    }

    public function testNormalizeMetricNameAddsCounterSuffix(): void
    {
        $result = $this->driver->testNormalizeMetricName('test_metric', 'counter');
        $this->assertEquals('test_metric_total', $result);
    }

    public function testNormalizeMetricNameAddsUniquePrefix(): void
    {
        $result = $this->driver->testNormalizeMetricName('test_metric', 'unique');
        $this->assertEquals('unique_test_metric_total', $result);
    }

    public function testNormalizeMetricNameKeepsExistingSuffix(): void
    {
        $result = $this->driver->testNormalizeMetricName('test_metric_total', 'counter');
        $this->assertEquals('test_metric_total', $result);
    }

    public function testGenerateLabelKeyWithEmptyLabels(): void
    {
        $result = $this->driver->testGenerateLabelKey('', []);
        $this->assertEquals('', $result);
    }

    public function testGenerateLabelKeyWithLabels(): void
    {
        $labels = ['method' => 'GET', 'status' => '200'];
        $result = $this->driver->testGenerateLabelKey('', $labels);
        $this->assertEquals('{method="GET",status="200"}', $result);
    }

    public function testGenerateLabelKeyEscapesQuotes(): void
    {
        $labels = ['message' => 'Error "not found"'];
        $result = $this->driver->testGenerateLabelKey('', $labels);
        $this->assertEquals('{message="Error \"not found\""}', $result);
    }

    public function testFormatValueWithInteger(): void
    {
        $result = $this->driver->testFormatValue(42);
        $this->assertEquals('42', $result);
    }

    public function testFormatValueWithFloat(): void
    {
        $result = $this->driver->testFormatValue(3.14159);
        $this->assertEquals('3.14159', $result);
    }

    public function testFormatValueWithInfinity(): void
    {
        $result = $this->driver->testFormatValue(INF);
        $this->assertEquals('+Inf', $result);
    }

    public function testFormatValueWithNegativeInfinity(): void
    {
        $result = $this->driver->testFormatValue(-INF);
        $this->assertEquals('-Inf', $result);
    }

    public function testFormatValueWithNaN(): void
    {
        $result = $this->driver->testFormatValue(NAN);
        $this->assertEquals('NaN', $result);
    }
}

/**
 * Testable implementation of AbstractStorageDriver for testing protected methods
 */
class TestableAbstractStorageDriver extends AbstractStorageDriver
{
    public function incrementCounter(string $name, array $labels = [], int $value = 1): void
    {
        // Implementation not needed for this test
    }

    public function observeHistogram(string $name, float $value, array $labels = [], array $buckets = []): void
    {
        // Implementation not needed for this test
    }

    public function trackUnique(string $name, string $identifier, array $labels = []): void
    {
        // Implementation not needed for this test
    }

    public function getMetrics(): string
    {
        return '';
    }
    
    public function getCounterValue(string $name, array $labels = []): int
    {
        return 0;
    }
    
    public function getHistogramSum(string $name, array $labels = []): float
    {
        return 0.0;
    }
    
    public function getHistogramCount(string $name, array $labels = []): int
    {
        return 0;
    }
    
    public function setGauge(string $name, float $value, array $labels = []): void
    {
        // Implementation not needed for this test
    }
    
    public function incrementGauge(string $name, float $value, array $labels = []): void
    {
        // Implementation not needed for this test
    }
    
    public function decrementGauge(string $name, float $value, array $labels = []): void
    {
        // Implementation not needed for this test
    }
    
    public function getGaugeValue(string $name, array $labels = []): float
    {
        return 0.0;
    }
    
    public function observeSummary(string $name, float $value, array $labels = [], array $quantiles = []): void
    {
        // Implementation not needed for this test
    }
    
    public function getSummarySum(string $name, array $labels = []): float
    {
        return 0.0;
    }
    
    public function getSummaryCount(string $name, array $labels = []): int
    {
        return 0;
    }
    
    public function flushMetrics(): bool
    {
        return true;
    }

    // Expose protected methods for testing
    public function testValidateMetricName(string $name): string
    {
        return $this->validateMetricName($name);
    }

    public function testValidateLabelName(string $labelName): string
    {
        return $this->validateLabelName($labelName);
    }

    public function testNormalizeMetricName(string $name, string $type): string
    {
        return $this->normalizeMetricName($name, $type);
    }

    public function testGenerateLabelKey(string $baseKey, array $labels): string
    {
        return $this->generateLabelKey($baseKey, $labels);
    }

    public function testFormatValue($value): string
    {
        return $this->formatValue($value);
    }
}
