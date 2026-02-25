# AGENTS.md

## Project Overview

OYS (Ocak Yönetim Sistemi) — a PHP-based quarry management system for personnel, payroll, attendance, overtime, advances, severance, and weighbridge reports. See `README.md` for full feature list.

---

## Security Rules (MANDATORY for all agents)

All agents (Claude Code, Cursor, Copilot, etc.) MUST follow these rules when writing or modifying code in this project.

### 1. CSRF Protection

- Every HTML `<form>` with `method="POST"` MUST include `<?php echo csrfField(); ?>` as the first hidden field inside the form.
- Every `*_islem.php` (processing) file MUST verify CSRF tokens at the top, before any action:
  ```php
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      verifyCsrfToken();
  }
  ```
- JavaScript-generated POST forms (e.g. delete confirmations) MUST include the CSRF token from `<meta name="csrf-token">`:
  ```javascript
  var token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
  ```
- CSRF functions are defined in `includes/auth.php`: `generateCsrfToken()`, `verifyCsrfToken()`, `csrfField()`.

### 2. State-Changing Operations MUST Use POST

- **NEVER** use GET requests for delete, update, or insert operations.
- All delete operations must use POST forms with CSRF tokens.
- Use the `postDeleteForm(action, id)` and `postRestoreForm(action, id)` helpers from `assets/js/main.js` for JavaScript-triggered deletions.

### 3. XSS Prevention

- Always escape user output with `escape()` (project helper) or `htmlspecialchars($val, ENT_QUOTES, 'UTF-8')`.
- When embedding PHP values into JavaScript, use `json_encode()`:
  ```php
  var data = <?php echo json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP); ?>;
  ```
- Never use `urldecode()` on display output — it can bypass escaping.
- The `showMessage()` function already escapes internally; do not double-escape.

### 4. Authentication & Authorization

- Every page MUST include auth check at the top:
  ```php
  require_once '../includes/auth.php';
  requireRole('user');  // or 'admin' for admin-only pages
  ```
- API endpoints (e.g. `*_api.php`) MUST also include session init + `requireLogin()`.
- Three roles exist: `admin`, `user`, `viewer`. Use `requireRole()` appropriately.
- Viewer role should NOT have access to insert/update/delete actions.

### 5. SQL Injection Prevention

- **ALWAYS** use PDO prepared statements with `?` placeholders. Never concatenate user input into SQL.
  ```php
  $stmt = $pdo->prepare("SELECT * FROM table WHERE id = ?");
  $stmt->execute([$id]);
  ```
- Cast IDs to integer: `$id = (int)$_POST['id'];`

### 6. Session Security

- Session initialization block (copy from existing files, e.g. `login.php`):
  - Session save path: `storage/sessions/`
  - Directory permissions: `0700` (NEVER `0777`)
  - `cookie_httponly = 1`, `use_only_cookies = 1`
- After successful login, MUST call `session_regenerate_id(true)` to prevent session fixation.

### 7. Error Handling

- **NEVER** expose database error details to users. Log with `error_log()`, show generic message:
  ```php
  error_log("Hata: " . $e->getMessage());
  safeRedirect('page.php?error=' . urlencode('Bir hata olustu.'));
  ```
- Production settings: `error_reporting(0)`, `display_errors = 0`.

### 8. File & Directory Protection

- `.htaccess` files protect `config/`, `storage/`, `.env`, `*.sql` files from direct web access. Do NOT remove them.
- Never create test/debug files (test.php, debug_*.php, etc.) in the project.
- Never commit `.env` files to git.

### 9. Money/Currency Handling

- Use `parseMoney()` from `includes/functions.php` to parse Turkish-formatted currency input (e.g. "57.500,00").
- Use `formatMoney()` for display output.
- For form submissions with money fields, use hidden `*_raw` fields populated by JavaScript before submit.

### 10. Redirect Safety

- Always use `safeRedirect()` instead of raw `header('Location: ...')` for redirects.
- Always call `exit` or `die` after redirects if not using `safeRedirect()`.

### 11. HTTP Security Headers

- `header.php` and `login.php` set security headers automatically. If creating a standalone page (without header.php), add these headers:

  ```php
  header('X-Content-Type-Options: nosniff');
  header('X-Frame-Options: SAMEORIGIN');
  header('X-XSS-Protection: 1; mode=block');
  header('Referrer-Policy: strict-origin-when-cross-origin');
  ```

### 12. Brute Force Protection

- Login page has rate limiting: 5 failed attempts = 15 min lockout.
- Never remove or weaken this protection.

---

## Cursor Cloud specific instructions

### Services

| Service | How to start | Port |
|---------|-------------|------|
| MariaDB (MySQL-compatible) | `sudo mysqld_safe &` | 3306 (socket: `/var/run/mysqld/mysqld.sock`) |
| PHP dev server | `php -S 0.0.0.0:8000` (from repo root) | 8000 |

### Database setup (first time only)

MariaDB must be running before importing. After installing MariaDB:

```bash
sudo chmod 755 /var/run/mysqld/
sudo mysql -u root -e "CREATE DATABASE IF NOT EXISTS zersoftn_personel_takip CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -u root zersoftn_personel_takip < database.sql
# Then run migrations in the order listed in README.md § Kurulum
# "Duplicate column" errors from migrations are expected — the base schema already includes those columns.
```

### Non-obvious gotchas

- **MySQL socket permissions**: After starting MariaDB, you may need `sudo chmod 755 /var/run/mysqld/` so PHP can connect via the Unix socket.
- **Admin credentials**: username `admin`, password `admin123`. The password hash was fixed in commit `0873434` — if running on an older branch, you may need to regenerate it with `php -r "echo password_hash('admin123', PASSWORD_BCRYPT);"` and update the `users` table.
- **`.env` file**: Copy `.env.example` to `.env` and set `DB_HOST=localhost`, `DB_USER=root`, `DB_PASS=` (empty) for local dev. The `.env` file is gitignored.
- **Session directory**: `storage/sessions/` must exist and be writable. Create it with `mkdir -p storage/sessions && chmod 700 storage/sessions`.
- **No linter/test framework**: This is a plain PHP project with no automated test suite or linter configured. Validation is done via `php -l` (syntax check) and manual browser testing.
- **Vendor committed**: The `vendor/` directory is committed; `composer install` is a no-op unless `composer.lock` changes.
- **Report DB is optional**: The `DB_REPORT_*` env vars for Kantar reports can be left empty — the app handles missing report DB gracefully.

### Running the application

1. Start MariaDB: `sudo mysqld_safe &` then `sudo chmod 755 /var/run/mysqld/`
2. Start PHP dev server: `php -S 0.0.0.0:8000` from the repo root
3. Open `http://localhost:8000/login.php` — login with `admin` / `admin123`

### Lint check (syntax only)

```bash
find . -name '*.php' -not -path './vendor/*' -exec php -l {} \; 2>&1 | grep -v "No syntax errors"
```
