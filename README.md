# Laravel API Metrics Exporter

A robust, production-ready metrics collection library for Laravel 9+ APIs. Effortlessly collect Prometheus-compatible metrics for your API endpoints with zero configuration and seamless integration.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/sidcorp-resource/api-metrics-exporter.svg)](https://packagist.org/packages/sidcorp-resource/api-metrics-exporter)
[![PHP Version Support](https://img.shields.io/packagist/php-v/sidcorp-resource/api-metrics-exporter.svg)](https://packagist.org/packages/sidcorp-resource/api-metrics-exporter)
[![Laravel Version Support](https://img.shields.io/badge/laravel-%3E%3D9.0-red)](https://laravel.com/)
[![License](https://img.shields.io/packagist/l/sidcorp-resource/api-metrics-exporter.svg)](https://github.com/sidcorp-resource/api-metrics-exporter/blob/main/LICENSE)

---

## Features

- **Zero-configuration setup** – Works out of the box with sensible defaults
- **Automatic API metrics collection**:
  - Request counts by endpoint, method, status, and user
  - Response time histograms for percentile analysis
  - Daily unique user tracking
- **Prometheus OpenMetrics format** – Standard `/metrics` endpoint with proper naming conventions
- **Multiple storage backends**:
  - **Redis** (recommended for production, requires phpredis extension)
  - **InMemory** (for testing/development)
- **Production-ready** – Graceful error handling and lightweight middleware
- **Strict Prometheus compliance** – All metrics follow Prometheus naming conventions and exposition format

---

## Requirements

- PHP 8.1 or higher
- Laravel 9.0 or higher
- PHP Redis extension (ext-redis) for production use

---

## Quick Start

### 1. Install

```bash
composer require sidcorp-resource/api-metrics-exporter
```

### 2. Add Middleware

Add the middleware to your `app/Http/Kernel.php`:

```php
protected $middlewareGroups = [
    'api' => [
        // ... other middleware
        \SidcorpResource\ApiMetricsExporter\Middleware\MetricsMiddleware::class,
    ],
];
```

### 3. View Metrics

Visit `http://your-app.com/metrics` to see your metrics in Prometheus format.

The library will automatically start collecting metrics using Redis (with InMemory fallback if Redis is unavailable).

---

## Configuration (Optional)

You can customize the default configuration by publishing the config file:

```bash
php artisan vendor:publish --provider="SidcorpResource\ApiMetricsExporter\ApiMetricsServiceProvider" --tag="config"
```

Key configuration options in `config/api-metrics.php`:

```php
return [
    'driver' => env('API_METRICS_DRIVER', 'redis'),
    'endpoint' => [
        'path' => env('API_METRICS_ENDPOINT_PATH', 'metrics'),
        'middleware' => ['api'],
    ],
    'collection' => [
        'enabled' => env('API_METRICS_ENABLED', true),
        'excluded_paths' => ['health', 'metrics', 'debug*'],
    ],
    'redis' => [
        'connection' => 'default',
        'prefix' => 'api_metrics:',
        'ttl' => 86400,
    ],
];
```

---

## Securing the /metrics Endpoint

### HTTP Basic Authentication

You can enable HTTP Basic Auth for the `/metrics` endpoint to restrict access:

1. Set the following in your `.env` file:

    ```env
    API_METRICS_BASIC_AUTH_ENABLED=true
    API_METRICS_BASIC_AUTH_USERNAME=your_username
    API_METRICS_BASIC_AUTH_PASSWORD=your_password
    ```

2. The exporter will require these credentials for all requests to `/metrics`.

### TLS/HTTPS

> **Note:** TLS/HTTPS is handled by your web server (Nginx, Apache, etc.) or Laravel Valet/Homestead. The library does not manage TLS certificates directly.

### IP Whitelisting / Network Security

> For additional security, you should restrict access to `/metrics` at the firewall, load balancer, or web server level (e.g. allow-list Prometheus server IPs only).

---

## Prometheus Scrape Config Examples

### Basic Auth Example

```yaml
scrape_configs:
  - job_name: 'my-app'
    metrics_path: '/metrics'
    basic_auth:
      username: 'myuser'      # Must match API_METRICS_BASIC_AUTH_USERNAME
      password: 'mypassword'  # Must match API_METRICS_BASIC_AUTH_PASSWORD
    static_configs:
      - targets: ['myapp.example.com:443']
```

---

## Usage

### Default Metrics

The middleware automatically collects:

- `api_requests_total` – Total requests by endpoint/method/status/user
- `api_request_duration_seconds` – Response time histogram
- `unique_users_total` – Daily unique users per endpoint

### Custom Metrics

Currently, the library does not support registering custom drivers, exporters, or decorators via API. These features may be considered for future releases.

---

### Dependency Injection

You can inject the collector into your controller:

```php
use SidcorpResource\ApiMetricsExporter\Contracts\MetricsCollectorInterface;

class CustomController extends Controller
{
    public function __construct(private MetricsCollectorInterface $metrics) {}

    public function index()
    {
        $this->metrics->incrementCounter('custom_actions_total');
        // Your logic...
    }
}
```

---

## Monitoring & Grafana

### Prometheus Configuration

```yaml
scrape_configs:
  - job_name: 'laravel_api'
    scrape_interval: 15s
    metrics_path: '/metrics'
    static_configs:
      - targets: ['your-api-host:80']
```

### Example Grafana Queries

**Request Rate:**
```
sum by(endpoint) (rate(api_requests_total[5m]))
```

**95th Percentile Response Time:**
```
histogram_quantile(0.95, sum(rate(api_request_duration_seconds_bucket[5m])) by (le, endpoint))
```

**Error Rate:**
```
sum by(endpoint) (rate(api_requests_total{status=~"5.."}[5m])) / sum by(endpoint) (rate(api_requests_total[5m])) * 100
```

**Unique Users (per endpoint per day):**
```
sum by(endpoint, date) (unique_users_total)
```

---

## Management

### Flush Metrics

```bash
# Reset all metrics data
php artisan api-metrics:flush

# Flush specific driver
php artisan api-metrics:flush --driver=redis
```

Or programmatically:

```php
use SidcorpResource\ApiMetricsExporter\Facades\Metrics;

$success = Metrics::flushMetrics();
```

---

## Troubleshooting

If you encounter errors related to Redis (e.g., `Class "Redis" not found`), ensure the PHP Redis extension is installed:

```bash
# Ubuntu/Debian
sudo apt-get install php-redis

# CentOS/RHEL
sudo yum install php-redis

# macOS with Homebrew
brew install php-redis
pecl install redis
```

Then restart your web server and verify the extension is loaded with:

```bash
php -m | grep redis
```

---

## Environment Check

Create a simple compatibility check:

```php
<?php
echo "PHP Version: " . PHP_VERSION . " (>= 8.1.0 required)\n";
echo "Redis: " . (extension_loaded('redis') ? "✓" : "✗ REQUIRED") . "\n";
```

---

## FAQ

**Does this affect performance?**  
Minimal impact. The middleware is lightweight and fails gracefully.

**What about high-traffic APIs?**  
Redis driver handles high traffic and supports horizontal scaling.

**How to exclude routes?**  
Set `excluded_paths: ['health', 'debug*']` in config (supports wildcards).

**Is phpredis extension required?**  
Yes, as of version 2.0, the phpredis extension is required for the Redis driver.

---

## Metric Naming Conventions

This library strictly follows Prometheus metric naming conventions to ensure compatibility with monitoring tools. See details in [Metrics Conventions Documentation](.docs/METRICS_CONVENTIONS.md).

---

## Testing

```bash
composer install
composer test
```

---

## Documentation

Detailed documentation can be found in the `.docs` directory:

- [Metrics Conventions](METRICS_CONVENTIONS.md) - Naming conventions and standards
- [Grafana Integration](GRAFANA-INTEGRATION.md) - Setting up Grafana dashboards
- [Project Instructions](PROJECT_INSTRUCTION.md) - Development guidelines

---

## Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.

---

## License

The MIT License (MIT). See [License File](LICENSE) for more information.

---

## Versioning

**From version v1.0.0, this project strictly follows [Semantic Versioning](https://semver.org/) (semver): v1.0.0, v1.0.1, v1.1.0, v2.0.0, ...**

---

_This library is developed and maintained by SidCorp IT Department._