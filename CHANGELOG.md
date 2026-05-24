# Changelog

All notable changes to `laravel-plenum` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project aims to follow [Semantic Versioning](https://semver.org/spec/v2.0.0.html)
once it reaches 1.0.

## [Unreleased]

## [0.1.0] - 2026-05-24

Initial **experimental** release. Public API, configuration keys, and runtime
behaviour may change without notice between 0.x point releases.

### Added
- Consistent-hash router (`Plenum`) that maps a per-request routing key onto a
  pool of healthy backend connections.
- `RoutingStrategy` contract with five built-in strategies: `AuthUserStrategy`,
  `SessionOnlyStrategy`, `TenantStrategy`, `CallbackStrategy`, `CompositeStrategy`.
- `ConnectionDriver` contract with built-in `DatabaseDriver`, `RedisDriver`, and
  `NullDriver`.
- `HealthChecker` contract and a cache-backed `PingHealthChecker` that
  delegates to each driver's `ping()` and shares state across workers.
- `RouteRequest` middleware that activates the routed connection on every
  request and optionally emits `X-Plenum-{driver}` and `X-Plenum-Strategy`
  debug headers.
- Service-provider wiring that auto-creates Laravel `database.connections.*`
  and `database.redis.*` entries from a template, without overriding
  user-defined ones.
- Failover-aware `Plenum::execute()` that retries on the driver's declared
  failover exceptions and dispatches a `FailoverOccurred` event when it kicks in.
- `NodeMarkedDown` / `NodeRecovered` events fired on cache-state transitions.
- Artisan commands: `plenum:diagnose`, `plenum:distribution`,
  `plenum:probe` (with `--watch` mode for supervisord).
- `Plenum::redis()` facade helper that resolves the currently-routed Redis
  connection.
- Test helpers: `Vented\Plenum\Testing\FakeStrategy`,
  `Vented\Plenum\Testing\FakeHealthChecker`.
- 100 %-coverage Pest test suite and GitHub Actions workflows for tests,
  PHPStan, and PHP-CS-Fixer.

[Unreleased]: https://github.com/Vented-Labs/laravel-plenum/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/Vented-Labs/laravel-plenum/releases/tag/v0.1.0
