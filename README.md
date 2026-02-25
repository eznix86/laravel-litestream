# Litestream for Laravel

![Litestream for Laravel](./art/litestream-for-laravel.png)

[Litestream](https://litestream.io/) is a streaming replication tool for SQLite.

## Introduction

This package is built for SQLite-first Laravel apps and will continuously stream SQLite changes to your preferred cloud storage or local files.

This will help you to quickly recover to the point of failure if your server goes down.

Litestream itself runs as a separate process and does not require application-level replication code changes. If you are new to Litestream, start with the official [Litestream Getting Started guide](https://litestream.io/getting-started/) and [How it works](https://litestream.io/how-it-works/).

## Requirements

- PHP `^8.4`
- Litestream binary support on macOS or Linux
- SQLite should be configured correctly. (See Get Started)

Windows is not supported.

## Installation

Install the package with Composer:

```bash
composer require eznix86/laravel-litestream
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=litestream-config
```

## Get Started

Use this quick sequence to get replication running in a Laravel app.

### 1) Configure SQLite for Litestream safety

In `config/database.php`, make sure the target SQLite connection uses:

- `driver` => `sqlite`
- `busy_timeout` => `5000` (can be more but not less)
- `journal_mode` => `WAL`
- `synchronous` => `NORMAL`
- `foreign_key_constraints` => `true`


### 2) Configure your Litestream replica

Update `config/litestream.php`:

- set one or more `connections`
- point each connection to one or more replica
- configure the replica details under `replicas` (for example `s3`)

If you are using S3-compatible storage, review Litestream's official docs for provider-specific endpoint and credential requirements:

- [Replicating to Amazon S3](https://litestream.io/guides/s3/)
- [Configuration file reference](https://litestream.io/reference/config/)

### IPC Socket

The package can write Litestream's control socket settings into generated YAML:

```php
'socket' => [
    'enabled' => true,
    'path' => env('LITESTREAM_SOCKET_PATH'),
    'permissions' => env('LITESTREAM_SOCKET_PERMISSIONS', '0600'),
],
```

Defaults:

- `socket.enabled`: `true`
- `socket.path`: `<dirname(litestream.binary_path)>/litestream.sock` when `LITESTREAM_SOCKET_PATH` is not set
- `socket.permissions`: `0600`

### 3) Install Litestream binary

```bash
php artisan litestream:install
```

This downloads the latest compatible Litestream binary to `litestream.binary_path` and marks it executable.

If Litestream is already installed on your server, point to it instead of downloading:

```dotenv
LITESTREAM_BINARY_PATH=/usr/local/bin/litestream
```

### 4) Start replication

```bash
php artisan litestream:replicate
```

The command validates configuration, regenerates YAML at `litestream.config_path`, and starts `litestream replicate`.

### 5) Verify status and test restore

```bash
php artisan litestream:status
php artisan litestream:reset
php artisan litestream:restore
php artisan litestream:sync
```

For deeper operational guidance, see:

- [Litestream tips and caveats](https://litestream.io/tips/)
- [Litestream restore command reference](https://litestream.io/reference/restore/)
- [Litestream troubleshooting](https://litestream.io/docs/troubleshooting/)

### Replicas

Each `replicas.<key>` entry is passed through to Litestream YAML.

- Keys are normalized recursively from `snake_case` to `kebab-case` when YAML is generated.
- You can use `['env' => 'VAR_NAME']` for any replica value to emit `${VAR_NAME}` in YAML and inject the real value at runtime via process environment.

For the complete list of replica options per backend, refer to the official Litestream configuration reference:

- [Replica settings reference](https://litestream.io/reference/config/#replica-settings)

Example:

```php
'replicas' => [
    's3' => [
        'type' => 's3',
        'bucket' => env('LITESTREAM_S3_BUCKET'),
        'path' => env('LITESTREAM_S3_PATH'),
        'access_key_id' => env('LITESTREAM_ACCESS_KEY_ID'),
        'secret_access_key' => env('LITESTREAM_SECRET_ACCESS_KEY'),
        'custom_options' => [
            'force_path_style' => true,
        ],
    ],
],
```

Generated YAML keys become:

- `access-key-id`
- `secret-access-key`
- `custom-options.force-path-style`

## Path Modes

`path_mode` controls how the replica `path` value is transformed per connection:

- `append`: `<replica.path>/<effective_connection_name>`
- `replace`: `<effective_connection_name>`
- `preserve`: keep replica `path` unchanged

V1 behavior applies this only to the flat `path` field in each replica definition.

## Runtime Connection Resolver

By default, commands use `config('litestream.connections')`.

If you need resolve the connections at run time, use `resolveConnectionsUsing`:

```php
use Eznix86\Litestream\Facades\Litestream;

Litestream::resolveConnectionsUsing(function (array $connections): array {
    return array_merge($connections, [
        'analytics' => [
            'name' => 'analytics',
            'replicas' => ['s3'],
            'path_mode' => 'append',
        ],
    ]);
});
```

This is useful when you want to:

- add runtime-specific connections on top of static config ex. Multi-tenants
- filter which configured connections run in a given context
- fully replace config connections from another source at runtime.

To return to config-only behavior:

```php
Litestream::forgetConnectionResolver();
```

## Commands

For native command behavior and all flags, refer to Litestream's upstream command docs:

- [Command: replicate](https://litestream.io/reference/replicate/)
- [Command: databases](https://litestream.io/reference/databases/)
- [Command: restore](https://litestream.io/reference/restore/)
- [Command: sync](https://litestream.io/reference/sync/)



## Testing and Quality

Run tests:

```bash
vendor/bin/pest
```

Run static analysis:

```bash
vendor/bin/phpstan analyse --memory-limit=1G
```

Run formatting checks:

```bash
vendor/bin/pint --test
```

Run refactoring checks:

```bash
vendor/bin/rector process --dry-run
```

## Further Reading

For anything not covered by this package README, use the official Litestream documentation:

- [Litestream docs index](https://litestream.io/docs/)
- [Litestream guides](https://litestream.io/guides/)
- [Litestream reference](https://litestream.io/reference/)

## License

This package is open-sourced software licensed under the MIT license.
