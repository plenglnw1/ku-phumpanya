# Runbook: Google login fails on KU

The callback (`GET /auth/google/callback`) no longer returns a bare 500. On failure it redirects to
`FRONTEND_URL/?login_error=<reason>` and logs the cause.

| `login_error` | Log key | Meaning |
|---|---|---|
| `session` | `google.callback.invalid_state` | State/session cookie lost between the redirect and the callback (session domain, SameSite, cookie blocked) |
| `google` | `google.callback.provider` | Google rejected the code or the token exchange failed |
| `account` | `google.callback.user_upsert` or `google.callback.google_id_conflict` | Database error while saving the user, or the email already belongs to another Google account |

`user_upsert` logs contain the exception, and a `QueryException` message includes the SQL bindings, i.e. the user's email.

## Commands on the KU server

App dir is `~/html/ku-phumpanya-app` (symlinked as `~/ku-phumpanya`).

```
cd ~/ku-phumpanya
grep -nE "google\.callback|SQLSTATE|InvalidState|production\.ERROR" storage/logs/laravel.log | tail -20
php artisan migrate:status | tail -15
php artisan app:check-schema
mysql -u "$DB_USER" -p "$DB_NAME" -e "SELECT VERSION(); DESCRIBE users; SELECT id,email,google_id,role,profile_completed_at FROM users WHERE email LIKE 'chissanupun%';"
grep -E '^(APP_URL|FRONTEND_URL|SESSION_DRIVER|SESSION_DOMAIN|SESSION_SECURE_COOKIE|SESSION_SAME_SITE|SANCTUM_STATEFUL_DOMAINS|GOOGLE_REDIRECT_URI)=' .env
```

`app:check-schema` is read-only and CLI-only. It lists pending migrations, checks the `users` columns the
callback writes and that `users.password` is nullable, checks the `sessions` table when
`SESSION_DRIVER=database`, and prints the session/URL config. It reports whether `GOOGLE_CLIENT_ID` is
set, never its value. Exit code 1 means a problem was found.

## Reading the result

- Pending migration or missing `users` column: the `2026_07_02` migration did not finish. MySQL DDL is not
  transactional, so a half-applied migration also makes the next deploy's `migrate --force` fail. Check the
  GitHub Actions deploy log.
- `users.password` NOT NULL: new Google users cannot be created.
- `login_error=session` only on some browsers: check `SESSION_DOMAIN`, `SESSION_SAME_SITE` and
  `SESSION_SECURE_COOKIE` against the HTTPS host users actually visit.
- A login that "works" for one person proves little. The callback route is in the `guest` group, so a user who
  already has a session or remember cookie is redirected away and never reaches it. Test in a fresh incognito window.

## Questions to answer

1. Did the successful login go through the callback in a fresh incognito window?
2. Did the last deploy's `migrate` step succeed? Check the Actions log.
3. Was `config:cache` re-run after `.env` edits?

## Running the MySQL tests locally

The callback tests also run on MySQL/MariaDB (`tests/Feature/Auth/GoogleAuthMysqlTest.php`, skipped on SQLite):

```
docker exec mariadb sh -c 'mysql -uroot -p"${MARIADB_ROOT_PASSWORD:-$MYSQL_ROOT_PASSWORD}" -e "CREATE DATABASE IF NOT EXISTS ku_phumpanya_test"'
DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3306 DB_DATABASE=ku_phumpanya_test DB_USERNAME=root DB_PASSWORD=... php artisan test --filter=GoogleAuth
```

The local MariaDB may not match the KU MySQL version; compare with `SELECT VERSION();` on KU.
