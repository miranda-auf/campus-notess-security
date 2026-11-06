<?php
declare(strict_types=1);

/*
 * CSRF tokens (issues #5 and #11). One random token per session,
 * required on every state-changing request (form field or X-CSRF-Token header).
 */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_verify(mixed $submitted): bool
{
    $expected = $_SESSION['csrf_token'] ?? null;
    return is_string($expected) && $expected !== ''
        && is_string($submitted) && hash_equals($expected, $submitted);
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}
