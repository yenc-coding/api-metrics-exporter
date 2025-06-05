# Project Instructions

This document provides development and contribution guidelines for the Laravel API Metrics Exporter project.

## Getting Started

- Clone the repository and install dependencies:
  ```bash
  git clone <repo-url>
  cd sidcorp-metrics
  composer install
  ```
- Run tests to verify your environment:
  ```bash
  composer test
  ```

## Directory Structure

- `src/` — Main library source code
- `tests/` — Unit and integration tests
- `config/` — Configuration files
- `.docs/` — Documentation and integration guides

## Coding Standards

- Follow [PSR-12](https://www.php-fig.org/psr/psr-12/) for PHP code.
- Use snake_case for metric and label names.
- Write clear docblocks for public classes and methods.

## Documentation

- Update documentation in `.docs/` as needed.
- Keep `README.md` and configuration examples up to date.

## Versioning

- This project follows [Semantic Versioning](https://semver.org/).

## License

- All contributions are licensed under the MIT License.

---

For more information, see the main `README.md` and the `.docs` directory.