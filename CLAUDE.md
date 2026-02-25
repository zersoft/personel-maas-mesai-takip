# CLAUDE.md

## Project

OYS (Ocak Yonetim Sistemi) — PHP quarry management system. See AGENTS.md for full details.

## Security Rules (MANDATORY)

All security rules are defined in `AGENTS.md` under "Security Rules". Follow them strictly. Key points:

1. **CSRF**: Every POST form needs `<?php echo csrfField(); ?>`. Every `*_islem.php` must call `verifyCsrfToken()` for POST requests.
2. **No GET for state changes**: Delete/update/insert must use POST with CSRF tokens.
3. **XSS**: Escape all output with `escape()` or `htmlspecialchars()`. Use `json_encode()` for JS embedding.
4. **Auth**: Every page needs `requireRole('user')` or `requireRole('admin')`. API endpoints need `requireLogin()`.
5. **SQL**: Always use PDO prepared statements. Never concatenate user input into queries.
6. **Sessions**: Directory permissions `0700`, never `0777`. Call `session_regenerate_id(true)` after login.
7. **Errors**: Never expose DB errors to users. Use `error_log()` + generic messages.
8. **No test files**: Never create test.php, debug_*.php, etc.
9. **Money**: Use `parseMoney()` for input, `formatMoney()` for output.
10. **Redirects**: Use `safeRedirect()` function, not raw `header()`.

## Key Files

- `includes/auth.php` — Auth + CSRF functions (generateCsrfToken, verifyCsrfToken, csrfField, requireRole, requireLogin)
- `includes/functions.php` — Helpers (escape, showMessage, parseMoney, formatMoney, safeRedirect, logUserAction)
- `config/db.php` — PDO connection
- `assets/js/main.js` — JS helpers (getCsrfToken, postDeleteForm, postRestoreForm)

## Lint

```bash
find . -name '*.php' -not -path './vendor/*' -exec php -l {} \; 2>&1 | grep -v "No syntax errors"
```
