# How to Integrate Metrics with Grafana

This document explains how to integrate the API Metrics Exporter with Prometheus and Grafana.

## Installing Prometheus

```sh
# Run Prometheus with Docker
docker run -d --name prometheus -p 9090:9090 -v $(pwd)/prometheus.yml:/etc/prometheus/prometheus.yml prom/prometheus
```

Sample `prometheus.yml` configuration:

```yaml
global:
  scrape_interval: 15s

scrape_configs:
  - job_name: 'laravel'
    metrics_path: '/metrics'
    basic_auth:
      username: 'username'  # Replace with your actual username
      password: 'password'  # Replace with your actual password
    static_configs:
      - targets: ['host.docker.internal:8000']  # URL of your Laravel app
```

## Installing Grafana

```sh
# Run Grafana with Docker
docker run -d --name grafana -p 3000:3000 grafana/grafana
```

## Common Prometheus Queries

| Goal | Prometheus Query |
|------|------------------|
| Total requests by endpoint | `sum by (endpoint) (api_requests_total)` |
| Request rate (req/s) | `sum(rate(api_requests_total[5m])) by (endpoint)` |
| Response time (p95) | `histogram_quantile(0.95, sum(rate(api_request_duration_seconds_bucket[5m])) by (le, endpoint))` |
| Error rate | `sum(rate(api_requests_total{status=~"5.*"}[5m])) by (endpoint) / sum(rate(api_requests_total[5m])) by (endpoint)` |
| Number of active users | `count(count by (user_id)(api_requests_total))` |
| Number of users per endpoint | `count by (endpoint)(unique_users_total)` |

## Import Dashboard

1. Go to Grafana (http://localhost:3000)
2. Log in (default: admin/admin)
3. Go to **+ > Import**
4. Upload the `dashboard.json` file or paste the JSON
5. Select the Prometheus data source
6. Click **Import**

You can find a sample JSON file at `grafana-dashboard.json` in this directory.

# Grafana Integration

This document provides guidance on integrating your Laravel API Metrics Exporter metrics with Grafana dashboards using Prometheus as a data source.

## Steps

1. **Add Prometheus as a Data Source in Grafana**
   - Go to Grafana > Configuration > Data Sources > Add data source > Prometheus.
   - Enter your Prometheus server URL and save.

2. **Import or Create Dashboards**
   - Use the provided `grafana-dashboard.json` as a starting point.
   - You can import this file directly into Grafana or create your own panels and queries.

3. **Example Queries**

- **Request Rate:**
  ```
  sum by(endpoint) (rate(api_requests_total[5m]))
  ```
- **95th Percentile Response Time:**
  ```
  histogram_quantile(0.95, sum(rate(api_request_duration_seconds_bucket[5m])) by (le, endpoint))
  ```
- **Error Rate:**
  ```
  sum by(endpoint) (rate(api_requests_total{status=~"5.."}[5m])) / sum by(endpoint) (rate(api_requests_total[5m])) * 100
  ```
- **Unique Users (per endpoint per day):**
  ```
  sum by(endpoint, date) (unique_users_total)
  ```

## Tips

- Use variables in Grafana to filter by endpoint, method, or status.
- Set appropriate time ranges and refresh intervals for your dashboards.
- Refer to the official Grafana and Prometheus documentation for advanced usage.

---

For more information, see:
- [Grafana Documentation](https://grafana.com/docs/)
- [Prometheus Documentation](https://prometheus.io/docs/introduction/overview/)
