<?php
// Copy this file to config.php and fill in the database connection.
// config.php is ignored by git. Environment variables (DB_HOST, DB_PORT,
// DB_NAME, DB_USER, DB_PASS) are used when config.php does not exist.
return [
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'mom_writer',
        'user' => 'mom',
        'pass' => 'change-me',
    ],
    // Largest request body accepted (bytes). Signatures are stored as images
    // inside the MOM, so allow a few MB.
    'max_body_bytes' => 5000000,
];
