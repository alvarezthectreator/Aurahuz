<?php
declare(strict_types=1);

return [
    'host' => getenv('AURAHUZ_DB_HOST') ?: '127.0.0.1',
    'port' => getenv('AURAHUZ_DB_PORT') ?: '8889',
    'name' => getenv('AURAHUZ_DB_NAME') ?: 'angel',
    'user' => getenv('AURAHUZ_DB_USER') ?: 'root',
    'password' => getenv('AURAHUZ_DB_PASSWORD') ?: 'root',
    'charset' => 'utf8mb4',
];
