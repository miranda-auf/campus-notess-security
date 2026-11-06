<?php
declare(strict_types=1);

/*
 * All note queries (issues #1, #3, #7, #9).
 * Every query is parameterised and scoped to the owner taken from the session.
 */

const NOTE_TITLE_MAX = 120;
const NOTE_BODY_MAX = 10000;

function notes_for_user(int $userId): array
{
    $stmt = db()->prepare('SELECT id, title, body FROM notes WHERE user_id = ? ORDER BY id DESC');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function find_note(int $noteId, int $userId): ?array
{
    $stmt = db()->prepare('SELECT id, title, body FROM notes WHERE id = ? AND user_id = ?');
    $stmt->execute([$noteId, $userId]);
    $note = $stmt->fetch();
    return $note ?: null;
}

function create_note(int $userId, string $title, string $body): void
{
    $stmt = db()->prepare('INSERT INTO notes (user_id, title, body) VALUES (?, ?, ?)');
    $stmt->execute([$userId, $title, $body]);
}

/** Returns false when the note does not exist or belongs to someone else. */
function update_note(int $noteId, int $userId, string $title, string $body): bool
{
    if (find_note($noteId, $userId) === null) {
        return false;
    }
    $stmt = db()->prepare('UPDATE notes SET title = ?, body = ? WHERE id = ? AND user_id = ?');
    $stmt->execute([$title, $body, $noteId, $userId]);
    return true;
}

function delete_note(int $noteId, int $userId): bool
{
    $stmt = db()->prepare('DELETE FROM notes WHERE id = ? AND user_id = ?');
    $stmt->execute([$noteId, $userId]);
    return $stmt->rowCount() > 0;
}

/**
 * Validates note fields from a form or JSON.
 * Returns ['title' => ..., 'body' => ..., 'errors' => [...]].
 */
function validate_note_input(mixed $title, mixed $body): array
{
    $errors = [];
    if (!is_string($title) || !preg_match('//u', $title)) {
        $errors['title'] = 'Title must be text.';
        $title = '';
    } else {
        $title = trim($title);
        if ($title === '' || text_length($title) > NOTE_TITLE_MAX) {
            $errors['title'] = 'Title must be 1 to ' . NOTE_TITLE_MAX . ' characters.';
        }
    }
    if (!is_string($body) || !preg_match('//u', $body)) {
        $errors['body'] = 'Body must be text.';
        $body = '';
    } else {
        if (trim($body) === '' || text_length($body) > NOTE_BODY_MAX) {
            $errors['body'] = 'Body must be 1 to ' . NOTE_BODY_MAX . ' characters.';
        }
    }
    return ['title' => $title, 'body' => $body, 'errors' => $errors];
}
