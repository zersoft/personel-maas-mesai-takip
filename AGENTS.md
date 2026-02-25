# AGENTS.md

## Project Overview

OYS (Ocak Yönetim Sistemi) — a PHP-based quarry management system for personnel, payroll, attendance, overtime, advances, severance, and weighbridge reports. See `README.md` for full feature list.

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
- **Session directory**: `storage/sessions/` must exist and be writable. Create it with `mkdir -p storage/sessions`.
- **No linter/test framework**: This is a plain PHP project with no automated test suite or linter configured. Validation is done via `php -l` (syntax check) and manual browser testing.
- **Vendor committed**: The `vendor/` directory is committed; `composer install` is a no-op unless `composer.lock` changes.
- **Report DB is optional**: The `DB_REPORT_*` env vars for Kantar reports can be left empty — the app handles missing report DB gracefully.

### Running the application

1. Start MariaDB: `sudo mysqld_safe &` then `sudo chmod 755 /var/run/mysqld/`
2. Start PHP dev server: `php -S 0.0.0.0:8000` from the repo root
3. Open `http://localhost:8000/login.php` — login with `admin` / `admin123`
4. Test page at `http://localhost:8000/test.php` verifies DB connectivity and table status

### Lint check (syntax only)

```bash
find . -name '*.php' -not -path './vendor/*' -exec php -l {} \; 2>&1 | grep -v "No syntax errors"
```
