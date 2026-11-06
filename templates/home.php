<?php
/*
 * Page template. Every dynamic value is escaped with e() (issue #4).
 * Every state-changing form uses POST and includes csrf_field() (issue #5).
 *
 * Variables provided by index.php: $message, $userId, $notes, $editing
 */
if (!function_exists('e')) {
    http_response_code(404);
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<title>Campus Notes</title>
<style>body{font:16px sans-serif;max-width:750px;margin:2rem auto;padding:0 1rem}label{display:block;margin:.5rem 0}article{border:1px solid #aaa;padding:1rem;margin:1rem 0}form.inline{display:inline}</style>
</head>
<body>
<h1>Campus Notes</h1>
<?php if ($message !== ''): ?>
<p role="status"><?= e($message) ?></p>
<?php endif; ?>

<?php if ($userId === null): ?>
<h2>Register or sign in</h2>
<form method="post" action="/">
  <?= csrf_field() ?>
  <label>Email <input name="email" type="email" maxlength="254" autocomplete="username" required></label>
  <label>Password <input name="password" type="password" maxlength="72" autocomplete="current-password" required></label>
  <button name="action" value="register">Register</button>
  <button name="action" value="login">Sign in</button>
</form>
<?php else: ?>
<div>
  Signed in as <?= e($_SESSION['email'] ?? '') ?> | Role: <?= e($_SESSION['role'] ?? '') ?>
  <form class="inline" method="post" action="/">
    <?= csrf_field() ?>
    <button name="action" value="logout">Log out</button>
  </form>
</div>

<?php if ($editing): ?>
<h2>Edit note</h2>
<form method="post" action="/">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= e($editing['id']) ?>">
  <label>Title <input name="title" value="<?= e($editing['title']) ?>" maxlength="120" required></label>
  <label>Body <textarea name="body" maxlength="10000" required><?= e($editing['body']) ?></textarea></label>
  <button name="action" value="update">Update</button>
  <a href="/">Cancel</a>
</form>
<?php endif; ?>

<h2>Create a note</h2>
<form method="post" action="/">
  <?= csrf_field() ?>
  <label>Title <input name="title" maxlength="120" required></label>
  <label>Body <textarea name="body" maxlength="10000" required></textarea></label>
  <button name="action" value="save">Save</button>
</form>

<h2>My notes</h2>
<?php if (!$notes): ?>
<p>No notes yet.</p>
<?php endif; ?>
<?php foreach ($notes as $note): ?>
<article>
  <h3><?= e($note['title']) ?></h3>
  <p><?= nl2br(e($note['body'])) ?></p>
  <a href="/?edit=<?= e($note['id']) ?>">Edit</a>
  <form class="inline" method="post" action="/">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= e($note['id']) ?>">
    <button name="action" value="delete">Delete</button>
  </form>
</article>
<?php endforeach; ?>
<?php endif; ?>
</body>
</html>
