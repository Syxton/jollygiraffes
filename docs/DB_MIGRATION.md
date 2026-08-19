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

## SQL-injection audit — in progress (Phase 4)

Prepared statements are available. Retrofit is underway on the highest-risk
surfaces. `get_db_count()` now also accepts an optional `$vars` array.

### Completed in this pass

**`ajax/ajax.php`**
- `check_in_out_employee()` — employeeid + activity/notes inserts
- `get_check_in_out_form()` — pid filter
- `check_in_out_form()` — chid lookup + required-notes count
- `check_in_out()` — event lookup, balance helpers, activity + notes inserts
- `add_edit_payment()` — payment insert/update (typed clean + prepared)
- `add_edit_account()` — account insert/update (typed clean + prepared)
- `add_edit_employee()` — employee + wage history (typed + prepared)
- `add_edit_child()` — child + auto-enrollment insert
- `add_edit_contact()` — contact insert/update
- `add_edit_program()` — program rates insert/update
- `add_edit_expense()` — expense (negative payment) insert
- `delete_expense()`, `delete_payment()`, `delete_note()`, `delete_activity()`
- `delete_tag()` — prepared values + **whitelist** for dynamic table name
- `billing_overrides()` — nullable rates via prepared (null → SQL NULL)
- `add_edit_tag()` — whitelist table + prepared values
- `add_edit_note()` — note insert/update variants
- `save_required_notes()`, `delete_required_notes()`, `required_notes_sort()`
- `toggle_exemption()` — billing_perchild + invoice rebuild
- `delete_wage_history()`, `delete_document()`, `delete_program()` (transactional cascade)
- `add_edit_bulletin()`, `add_activity()`, `add_edit_notes()` (fixed column bug on aid insert)
- `copy_program()`, `activate_program()`, `deactivate_program()`
- `activate_account()`, `deactivate_account()`, `activate_employee()`, `deactivate_employee()`
- `toggle_contact_activation()`, `toggle_child_activation()`, `toggle_enrollment()`
- `validate()` — **auth/PIN paths** fully prepared
- List/get helpers: `get_notifications()`, `get_contact_name()`, form loaders
  (programs/accounts/children/contacts/employees), billing invoice sums,
  `required_notes_resort()`, `add_edit_employee_activity()`,
  `save_employee_timecard()`, `deactivate_employee_activity()`, etc.

Also: `find_var_type(null)` returns `'s'` so PHP null binds as SQL NULL.

**`ajax/ajax.php` status:** **0 remaining** string-interpolated `get_db_*` /
`execute_db_*` call sites (verified by scan; three sites missed in the
previous pass — `add_edit_program()`'s tag lookup, the account-selector's
`kid_count` in `get_account_selector()`, and the required-notes
`question_type` lookup — were converted to parameterized calls in this
pass). The two `$tagtype`/`$table`-interpolated `DELETE`/`SELECT`
statements in `add_edit_tag()`/`delete_tag()` are intentional and safe:
the table-name fragment is checked against a hardcoded `$allowed` whitelist
before use, and the `tag` value itself is passed as a `||tag||` bound
parameter.

**`lib/billinglib.php` status:** **fully converted** (0 remaining user-interpolated sites).

- `account_balance()`, `apply_overrides()`, `week_balance()`
- `make_account_invoice()` — prepared + transaction
- `save_child_invoice()` — discount sibling query + inserts prepared
- `get_child_week_attendance_list()`, `make_child_invoice()`
- `create_invoices()` — deletes, account/child loops, employee first-in
- `get_enrollment_method()`

**`ajax/reports.php` status:** **fully converted** (0 remaining
user-interpolated sites) — `$type` column whitelist, date-range tokens
(`||t_from||`/`||t_to||`), report-switch queries + invoice/timeline loops,
employee payroll, attendance-throughout-day, note_entry helpers. `$sql_vars`
passed to `get_db_result($SQL, $sql_vars)`. Six `get_db_field()` calls in the
`invoice_between` and `program_per_program_cash_flow` report branches were
left interpolated in the previous pass (payments/owed totals keyed on
`$pid`/`$aid`/`$id` plus a `$timesql`/`$timesql2` fragment, and the weekly
payroll sums keyed on `$id` + `$week["fromdate"]`/`["todate"]`) — these have
now been converted to bound `||token||` parameters in this pass, consistent
with the `get_db_result()` calls immediately next to them that were already
parameterized.

**Other `ajax/*.php` tab files** (`childrentab.php`, `contactstab.php`,
`employeestab.php`, `programtab.php`) — scanned, **0** interpolated
`get_db_*`/`execute_db_sql` call sites found; no SQL is built directly in
these files.

### Still to do (recommended order)

1. Smoke-test reports + billing invoice generation on staging (the
   `get_db_field()` conversions above change three-argument calls to
   four-argument calls — behavior should be identical, but hasn't been
   run against a live database in this session).
2. `status.php` / `ajax/status_ajax.php` / `lib/status_lib.php` remain
   explicitly out of scope per direction (status area is new).

### Safe migration pattern

```php
// Before
$row = get_db_row("SELECT * FROM t WHERE id='$id'");
execute_db_sql("INSERT INTO t (a,b) VALUES('$a','$b')");

// After
$row = get_db_row("SELECT * FROM t WHERE id = ||id||", false, ["id" => $id]);
execute_db_sql("INSERT INTO t (a,b) VALUES (||a||, ||b||)", ["a" => $a, "b" => $b]);
```

Prefer `clean_param_req` / `clean_var_opt` for incoming request values
before they enter `$vars`. Tokens may be written with or without surrounding
quotes (`'||id||'` or `||id||`); the prepare layer strips them.
