<?php
// Vercel runs this file as /api/index.php, which makes Laravel treat "/api" as a
// folder prefix and cut it from every URL (/api/v1/login became /v1/login).
// Pretend the front controller is /index.php so the full URL reaches Laravel.
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/../public/index.php';

require __DIR__ . '/../public/index.php';