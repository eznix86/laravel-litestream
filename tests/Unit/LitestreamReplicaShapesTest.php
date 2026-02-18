<?php

declare(strict_types=1);

use Eznix86\Litestream\Concerns\GeneratesLitestreamConfig;
use Symfony\Component\Yaml\Yaml;

it('builds yaml payloads for documented replica shapes with normalized keys', function (): void {
    $generator = new class
    {
        use GeneratesLitestreamConfig;
    };

    $yamlPath = sys_get_temp_dir().'/litestream-tests/'.uniqid('yaml-replica-shapes-', true).'.yml';

    config()->set('litestream.config_path', $yamlPath);
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite.database', '/tmp/default.sqlite');
    config()->set('litestream.replicas', [
        's3' => [
            'type' => 's3',
            'path' => 'backups/app',
            'access_key_id' => 'key',
            'secret_access_key' => 'secret',
        ],
        'sftp' => [
            'type' => 'sftp',
            'host' => 'example.com:22',
            'key_path' => '/keys/id_rsa',
            'path' => '/remote/path',
        ],
        'webdav' => [
            'type' => 'webdav',
            'webdav_url' => 'https://webdav.example.com',
            'webdav_username' => 'user',
            'webdav_password' => 'password',
            'path' => '/webdav/path',
        ],
    ]);

    $generator->generateConfig([
        'default' => [
            'name' => 'default',
            'replicas' => ['s3', 'sftp', 'webdav'],
            'path_mode' => 'preserve',
        ],
    ]);

    $replicas = data_get(Yaml::parseFile($yamlPath), 'dbs.0.replicas', []);

    expect($replicas)->toBe([
        [
            'type' => 's3',
            'path' => 'backups/app',
            'access-key-id' => 'key',
            'secret-access-key' => 'secret',
        ],
        [
            'type' => 'sftp',
            'host' => 'example.com:22',
            'key-path' => '/keys/id_rsa',
            'path' => '/remote/path',
        ],
        [
            'type' => 'webdav',
            'webdav-url' => 'https://webdav.example.com',
            'webdav-username' => 'user',
            'webdav-password' => 'password',
            'path' => '/webdav/path',
        ],
    ]);
});
