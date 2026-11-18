<?php

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/panel.php';
require_once __DIR__ . '/reservations.php';
require_once __DIR__ . '/availability.php';

date_default_timezone_set('Europe/Warsaw');

ini_set('default_charset', 'UTF-8');
header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');

error_reporting(E_ALL);
ini_set('display_errors', config('debug') ? '1' : '0');

set_error_handler(function (int $level, string $message, string $file, int $line): bool {
    throw new ErrorException($message, 0, $level, $file, $line);
});

set_exception_handler(function (Throwable $e): void {
    error_log((string) $e);
    http_response_code(500);

    if (config('debug')) {
        echo '<pre>' . e((string) $e) . '</pre>';
        return;
    }

    require __DIR__ . '/layout/blad.php';
});

start_session();
