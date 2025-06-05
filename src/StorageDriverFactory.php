<?php

declare(strict_types=1);

namespace SidcorpResource\ApiMetricsExporter;

use SidcorpResource\ApiMetricsExporter\Contracts\StorageDriverInterface;
use SidcorpResource\ApiMetricsExporter\Drivers\RedisDriver;
use SidcorpResource\ApiMetricsExporter\Drivers\InMemoryDriver;
use SidcorpResource\ApiMetricsExporter\Drivers\FileDriver;
use SidcorpResource\ApiMetricsExporter\Exceptions\StorageDriverException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Illuminate\Support\Facades\Redis;

/**
 * Factory for creating storage drivers
 */
class StorageDriverFactory
{
    /**
     * Create a storage driver instance
     *
     * @param string $driver Driver type ('redis', 'memory', 'file')
     * @param array<string, mixed> $config Configuration options
     * @param LoggerInterface|null $logger Logger instance
     * @return StorageDriverInterface
     * 
     * @throws StorageDriverException If the driver type is invalid
     */
    public static function create(string $driver, array $config = [], ?LoggerInterface $logger = null): StorageDriverInterface
    {
        $logger = $logger ?? new NullLogger();
        
        switch (strtolower($driver)) {
            case 'redis':
                return new RedisDriver(
                    $config['redis']['connection'] ?? 'default',
                    $config['redis']['prefix'] ?? 'api_metrics:',
                    $config['redis']['ttl'] ?? 86400,
                    $logger,
                    $config['redis']['client'] ?? 'phpredis'
                );
                
            case 'memory':
            case 'in_memory':
            case 'inmemory':
                return new InMemoryDriver($logger);
                
            case 'file':
                return new FileDriver(
                    $config['file']['path'] ?? storage_path('metrics'),
                    $config['file']['prefix'] ?? 'metrics_',
                    $logger
                );
                
            default:
                throw new StorageDriverException("Unsupported driver type: {$driver}");
        }
    }
}
