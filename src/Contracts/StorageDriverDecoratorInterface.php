<?php

declare(strict_types=1);

namespace SidcorpResource\ApiMetricsExporter\Contracts;

/**
 * Interface for storage driver decorators
 */
interface StorageDriverDecoratorInterface extends StorageDriverInterface
{
    /**
     * Get the decorated storage driver
     *
     * @return StorageDriverInterface
     */
    public function getDecoratedDriver(): StorageDriverInterface;
}
