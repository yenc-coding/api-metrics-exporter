# Metrics Naming Conventions and Standards

For official and up-to-date Prometheus metric naming conventions and best practices, please refer to the Prometheus documentation:

- https://prometheus.io/docs/practices/naming/

This document previously outlined local conventions, but the project now defers to the official Prometheus documentation for all naming, labeling, and exposition standards.

---

## Key Points (from Prometheus)

- Use snake_case for all metric and label names.
- Include base units (e.g., `_seconds`, `_bytes`, `_total`) as suffixes in metric names.
- Avoid high-cardinality labels (such as user IDs, emails, or unbounded sets).
- Do not encode label names or values in the metric name.
- Use labels for dimensions (endpoint, method, status, etc.).
- Always use base units (seconds, bytes, meters, etc.).

For rationale and more examples, see the [official Prometheus documentation](https://prometheus.io/docs/practices/naming/).

---

_Last updated: June 2025_
