<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Enable Litestream Workflows
    |--------------------------------------------------------------------------
    |
    | When false, install/replicate/status/restore commands exit early.
    |
    */
    'enabled' => env('LITESTREAM_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Litestream Binary Path
    |--------------------------------------------------------------------------
    |
    | Absolute path to the Litestream binary that commands execute.
    | `litestream:install` downloads to this path when missing.
    |
    */
    'binary_path' => env('LITESTREAM_BINARY_PATH', storage_path('litestream/litestream')),

    /*
    |--------------------------------------------------------------------------
    | Generated Litestream YAML Path
    |--------------------------------------------------------------------------
    |
    | Destination path for generated Litestream YAML.
    | Regenerated before `status`, `replicate`, `reset`, and `restore`.
    |
    */
    'config_path' => env('LITESTREAM_CONFIG_PATH', storage_path('litestream/litestream.yml')),

    /*
    |--------------------------------------------------------------------------
    | Log Level
    |--------------------------------------------------------------------------
    |
    | Default Litestream logging level written to YAML.
    |
    */
    'log_level' => env('LITESTREAM_LOG_LEVEL', 'info'),

    /*
    |--------------------------------------------------------------------------
    | Metrics Server
    |--------------------------------------------------------------------------
    |
    | Optional Prometheus metrics endpoint.
    | `enabled=true` adds `addr` to generated YAML and validates availability.
    |
    */
    'metrics' => [
        'enabled' => (bool) env('LITESTREAM_METRICS_ENABLED', false),
        'address' => env('LITESTREAM_METRICS_ADDRESS', '127.0.0.1:9090'),
    ],

    /*
    |--------------------------------------------------------------------------
    | MCP Server
    |--------------------------------------------------------------------------
    |
    | Optional MCP endpoint.
    | `enabled=true` adds `mcp-addr` to generated YAML and validates availability.
    |
    */
    'mcp' => [
        'enabled' => (bool) env('LITESTREAM_MCP_ENABLED', false),
        'address' => env('LITESTREAM_MCP_ADDRESS', '127.0.0.1:3001'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Connection Mapping
    |--------------------------------------------------------------------------
    |
    | Defines which Laravel SQLite connections are managed.
    | Key is the Litestream connection key used by command filtering.
    |
    | - name: Optional effective name for path suffix/replacement behavior.
    | - replicas: Replica keys from the `replicas` section to attach.
    | - path_mode: How replica `path` is transformed:
    |   - append: `<path>/<name>`
    |   - replace: `<name>`
    |   - preserve: original `path`
    |
    |
    */
    'connections' => [
        'default' => [
            'name' => 'default',
            'replicas' => ['s3'],
            'path_mode' => 'append',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Replica Definitions
    |--------------------------------------------------------------------------
    |
    | Keyed by replica key referenced from `connections.*.replicas`.
    | Values are passed through to Litestream YAML (snake_case -> kebab-case).
    | Use ['env' => 'VAR_NAME'] to emit ${VAR_NAME} placeholders in YAML
    | and inject values at runtime via Process::env(...).
    | Refer to https://litestream.io/reference/config/#replica-settings
    |
    */
    'replicas' => [
        's3' => [
            'type' => 's3',
            'bucket' => env('LITESTREAM_S3_BUCKET', ''),
            'path' => env('LITESTREAM_S3_PATH', ''),
            'region' => env('LITESTREAM_S3_REGION', ''),
            'endpoint' => env('LITESTREAM_S3_ENDPOINT', ''),
            'access_key_id' => env('LITESTREAM_ACCESS_KEY_ID', ''),
            'secret_access_key' => env('LITESTREAM_SECRET_ACCESS_KEY', ''),
        ],

        // Local directory replica.
        // 'file' => [
        //     'type' => 'file',
        //     'path' => env('LITESTREAM_FILE_PATH', ''),
        // ],

        // Google Cloud Storage replica.
        // 'gs' => [
        //     'type' => 'gs',
        //     'bucket' => env('LITESTREAM_GS_BUCKET', ''),
        //     'path' => env('LITESTREAM_GS_PATH', ''),
        //     'service_account_key' => env('LITESTREAM_GS_SERVICE_ACCOUNT_KEY', ''),
        // ],

        // Azure Blob Storage replica.
        // 'abs' => [
        //     'type' => 'abs',
        //     'account_name' => env('LITESTREAM_ABS_ACCOUNT_NAME', ''),
        //     'account_key' => env('LITESTREAM_ABS_ACCOUNT_KEY', ''),
        //     'bucket' => env('LITESTREAM_ABS_BUCKET', ''),
        //     'path' => env('LITESTREAM_ABS_PATH', ''),
        // ],

        // SFTP server replica.
        // 'sftp' => [
        //     'type' => 'sftp',
        //     'host' => env('LITESTREAM_SFTP_HOST', ''),
        //     'user' => env('LITESTREAM_SFTP_USER', ''),
        //     'password' => env('LITESTREAM_SFTP_PASSWORD', ''),
        //     'path' => env('LITESTREAM_SFTP_PATH', ''),
        // ],

        // NATS JetStream Object Store replica.
        // 'nats' => [
        //     'type' => 'nats',
        //     'url' => env('LITESTREAM_NATS_URL', ''),
        //     'bucket' => env('LITESTREAM_NATS_BUCKET', ''),
        //     'username' => env('LITESTREAM_NATS_USERNAME', ''),
        //     'password' => env('LITESTREAM_NATS_PASSWORD', ''),
        // ],

        // Alibaba Cloud OSS replica.
        // 'oss' => [
        //     'type' => 'oss',
        //     'bucket' => env('LITESTREAM_OSS_BUCKET', ''),
        //     'path' => env('LITESTREAM_OSS_PATH', ''),
        //     'region' => env('LITESTREAM_OSS_REGION', ''),
        //     'access_key_id' => env('LITESTREAM_OSS_ACCESS_KEY_ID', ''),
        //     'secret_access_key' => env('LITESTREAM_OSS_SECRET_ACCESS_KEY', ''),
        // ],

        // WebDAV server replica.
        // 'webdav' => [
        //     'type' => 'webdav',
        //     'webdav_url' => env('LITESTREAM_WEBDAV_URL', ''),
        //     'webdav_username' => env('LITESTREAM_WEBDAV_USERNAME', ''),
        //     'webdav_password' => env('LITESTREAM_WEBDAV_PASSWORD', ''),
        //     'path' => env('LITESTREAM_WEBDAV_PATH', ''),
        // ],
    ],
];
