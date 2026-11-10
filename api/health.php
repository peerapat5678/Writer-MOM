<?php
require __DIR__ . '/_bootstrap.php';

db()->query('SELECT 1');
respond(['ok' => true]);
