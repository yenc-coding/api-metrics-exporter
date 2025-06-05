<?php

namespace SidcorpResource\ApiMetricsExporter\Console;

use Illuminate\Console\Command;
use SidcorpResource\ApiMetricsExporter\MetricsCollector;

class FlushMetricsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api-metrics:flush {--driver= : The storage driver to use (redis, memory)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Flush all stored API metrics data';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $driver = $this->option('driver') ?: config('api-metrics.driver', 'redis');
        
        $this->info("Flushing metrics for driver: {$driver}");
        
        $collector = new MetricsCollector($driver, config('api-metrics'));
        
        $result = $collector->flushMetrics();
        
        if ($result) {
            $this->info('Successfully flushed metrics data.');
            return 0;
        } else {
            $this->error('Failed to flush metrics data. Check the logs for details.');
            return 1;
        }
    }
}
