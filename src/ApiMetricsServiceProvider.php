<?php

declare(strict_types=1);

namespace SidcorpResource\ApiMetricsExporter;

use SidcorpResource\ApiMetricsExporter\Contracts\MetricsCollectorInterface;
use SidcorpResource\ApiMetricsExporter\Contracts\ExporterInterface;
use SidcorpResource\ApiMetricsExporter\Contracts\StorageDriverInterface;
use SidcorpResource\ApiMetricsExporter\MetricsCollector;
use SidcorpResource\ApiMetricsExporter\MetricsExporter;
use SidcorpResource\ApiMetricsExporter\StorageDriverFactory;
use SidcorpResource\ApiMetricsExporter\Exporter\PrometheusExporter;
use SidcorpResource\ApiMetricsExporter\Middleware\MetricsMiddleware;
use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;

/**
 * Service provider for API metrics exporter
 */
class ApiMetricsServiceProvider extends ServiceProvider
{
    /**
     * Register services
     *
     * @return void
     */
    public function register(): void
    {
        // Merge config
        $this->mergeConfigFrom(
            __DIR__ . '/../config/api-metrics.php',
            'api-metrics'
        );
        
        // Register metrics collector
        $this->app->singleton(MetricsCollectorInterface::class, function ($app) {
            $config = $app['config']['api-metrics'];
            $logger = $app->make('log');
            
            return new MetricsCollector(
                $config['driver'] ?? 'redis',
                $config,
                $logger
            );
        });
        
        // Register metrics collector as concreate class too
        $this->app->singleton(MetricsCollector::class, function ($app) {
            return $app->make(MetricsCollectorInterface::class);
        });
        
        // Register exporter interface
        $this->app->singleton(ExporterInterface::class, function ($app) {
            $collector = $app->make(MetricsCollectorInterface::class);
            $logger = $app->make('log');
            
            return new PrometheusExporter($collector->getDriver(), $logger);
        });
        
        // Register metrics exporter
        $this->app->singleton(MetricsExporter::class, function ($app) {
            $collector = $app->make(MetricsCollectorInterface::class);
            $logger = $app->make('log');
            $authConfig = $app['config']['api-metrics.basic_auth'] ?? [];
            
            return new MetricsExporter($collector, $logger, $authConfig);
        });
        
        // Register middleware
        $this->app->singleton(MetricsMiddleware::class, function ($app) {
            $collector = $app->make(MetricsCollectorInterface::class);
            $logger = $app->make('log');
            
            return new MetricsMiddleware($collector, $logger);
        });
    }

    /**
     * Bootstrap services
     *
     * @return void
     */
    public function boot(): void
    {
        // Publish config
        $this->publishes([
            __DIR__ . '/../config/api-metrics.php' => config_path('api-metrics.php'),
        ], 'config');
        
        // Register routes
        $this->registerRoutes();
        
        // Register middleware alias
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('api-metrics', MetricsMiddleware::class);
        
        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                \SidcorpResource\ApiMetricsExporter\Console\FlushMetricsCommand::class,
            ]);
        }
    }

    /**
     * Register routes for metrics endpoint
     *
     * @return void
     */
    protected function registerRoutes(): void
    {
        // Get configuration
        $config = $this->app['config']['api-metrics'];
        $path = $config['endpoint']['path'] ?? 'metrics';
        $middleware = $config['endpoint']['middleware'] ?? ['api'];
        
        // Register the metrics endpoint
        $router = $this->app->make(Router::class);
        $router->get($path, function (\Illuminate\Http\Request $request) {
            return app(MetricsExporter::class)->handleRequest($request);
        })
            ->middleware($middleware)
            ->name('api-metrics.export');
    }
}
