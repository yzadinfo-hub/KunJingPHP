<?php
/**
 * 组梦ERP（小模块迁移样例）— 配置文件
 * 数据源：本地 MySQL 库 kunjing_erp
 */

$basePath = __DIR__;

return [
    'app' => [
        'name'  => '组梦ERP·堃景框架迁移样例',
        'debug' => true,
    ],

    'db' => [
        'default' => 'mysql',
        'connections' => [
            'mysql' => [
                'driver'   => 'mysql',
                'host'     => '127.0.0.1',
                'port'     => 3306,
                'database' => 'kunjing_erp',
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
        'path'    => $basePath . '/storage/logs/erp.log',
        'channel' => 'daily',
        'level'   => 'debug',
    ],

    'session' => [
        'driver'   => 'file',
        'files'    => $basePath . '/storage/session',
        'name'     => 'ERPSID',
        'lifetime' => 7200,
    ],

    'view' => [
        'view_path'  => $basePath . '/views',
        'cache_path' => $basePath . '/storage/views',
    ],
];
