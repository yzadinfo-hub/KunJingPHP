<?php
/**
 * 永州堃景科技 · 企业官网 — 配置文件
 * 数据源：本地 MySQL 库 kunjing_site（联系表单留言可存此处，缺失时自动回退文件存储）
 */

$basePath = __DIR__;

return [
    'app' => [
        'name'  => '永州堃景科技有限公司',
        'debug' => true,
    ],

    'db' => [
        'default' => 'mysql',
        'connections' => [
            'mysql' => [
                'driver'   => 'mysql',
                'host'     => '127.0.0.1',
                'port'     => 3306,
                'database' => 'kunjing_site',
                'username' => 'root',
                'password' => '',
                'charset'  => 'utf8mb4',
                'timeout'  => 5,
            ],
        ],
    ],

    'cache' => [
        'default' => 'file',
        'stores'  => [
            'array' => ['driver' => 'array'],
            'file'  => ['driver' => 'file', 'path' => $basePath . '/storage/cache'],
            'redis' => ['driver' => 'redis', 'host' => '127.0.0.1', 'port' => 6379],
        ],
    ],

    'log' => [
        'path'    => $basePath . '/storage/logs/site.log',
        'channel' => 'daily',
        'level'   => 'debug',
    ],

    'session' => [
        'driver'   => 'file',
        'files'    => $basePath . '/storage/session',
        'name'     => 'KJSID',
        'lifetime' => 7200,
    ],

    'view' => [
        'view_path'  => $basePath . '/views',
        'cache_path' => $basePath . '/storage/views',
    ],
];
