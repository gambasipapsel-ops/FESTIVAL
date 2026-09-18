<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !verify_csrf()) {
    redirect(url('/admin/'));
}
admin_logout();
redirect(url('/admin/login.php'));
