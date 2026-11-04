<?php
// Loaded at the top of every page: database, helpers, CSRF, login session.

// Any unexpected error (e.g. a failed database query) is written to the PHP
// error log and the user sees a plain message, never technical details
// such as SQL or file paths (PRD 12.1, 14).
set_exception_handler(function (Throwable $e): void {
    error_log('Guest Register: ' . $e);

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
    }

    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Something went wrong</title></head>'
        . '<body style="font-family: Segoe UI, Arial, sans-serif; background: #08090a; color: #f7f8f8; padding: 3rem;">'
        . '<h1 style="font-size: 1.4rem;">Something went wrong</h1>'
        . '<p>Your last action was <strong>not saved</strong>. Please go back and try again.</p>'
        . '<p style="color: #8a8f98;">If this keeps happening, tell your administrator.</p>'
        . '</body></html>';
});

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth.php';

// Basic browser security headers
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

start_session();
