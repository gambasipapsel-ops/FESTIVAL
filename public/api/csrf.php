<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';

require_method('GET');
json_ok('OK', ['csrf_token' => csrf_token()]);
