<?php
declare(strict_types=1);

/*
 * JSON API (issues #9, #10, #11).
 *   GET  /api.php        -> the signed-in user's notes (any ?user_id is ignored)
 *   POST /api.php        -> update one of the signed-in user's notes
 *        headers: Content-Type: application/json, X-CSRF-Token: <token from the page>
 *        body:    {"id": 1, "title": "...", "body": "..."}
 */

require __DIR__ . '/src/bootstrap.php';

// JSON errors only: never a stack trace or database message.
set_exception_handler(static function (Throwable $e): void {
    error_log(sprintf('[campus-notes api] %s in %s:%d', get_class($e), $e->getFile(), $e->getLine()));
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['error' => 'Internal server error']);
});

header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
// Issue #11: no Access-Control-Allow-* headers at all, so browsers block cross-origin
// pages from reading responses. Requests from another origin are also refused outright.
if (!request_origin_allowed()) {
    json_response(['error' => 'Cross-origin request rejected'], 403);
}

start_secure_session();

// Issue #9: authentication required for every method.
$userId = current_user_id();
if ($userId === null) {
    json_response(['error' => 'Authentication required'], 401);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    // Owner comes from the session, never from ?user_id=.
    json_response(notes_for_user($userId));
}

if ($method === 'POST') {
    // Issue #11: cookie-authenticated writes need the session's CSRF token in a custom header.
    if (!csrf_verify($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
        json_response(['error' => 'Missing or invalid CSRF token'], 403);
    }

    // Issue #10: strict request validation.
    $contentType = strtolower($_SERVER['CONTENT_TYPE'] ?? '');
    if (!str_starts_with($contentType, 'application/json')) {
        json_response(['error' => 'Content-Type must be application/json'], 415);
    }
    $raw = file_get_contents('php://input', false, null, 0, 65537);
    if ($raw === false || strlen($raw) > 65536) {
        json_response(['error' => 'Request body too large'], 413);
    }
    try {
        $data = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        json_response(['error' => 'Malformed JSON'], 400);
    }
    if (!is_array($data) || ($data !== [] && array_is_list($data))) {
        json_response(['error' => 'Expected a JSON object'], 400);
    }
    $unexpected = array_diff(array_keys($data), ['id', 'title', 'body']);
    if ($unexpected) {
        json_response(['error' => 'Unexpected fields', 'fields' => array_values($unexpected)], 422);
    }

    $noteId = positive_int($data['id'] ?? null);
    $input = validate_note_input($data['title'] ?? null, $data['body'] ?? null);
    $errors = $input['errors'];
    if ($noteId === null) {
        $errors['id'] = 'id must be a positive integer.';
    }
    if ($errors) {
        json_response(['error' => 'Validation failed', 'fields' => $errors], 422);
    }

    // Issue #9: per-record authorization. Someone else's note looks exactly like a missing one.
    if (!update_note($noteId, $userId, $input['title'], $input['body'])) {
        json_response(['error' => 'Note not found'], 404);
    }
    json_response(['updated' => true]);
}

header('Allow: GET, POST');
json_response(['error' => 'Method not supported'], 405);
