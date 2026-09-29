<?php

/**
 * Configuracion de Phinx (migraciones y seeds).
 *
 * Corre dentro del contenedor php (targets `migrate`/`seed` del Makefile), asi
 * que el host de la base es el servicio `db` de docker-compose. Las
 * credenciales llegan como variables de entorno desde el .env de la raiz.
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Env;

return [
    'paths' => [
        'migrations' => __DIR__ . '/db/migrations',
        'seeds'      => __DIR__ . '/db/seeds',
    ],

    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment'     => 'default',

        'default' => [
            'adapter'   => 'mysql',
            'host'      => Env::get('DB_HOST', 'db'),
            'name'      => Env::get('DB_DATABASE', 'navertia'),
            'user'      => Env::get('DB_USER', 'navertia'),
            'pass'      => Env::get('DB_PASSWORD', 'password'),
            // Fijo: DB_PORT del .env es el puerto PUBLICADO en el host; dentro
            // de la red de Docker MariaDB siempre escucha en el 3306.
            'port'      => 3306,
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ],
    ],

    'version_order' => 'creation',
];
