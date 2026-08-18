# Database Layer Upgrade: `lib/dblib*.php`

## What changed

Ported from `syxtoncms/lib/dblib.php` (rev 1.7.7) and
`dblib_mysqli.php` (rev 1.1.1), which are a matured evolution of the
exact same library jollygiraffes still runs (same author, same
original function names — `get_db_row`, `get_db_result`,
`execute_db_sql`, `get_db_count`, `dbescape`, etc.).

**Nothing about existing call sites needs to change.** Every function
kept its original argument order; new parameters were only ever
appended at the end with defaults, e.g.:

```php
// old:
function get_db_row($SQL, $type = false)
// new:
function get_db_row($SQL, $type = false, $vars = [])
```

A grep across the whole jollygiraffes codebase found only 16 files
calling these functions, and every call site inspected passes just
`$SQL` (raw interpolated strings like
`"SELECT * FROM programs WHERE pid='$pid'"`). Those continue to work
unmodified.

## What's new (opt-in)

1. **Real prepared statements.** Pass a `$vars` array and use
   `||name||` tokens in the SQL instead of interpolating PHP variables
   directly:

   ```php
   // Before (string interpolation — SQL injection risk if $pid
   // isn't already sanitized upstream):
   $program = get_db_row("SELECT * FROM programs WHERE pid='$pid'");

   // After (parameterized, same function, same return value):
   $program = get_db_row("SELECT * FROM programs WHERE pid = ||pid||", false, ["pid" => $pid]);
   ```

   Internally this compiles to a real `mysqli_prepare()` /
   `mysqli_stmt_bind_param()` call — the value is never concatenated
   into the SQL string.

2. **Input sanitization helpers** — `clean_var_opt()`, `clean_var_req()`,
   `clean_param_opt()`, `clean_param_req()` — typed coercion for
   `int`, `float`, `string`, `array`, `object`, `json`, `bool` pulled
   straight from `$_GET`/`$_POST`/`$_REQUEST` in one call, e.g.:

   ```php
   $pid = clean_param_req($_POST, 'pid', 'int');
   ```

3. **Transactions** — `start_db_transaction()` / `commit_db_transaction()`
   / `rollback_db_transaction()`, useful for the billing/payment code
   in `lib/billinglib.php` where multiple related INSERT/UPDATEs
   currently have no atomicity guarantee.

4. **Error visibility** — `set_db_report_level()` turns on
   `MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT` so failed queries throw
   instead of silently returning `false`, and `get_db_error()` /
   `get_db_errorno()` expose the underlying mysqli error for logging.

## What was intentionally NOT changed

- `senderror()` still does `error_log()` + `die()` rather than
  `trigger_error()`, to avoid changing jollygiraffes' existing error
  page behavior. Recommend switching to `trigger_error()` in a later,
  separate pass alongside `lib/errors.php`, once there's a way to
  verify the current error-page templates against it.
- `get_db_field()` / `get_db_count()` were left structurally the same
  (rather than syxtoncms's rewritten `SELECT COUNT(*) FROM (subquery)`
  form) since jollygiraffes' version is simpler and none of the call
  sites need the subquery form's extra correctness on complex `JOIN`s.
  Worth revisiting only if a specific report query needs it.

## `lib/dblib_mysql.php` — recommend deletion

This file wraps the `ext/mysql` extension, which was **removed from
PHP entirely in PHP 7.0** (December 2015). If `$CFG->dbtype` is ever
set to anything other than `"mysqli"`, the app cannot run on any
PHP version currently supported/security-patched. It's been kept as a
clearly-labeled stub that fails with an actionable error message
instead of a confusing "call to undefined function", but the
recommendation is:

1. Confirm `$CFG->dbtype = "mysqli"` in `config.php` (should already be
   the case in practice).
2. Delete `lib/dblib_mysql.php` and the `dbtype` branch in
   `lib/dblib.php`/`reconnect()`.
3. Remove the `dbtype` option from `config-sample.php` entirely, since
   there is only one viable driver.

## SQL-injection audit — recommended next step

The prepared-statement capability is now available, but it hasn't been
retrofitted onto the ~4,000-line `ajax/ajax.php` or the other 15 files
that build SQL via string interpolation — that's a much larger,
higher-risk pass that deserves its own review cycle (ideally with a
staging DB to test against, which wasn't available in this session).
Recommended order, highest-risk first:

1. `ajax/ajax.php`, `ajax/reports.php` — handle the most
   user-influenced input (search fields, report filters).
2. `lib/billinglib.php` — handles money; also the best candidate for
   wrapping in `start_db_transaction()`/`commit_db_transaction()`.
3. Everything else in `ajax/*.php`.

A safe migration pattern per query: change the interpolated variable
to a `||name||` token, add the `$vars` array as the new trailing
argument, and diff the generated SQL (log it once) against the old
version to confirm it's identical before deploying.
