# Laravel Server Info

[![CI](https://github.com/aporat/laravel-server-info/actions/workflows/ci.yml/badge.svg)](https://github.com/aporat/laravel-server-info/actions/workflows/ci.yml)
[![Latest Stable Version](https://poser.pugx.org/aporat/laravel-server-info/v/stable)](https://packagist.org/packages/aporat/laravel-server-info)
[![License](https://poser.pugx.org/aporat/laravel-server-info/license)](https://packagist.org/packages/aporat/laravel-server-info)

A Laravel package that reports server and environment information (PHP runtime, Laravel application, operating system, disk usage, service drivers, database and Redis server versions, and installed packages) with one Artisan command. Useful for debugging, support tickets and deployment checks.

## Features

- `php artisan server:info` with an `about`-style view, a flat `module.key value` view, and JSON output
- Eight built-in modules, all cheap to run and safe to print: no passwords, credentials, DSNs or environment values
- One failing module never hides the others; the command reports it and exits non-zero
- Modules are plain classes resolved through the container, so `config:cache` always works
- Add modules from config, from a service provider (`ServerInfo::extend()`), or with a container tag

## Requirements

- PHP 8.4 or 8.5
- Laravel 12.x or 13.x

## Installation

```bash
composer require aporat/laravel-server-info
```

The service provider and the `ServerInfo` facade are registered automatically.

To change the module list or module options, publish the config file:

```bash
php artisan vendor:publish --provider="Aporat\ServerInfo\ServerInfoServiceProvider" --tag="config"
```

## Usage

```bash
php artisan server:info              # every module, grouped by module
php artisan server:info database     # one module
php artisan server:info --flat       # one "module.key: value" line per value
php artisan server:info --json       # nested JSON
php artisan server:info --json --flat
```

### Default view

Each module is a section, like `php artisan about`. Values inside a group are labelled with their path in the module (`opcache.enabled`, `storage.free`):

```
  php ..............................................................................
  version ................................................................... 8.4.26
  sapi ......................................................................... cli
  memory_limit ................................................................ 128M
  max_execution_time ............................................................. 0
  upload_max_filesize ........................................................... 2M
  post_max_size ................................................................. 8M
  timezone ..................................................................... UTC
  opcache.enabled ............................................................. true
  opcache.memory_used ...................................................... 9.4 MiB
  opcache.memory_free .................................................... 118.6 MiB
  opcache.jit ................................................................. true
  extensions  Core, ctype, curl, date, dom, fileinfo, filter, hash, iconv, json, ...

  laravel ..........................................................................
  version .................................................................. 13.34.0
  env ................................................................... production
  debug ...................................................................... false
  ...

  database .........................................................................
  mysql.driver ............................................................... mysql
  mysql.status .................................................................. ok
  mysql.version ............................................................... 8.4.3
```

### Flat view (`--flat`)

The v1 format: one line per value, keyed `module.key`. Groups are flattened with dots; lists are printed as JSON.

```
php.version: 8.4.26
php.opcache.enabled: true
php.extensions: ["Core","ctype","curl",...]
disk.storage.free: 106.0 GiB
database.mysql.version: 8.4.3
```

### JSON (`--json`)

`--json` prints the nested report; `--json --flat` prints the flat keys as a JSON object. Values keep their types (`true`, `null`, numbers, lists).

```json
{
    "php": {
        "version": "8.4.26",
        "opcache": { "enabled": true, "jit": true, "memory_used": "9.4 MiB", "memory_free": "118.6 MiB" }
    },
    "redis": { "client": "phpredis", "status": "not in use" }
}
```

### Errors and exit codes

Each module runs on its own. If a module's `info()` throws, the command prints the error in that module's place (an `Error` row, a `module.error` line, or `{"error": "..."}` in JSON), still prints every other module, and exits with status **1**. The error shows the exception class and message, so custom modules should not put secrets in exception messages.

`server:info <module>` takes an exact module name. An unknown name prints the available modules and exits with status 1. Only the selected module is run.

Problems with the module list itself (a class that does not exist or is not a module, a duplicate or invalid module name, a closure in the config) are configuration errors: they throw an `InvalidArgumentException` naming the entry.

The built-in modules do not throw for unavailable services: an unreachable database or Redis server is reported as `status: unreachable`, which is information, not a module failure.

### In code

```php
use Aporat\ServerInfo\Facades\ServerInfo;

ServerInfo::all();          // ['php' => ['version' => '8.4.26', ...], ...]; rethrows a module's exception
ServerInfo::flat();         // ['php.version' => '8.4.26', ...]; rethrows a module's exception
$report = ServerInfo::collect();         // never throws for a failing module
$report = ServerInfo::collect('disk');   // only the disk module

$report->data();      // info of the modules that succeeded
$report->errors();    // ['broken' => Throwable]
$report->hasErrors();
$report->toArray();   // nested, failed modules as ['error' => '...']
$report->flat();      // flat, failed modules as 'module.error'
```

`ServerInfo` is a facade for `Aporat\ServerInfo\ModuleRegistry`, which you can also inject.

## Built-in modules

All modules are enabled by default. Each one only reads local state or opens one short, time-limited connection, and never prints credentials, hosts, DSNs, URLs or environment variable values.

| Module | Name | Reports |
| --- | --- | --- |
| `PhpModule` | `php` | version, SAPI, `memory_limit`, `max_execution_time`, `upload_max_filesize`, `post_max_size`, default timezone, OPcache (`enabled`, `memory_used`, `memory_free`, `jit`), sorted extension list |
| `LaravelModule` | `laravel` | framework version, environment, `debug` (boolean), app name, timezone, locale, `maintenance_mode`, `config_cached`, `routes_cached`, `events_cached` |
| `SystemModule` | `system` | OS, OS family, kernel release, machine type, hostname, CPU count, load average, uptime, container runtime, cgroup memory limit |
| `DiskModule` | `disk` | for each path (storage and base by default): `path`, `total`, `free`, `used`, `used_percent`, and the same sizes in bytes (`total_bytes`, `free_bytes`, `used_bytes`) |
| `DriversModule` | `drivers` | default cache store, queue connection, session driver, mailer, filesystem disk, broadcaster, log channel and database connection names |
| `DatabaseModule` | `database` | for each probed connection: `driver`, `status` (`ok` / `unreachable` / `not configured`), server `version`, and a redacted `error` |
| `RedisModule` | `redis` | client (`phpredis` / `predis`), and for each probed connection `status`, server `version` and `mode` (standalone, cluster, sentinel), or a redacted `error` |
| `PackagesModule` | `packages` | root package name, version and commit, and the installed version of each configured package (`not installed` if absent) |

Notes:

- **SystemModule** reads `/proc/cpuinfo`, `/proc/uptime`, `/proc/1/cgroup` and `/sys/fs/cgroup` directly and never runs shell commands. Values that cannot be read (for example on macOS or Windows) are `null`. The container runtime is `docker` (`/.dockerenv`), `podman` (`/run/.containerenv`), `kubernetes` (the `KUBERNETES_SERVICE_HOST` variable exists; its value is not read), a runtime found in `/proc/1/cgroup`, or `none`. The memory limit is the cgroup v2 `memory.max` or cgroup v1 `memory.limit_in_bytes`, `unlimited` when there is none, and `null` when no cgroup memory controller is visible.
- **DatabaseModule** probes only the default connection unless configured. Each probe opens a separate connection (the application's own connections are untouched) with a 2-second `PDO::ATTR_TIMEOUT`, reads `PDO::ATTR_SERVER_VERSION` and disconnects. Errors are reduced to the exception class and SQLSTATE/driver code (`PDOException [2002]`); exception messages, which can contain hosts and usernames, are never printed. Requires `illuminate/database` (part of `laravel/framework`).
- **RedisModule** only connects when asked to or when it matters: by default it probes the `default` Redis connection only if the default cache store, queue connection, session driver or broadcaster uses Redis, and otherwise reports `status: not in use`. Probes use their own short-lived client with a 2-second connect and read timeout and run `INFO server`. If the configured client is missing (no `redis` extension for phpredis, no `predis/predis` for predis) it reports `status: unavailable` with the reason. Errors are redacted like the database module's.
- **DiskModule** prints paths, not their contents. A path that cannot be measured is reported as `status: unavailable`.
- In the default view, paths under the application's base path are shown relative to it (Laravel's console components do this); `--flat` and `--json` show full paths.

## Configuration

`config/server-info.php`:

```php
return [
    // Class names only, in display order.
    'modules' => [
        PhpModule::class,
        LaravelModule::class,
        SystemModule::class,
        DiskModule::class,
        DriversModule::class,
        DatabaseModule::class,
        RedisModule::class,
        PackagesModule::class,
    ],

    // label => path; null = ['storage' => storage_path(), 'base' => base_path()]
    'disk' => ['paths' => null],

    // connection names from config/database.php; null = only the default connection
    'database' => ['connections' => null, 'timeout' => 2],

    // Redis connection names; null = "default", only if cache/queue/session/broadcast use Redis
    'redis' => ['connections' => null, 'timeout' => 2],

    // packages whose installed version is reported, besides the root package
    'packages' => ['laravel/framework'],
];
```

Remove a module from `modules` to disable it, for example `DatabaseModule` if you do not want `server:info` to open database connections.

Only class names are accepted in `modules`, so the file can always be cached with `php artisan config:cache`. Modules are resolved through the service container (constructor dependencies are injected) and only when `server:info` runs or the registry is queried, never while the application boots.

## Writing a module

A module implements `Aporat\ServerInfo\Contracts\ModuleInterface`:

```php
interface ModuleInterface
{
    /** Unique, non-empty, without dots. */
    public function name(): string;

    /** @return array<string, scalar|null|array<mixed>> */
    public function info(): array;
}
```

`info()` returns key/value pairs. A value is a scalar, `null`, a list (printed comma-separated, or as JSON in `--flat`), or an associative array (a nested group, flattened with dots in `--flat`).

```php
namespace App\ServerInfo;

use Aporat\ServerInfo\Contracts\ModuleInterface;
use Illuminate\Contracts\Queue\Factory as Queue;

class QueueModule implements ModuleInterface
{
    public function __construct(private Queue $queue) {}

    public function name(): string
    {
        return 'queue';
    }

    public function info(): array
    {
        return [
            'default_size' => $this->queue->connection()->size(),
            'workers' => ['high' => 4, 'low' => 1],
        ];
    }
}
```

Do not return secrets: `server:info` output ends up in terminals, CI logs and support tickets.

### Registering a module

Pick one:

```php
// 1. config/server-info.php
'modules' => [
    // ...
    App\ServerInfo\QueueModule::class,
],
```

```php
// 2. From a service provider's boot() method. Nothing is built until server info is requested.
use Aporat\ServerInfo\Facades\ServerInfo;

ServerInfo::extend(\App\ServerInfo\QueueModule::class);
```

```php
// 3. With a container tag, handy for packages. Bind the class first if it needs custom construction.
$this->app->tag([\App\ServerInfo\QueueModule::class], 'server-info.modules');
```

Modules are listed in this order: config, tagged, `extend()`, then instances passed to `ModuleRegistry::register()`. Module names must be non-empty, must not contain a dot, and must be unique.

## Upgrade guide

The module redesign (v2) contains breaking changes for code written against earlier versions of this package.

1. **Closures are no longer allowed in `server-info.modules`.** They made `config:cache` fail. A closure in the config now throws an `InvalidArgumentException` explaining how to migrate. Move closure-built modules to a class, or register them from a service provider:

    ```php
    // Before (config/server-info.php)
    'modules' => [fn () => new MyModule('x')],

    // After (AppServiceProvider::register / boot)
    $this->app->bind(MyModule::class, fn () => new MyModule('x'));
    ServerInfo::extend(MyModule::class);   // or: $this->app->tag([MyModule::class], 'server-info.modules');
    ```

2. **`ModuleRegistry::extend()` accepts class names only**, not closures. Bind the class in the container if it needs custom construction (as above).

3. **`ModuleInterface::info()` must return an array.** Change the signature to `public function info(): array` and wrap single values, e.g. `return ['value' => $value];`. Objects are no longer part of the contract; return strings or arrays.

4. **`ModuleRegistry::all()` returns nested data** (`['php' => ['version' => ...]]`) instead of flat `'php.version'` keys. Use `ModuleRegistry::flat()` (or `ServerInfo::flat()`) for the old shape. Both rethrow a failing module's exception; use `collect()` to get a `Report` with failures isolated.

5. **The default command output changed** to a section per module. Use `server:info --flat` for the old `module.key: value` lines. Scripts that parse the output should switch to `--json`.

6. **`server:info <module>` matches module names exactly.** `server:info php.version` no longer works; use `server:info php --flat` and filter, or `--json`.

7. **A module that throws no longer aborts the command.** The error is printed in its place and the command exits with status 1.

8. **Built-in module changes:**
    - `laravel.debug` is a boolean (`true`/`false`), not the strings `'true'`/`'false'`.
    - `php.os` moved to `system.os`.
    - `php.extensions` is sorted.
    - `LaravelModule` takes its dependencies through the constructor; build it with `app(LaravelModule::class)` instead of `new LaravelModule`.

9. **New default modules.** A config file published from an earlier version lists only `PhpModule` and `LaravelModule`, so add the new modules to your published `config/server-info.php` (or delete it to use the package defaults). `DatabaseModule` opens a short connection to the default database when `server:info` runs; leave it out if you do not want that.

## Testing

```bash
composer test      # PHPUnit
composer check     # Pint (code style)
composer analyze   # PHPStan
```

## Contributing

Contributions are welcome! Please open an issue or a pull request.

1. Clone the repository
2. Install dependencies: `composer install`
3. Run `composer test`, `composer check` and `composer analyze`

## Security

If you discover any security-related issues, please email aporat28@gmail.com instead of using the issue tracker.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

## Credits

- [Adar Porat](https://github.com/aporat)
- [All Contributors](https://github.com/aporat/laravel-server-info/contributors)
