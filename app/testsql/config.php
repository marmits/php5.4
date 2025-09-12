<?php
return array(
    'db' => array(
        // Astuce : si tu préfères piloter ça via des variables d'env (Compose),
//        'host'    => getenv('DB_HOST') ?: 'host.docker.internal',
        'host'    => getenv('DB_HOST'),
        'port'    => getenv('DB_PORT'),
        'name'    => getenv('DB_NAME'),
        'user'    => getenv('DB_USER'),
        'pass'    => getenv('DB_PASS'),
        'charset' => 'utf8mb4',
    ),
    // Active des messages détaillés en cas de souci (désactive en prod)
    'debug' => false,
);