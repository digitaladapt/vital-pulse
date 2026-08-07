# Contributing to VitalPulse

Thank you for your interest in contributing to VitalPulse! This document outlines how to report bugs, request features, set up a development environment, and submit pull requests.

---

## Reporting Bugs

Before submitting a bug report:

1. **Search existing issues** to avoid duplicates.
2. Verify the bug exists on the latest `main` branch.

When filing a bug report, please include:

- **Summary** — a clear description of the problem.
- **Steps to reproduce** — the minimal sequence to trigger the bug.
- **Expected behavior** — what you expected to happen.
- **Actual behavior** — what actually happened.
- **Environment** — PHP version, OS, browser (if frontend-related).
- **Logs/error output** — any relevant stack traces or API responses.

---

## Requesting Features

Feature requests are welcome! Please:

1. Check existing issues for similar requests.
2. Open a new issue with the `enhancement` label.
3. Describe the use case and the proposed solution.
4. If possible, sketch out the API or UI changes you envision.

---

## Development Setup

### Prerequisites

- PHP 8.3+
- Composer
- SQLite3

### Clone & Install

```bash
git clone https://github.com/your-user/vital-pulse.git
cd vital-pulse
composer install
```

### Configure Test Environment

The test environment is pre-configured in `.env.test`:

```env
APP_ENV=test
APP_SECRET=test_secret_for_unit_tests
DEFAULT_URI=http://localhost
DATABASE_URL="sqlite:///:memory:"
API_KEY=test_api_key_12345
```

No additional configuration is needed to run tests — the test suite uses an in-memory SQLite database.

### Start the Dev Server

```bash
php -S 0.0.0.0:9000 -t public/
```

---

## Running Tests

```bash
# Full suite
php vendor/bin/phpunit

# Verbose / testdox output
php vendor/bin/phpunit --testdox

# Run a specific test class
php vendor/bin/phpunit tests/Controller/HealthApiControllerTest.php
```

All tests must pass before submitting a pull request.

---

## Code Style

- **PSR-12** — follow the [PSR-12 Extended Coding Style](https://www.php-fig.org/psr/psr-12/).
- **PHP 8.3+** — use modern PHP features (typed properties, readonly, enums, etc.).
- Use `declare(strict_types=1);` at the top of every PHP file.
- Keep methods small and focused.
- Add PHPDoc blocks for public methods where the signature isn't self-explanatory.

You can check your code style with:

```bash
php vendor/bin/php-cs-fixer fix --dry-run --diff
```

---

## Pull Request Process

1. **Fork** the repository and clone your fork.
2. **Create a branch** from `main`:
   ```bash
   git checkout -b feature/my-new-feature
   ```
   Use a descriptive branch name prefixed by type: `feature/`, `fix/`, `docs/`, `refactor/`.
3. **Write code** and add or update tests as needed.
4. **Run the test suite**:
   ```bash
   php vendor/bin/phpunit
   ```
   Ensure all 37 tests pass.
5. **Commit your changes** (see commit conventions below).
6. **Push** to your fork and open a pull request against `main`.
7. **Describe your changes** in the PR description — what changed, why, and any breaking changes.

A maintainer will review your PR. Please be responsive to feedback.

---

## Commit Message Conventions

This project follows [Conventional Commits](https://www.conventionalcommits.org/). Each commit message should be structured as:

```
<type>(<scope>): <description>

[optional body]

[optional footer]
```

### Types

| Type       | Use for                                          |
|------------|--------------------------------------------------|
| `feat`     | A new feature                                    |
| `fix`      | A bug fix                                        |
| `docs`     | Documentation-only changes                       |
| `style`    | Code style changes (formatting, no logic change) |
| `refactor` | Code refactoring without behavior change         |
| `test`     | Adding or modifying tests                        |
| `chore`    | Build tooling, dependencies, config              |
| `ci`       | CI/CD pipeline changes                           |

### Examples

```
feat(api): add weight tracking to health log endpoint
fix(dashboard): correct chart tooltip date formatting
docs(readme): update API reference table
test(controller): add edge case for missing measurements
```

---

## Questions?

Feel free to open an issue with the `question` label if anything is unclear. Happy coding!
