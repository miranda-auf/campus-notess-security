# Campus Notes: security review project (repaired)

This was intentionally vulnerable educational material. This version contains the security repairs for the laboratory assignment; it is still a teaching app. Run it only on an isolated machine you control, with synthetic data and a disposable local database. Never expose it to the internet, deploy it, or connect it to shared or real accounts.

## Requirements

- PHP 8.1 or newer with `pdo_mysql` (check with `php -m`). `mbstring` is used if present.
- MySQL 8 or MariaDB 10.4+ running locally (tested with XAMPP: PHP 8.2.12, MariaDB 10.4.32).
- No Composer packages are used.

## Local setup

1. **Import the schema** as a local MySQL administrator (review it first). It creates `notes_lab`, its tables, and two synthetic accounts whose passwords are now stored with `password_hash()` (bcrypt):

   ```powershell
   Get-Content schema.sql | mysql -u root -p
   ```

   If you already imported the *original* MD5 schema, keep that database and run step 4's command `php tools/reset_passwords.php` once instead (after setting the variables in step 3).

2. **Create a least-privilege account** (the app refuses to connect as `root`):

   ```sql
   CREATE USER 'notes_app'@'localhost' IDENTIFIED BY 'choose-a-local-password';
   GRANT SELECT, INSERT, UPDATE, DELETE ON notes_lab.* TO 'notes_app'@'localhost';
   ```

3. **Set the credentials in the shell** you will start the server from. Do not commit them.

   ```powershell
   $env:LAB_DB_USER = 'notes_app'
   $env:LAB_DB_PASSWORD = 'choose-a-local-password'
   ```

   Optional: `LAB_DB_HOST` (default `127.0.0.1`), `LAB_DB_PORT` (`3306`), `LAB_DB_NAME` (`notes_lab`).

4. **Start the development server with the router**, which serves only `index.php` and `api.php` and returns 404 for `src/`, `templates/`, `tools/`, `tests/` and `schema.sql`:

   ```powershell
   php -S 127.0.0.1:8000 router.php
   ```

   Visit `http://127.0.0.1:8000`. Stop it with Ctrl+C. The built-in server is for local development only.

5. **Synthetic accounts** (public, throwaway, local lab only):
   - `alex@example.test` / `LabOnly-Alex-123`
   - `sam@example.test` / `LabOnly-Sam-456`

   Use separate browser profiles (or a normal and a private window) for the two sessions.

## Automated security checks

With the server running, in a second terminal in this folder:

```powershell
php tests/security_check.php http://127.0.0.1:8000
```

It signs in as both synthetic users and runs 35 checks (login, ownership, XSS, CSRF, sessions, API authentication, validation and origin checks). It never prints passwords, session IDs or tokens. It deletes the notes it creates; each run leaves one throwaway `check-…@example.test` account.

## API

All endpoints require a signed-in session cookie and refuse other origins. No CORS headers are sent.

- `GET /api.php` – the signed-in user's notes. Any `user_id` parameter is ignored.
- `POST /api.php` – update one of the signed-in user's notes.
  - Headers: `Content-Type: application/json`, `X-CSRF-Token: <value of the page's <meta name="csrf-token">>`
  - Body: `{"id": 1, "title": "1–120 chars", "body": "1–10000 chars"}` (no other fields)
  - Responses: 200, 400 malformed JSON, 401, 403 (origin/CSRF), 404 (missing or not yours), 415, 422 validation.

## Project layout

```
index.php               web controller (account + note CRUD)
api.php                 JSON API
router.php              built-in-server router (blocks internal files)
schema.sql              database + synthetic data (bcrypt hashes)
src/bootstrap.php       error handling, loads the modules below
src/db.php              PDO connection from environment variables
src/auth.php            sessions, login, logout, registration
src/csrf.php            CSRF tokens
src/notes.php           owner-scoped note queries and validation
src/helpers.php         escaping, redirects, flash messages, headers, origin check
templates/home.php      HTML template (all output escaped)
tools/reset_passwords.php  CLI: re-hash synthetic accounts in an old MD5 database
tests/security_check.php   CLI: automated security checks
```

## Production notes

This local HTTP setup is for the exercise only. A real deployment would also need HTTPS (the session cookie becomes `Secure` automatically), a real web server instead of `php -S`, secrets from a secret store, login rate limiting, and centralised logging.
