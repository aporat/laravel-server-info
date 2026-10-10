# Changelog

All notable changes to this project are documented in this file.

## Unreleased

Module redesign (v2). Contains breaking changes; see the upgrade guide in the README.

### Breaking

- `server-info.modules` accepts module class names only. A closure in the config throws an `InvalidArgumentException` with migration instructions, so the config is always cacheable.
- `ModuleRegistry::extend()` accepts class names only (no closures).
- `ModuleInterface::info()` now returns `array<string, scalar|null|array>`; the scalar/mixed return form was removed.
- `ModuleRegistry::all()` returns data nested by module. The previous flat `module.key` shape is available from the new `ModuleRegistry::flat()`.
- `server:info` prints a section per module (like `php artisan about`) by default. The previous `module.key: value` lines moved to `--flat`.
- `server:info {module}` matches the module name exactly; dotted keys are no longer accepted as a filter.
- `laravel.debug` is a boolean instead of the strings `'true'`/`'false'`.
- `php.os` moved to the new `system` module; `php.extensions` is sorted.
- `LaravelModule` receives the application and config through its constructor.
- `ModuleRegistry::register()` returns the registry instead of `void`.

### Added

- `--json` option (nested, or flat with `--flat`) and `--flat` option for `server:info`.
- Per-module error isolation: a module that throws is reported in its place, the other modules still run, and the command exits with status 1. `ModuleRegistry::collect()` returns a `Report` with the data and errors.
- `ServerInfo` facade (auto-registered alias) with `extend()`, `modules()`, `collect()`, `all()` and `flat()`.
- Modules tagged `server-info.modules` in the container are loaded (`ModuleRegistry::TAG`).
- `PhpModule`: `memory_limit`, `max_execution_time`, `upload_max_filesize`, `post_max_size`, timezone and OPcache status (enabled, memory, JIT).
- `LaravelModule`: locale, maintenance mode and config/routes/events cached flags.
- New modules, enabled by default: `SystemModule` (OS, kernel, hostname, CPUs, load, uptime, container runtime, cgroup memory limit), `DiskModule` (usage of the storage and base paths), `DriversModule` (default cache/queue/session/mail/filesystem/broadcast/log/database drivers), `DatabaseModule` (driver and server version per connection, time-limited probe, redacted errors), `RedisModule` (server version and mode, only probed when Redis is in use, redacted errors), `PackagesModule` (root package and configured package versions).
- Config options `disk.paths`, `database.connections`, `database.timeout`, `redis.connections`, `redis.timeout` and `packages`.

### Fixed (#29)

- Modules are resolved lazily through the container and validated (names must be non-empty, dot-free and unique).
- Values are escaped in console output and rendered without fatal errors; an unknown module exits with status 1.
