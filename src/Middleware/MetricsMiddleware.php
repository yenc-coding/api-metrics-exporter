<?php

declare(strict_types=1);

namespace SidcorpResource\ApiMetricsExporter\Middleware;

use SidcorpResource\ApiMetricsExporter\Contracts\MetricsCollectorInterface;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

/**
 * Middleware to collect API metrics for every request
 */
class MetricsMiddleware
{
    /**
     * Metrics collector instance
     *
     * @var MetricsCollectorInterface
     */
    protected MetricsCollectorInterface $collector;

    /**
     * Logger instance
     *
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;

    /**
     * Create a new middleware instance
     *
     * @param MetricsCollectorInterface $collector Metrics collector
     * @param LoggerInterface|null $logger Logger instance
     */
    public function __construct(MetricsCollectorInterface $collector, ?LoggerInterface $logger = null)
    {
        $this->collector = $collector;
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Handle an incoming request
     *
     * @param Request $request The request instance
     * @param Closure $next The next middleware
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Skip excluded paths
        if ($this->collector->shouldExcludePath($request->path(), $request->method())) {
            return $next($request);
        }

        // Start timing
        $startTime = microtime(true);

        // Process the request
        $response = $next($request);

        try {
            // Calculate request duration
            $duration = microtime(true) - $startTime;
            
            // Extract labels
            $labels = $this->extractLabels($request, $response);
            
            // Record metrics
            $this->collector->incrementCounter('api_requests_total', $labels);
            $this->collector->observeHistogram('api_request_duration_seconds', $duration, $labels);
            
            // Track unique users
            $userId = $this->getUserIdentifier($request);
            if ($userId) {
                $this->collector->trackUnique('users', $userId, $labels);
            }
        } catch (Throwable $e) {
            $this->logger->error('Failed to collect metrics', [
                'exception' => $e->getMessage(),
                'path' => $request->path(),
                'method' => $request->method(),
            ]);
            // Don't rethrow the exception - middleware should never break the application
        }

        return $response;
    }

    /**
     * Extract labels from request and response
     *
     * @param Request $request The request instance
     * @param Response $response The response instance
     * @return array<string, string> The labels
     */
    protected function extractLabels(Request $request, $response): array
    {
        $route = $request->route();
        $routeName = $route ? ($route->getName() ?? $this->getRouteActionName($route)) : 'unknown';
        
        $statusCode = $response instanceof Response ? $response->getStatusCode() : 200;
        
        $userId = $this->getUserIdentifier($request) ?? 'guest';
        
        return [
            'endpoint' => $this->normalizeEndpoint($request->path()),
            'method' => $request->method(),
            'status' => (string)$statusCode,
            'user_id' => (string)$userId,
            'route' => $routeName,
        ];
    }

    /**
     * Get the route action name
     *
     * @param mixed $route The route instance
     * @return string The route action name
     */
    protected function getRouteActionName($route): string
    {
        $action = $route->getAction('controller') ?? $route->getAction()['uses'] ?? null;
        
        if (is_string($action) && $action) {
            return $action;
        }
        
        // Fallback to the URI pattern
        $uri = $route->uri();
        return $uri ?? 'unknown';
    }
    
    /**
     * Normalize the endpoint path for consistent metrics
     *
     * @param string $path The request path
     * @return string Normalized path
     */
    protected function normalizeEndpoint(string $path): string
    {
        // Remove trailing slash
        $path = rtrim($path, '/');
        
        // Add leading slash if missing
        if (!str_starts_with($path, '/') && !empty($path)) {
            $path = '/' . $path;
        }
        
        // Use root path indicator if empty
        if (empty($path)) {
            $path = '/';
        }
        
        return $path;
    }

    /**
     * Get user identifier for metrics
     *
     * @param Request $request The request instance
     * @return string|null User identifier
     */
    protected function getUserIdentifier(Request $request): ?string
    {
        // Try to get authenticated user ID
        $user = Auth::user();
        if ($user && method_exists($user, 'getAuthIdentifier')) {
            return (string)$user->getAuthIdentifier();
        }
        
        // Fallback to IP address
        $ip = $request->ip();
        if ($ip) {
            return 'ip:' . $ip;
        }
        
        return null;
    }
}
