<?php
declare(strict_types=1);

/*
 * Shared setup for every entry point (index.php, api.php).
 * Fix for issue #8: errors are logged, never displayed to the browser.
 */

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// Turn notices/warnings into exceptions so unexpected input fails closed
// instead of continuing with half-valid data.
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// Default handler for HTML pages: generic message for the user, details only in the server log.
set_exception_handler(static function (Throwable $e): void {
    error_log(sprintf('[campus-notes] %s in %s:%d', get_class($e), $e->getFile(), $e->getLine()));
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<!doctype html><meta charset="utf-8"><title>Error</title><p>Something went wrong. Please try again later.</p>';
});

require __DIR__ . '/db.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/csrf.php';
require __DIR__ . '/notes.php';
