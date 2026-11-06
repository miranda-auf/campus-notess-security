<?php
declare(strict_types=1);

/*
 * Web app controller. Handles requests; HTML lives in templates/home.php.
 */

require __DIR__ . '/src/bootstrap.php';

start_secure_session();
send_security_headers();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$userId = current_user_id();

// Issue #5: state changes are POST-only. The old GET links (?delete=, ?logout=) no longer do anything.
if ($method === 'GET' && (isset($_GET['delete']) || isset($_GET['logout']))) {
    http_response_code(405);
    header('Allow: POST');
    exit('This action must be submitted with the form button.');
}

if ($method === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        exit('Invalid or expired form. Go back, reload the page and try again.');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'register' && $userId === null) {
        $error = register_user((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''));
        flash($error ?? 'Account created. You can sign in now.');
        redirect('/');
    }

    if ($action === 'login' && $userId === null) {
        $user = authenticate(trim((string) ($_POST['email'] ?? '')), (string) ($_POST['password'] ?? ''));
        if ($user === null) {
            flash('Invalid email or password.');
            redirect('/');
        }
        login_user($user);
        redirect('/');
    }

    if ($userId === null) {
        flash('Please sign in first.');
        redirect('/');
    }

    if ($action === 'logout') {
        logout_user();
        session_id(session_create_id());  // brand-new, empty session just to carry the message
        session_start();
        flash('You have been signed out.');
        redirect('/');
    }

    if ($action === 'save') {
        // Issue #7: the owner is always the signed-in user; any user_id field is ignored.
        $input = validate_note_input($_POST['title'] ?? null, $_POST['body'] ?? null);
        if ($input['errors']) {
            flash(implode(' ', $input['errors']));
            redirect('/');
        }
        create_note($userId, $input['title'], $input['body']);
        flash('Note saved.');
        redirect('/');
    }

    if ($action === 'update') {
        $noteId = positive_int($_POST['id'] ?? null);
        $input = validate_note_input($_POST['title'] ?? null, $_POST['body'] ?? null);
        if ($noteId === null || $input['errors']) {
            http_response_code(422);
            exit('Invalid note data.');
        }
        if (!update_note($noteId, $userId, $input['title'], $input['body'])) {
            http_response_code(404);
            exit('Note not found.');
        }
        flash('Note updated.');
        redirect('/');
    }

    if ($action === 'delete') {
        $noteId = positive_int($_POST['id'] ?? null);
        if ($noteId === null || !delete_note($noteId, $userId)) {
            http_response_code(404);
            exit('Note not found.');
        }
        flash('Note deleted.');
        redirect('/');
    }

    http_response_code(400);
    exit('Unknown action.');
}

if ($method !== 'GET' && $method !== 'HEAD') {
    http_response_code(405);
    header('Allow: GET, POST');
    exit('Method not allowed.');
}

// Build the page.
$message = take_flash();
$notes = [];
$editing = null;

if ($userId !== null) {
    $notes = notes_for_user($userId);              // issue #3: only the signed-in user's notes
    if (isset($_GET['edit'])) {
        $editId = positive_int($_GET['edit']);
        $editing = $editId === null ? null : find_note($editId, $userId);
        if ($editing === null) {
            http_response_code(404);
            $message = 'Note not found.';
        }
    }
}

require __DIR__ . '/templates/home.php';
