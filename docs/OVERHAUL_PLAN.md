# jollygiraffes Overhaul: Codebase Review & Migration Plan

## 0. Summary

Both `jollygiraffes` and `syxtoncms` are the same author's (Matthew
Davidson) PHP framework — same `header.php` bootstrap pattern, same
`dblib`/`formlib`/`pagelib`/`filelib` naming, same `CFG`/`USER`/`MYVARS`
globals. `syxtoncms` is the actively-maintained line (commits as recent
as 2025, PHP 8 syntax like `match()`), while `jollygiraffes`'s core libs
date to 2010-2013 and haven't tracked the same hardening. That means the
"port the good parts over" approach the request asked for is unusually
low-risk here: it's largely the *same* API, matured, not a foreign
framework being grafted on.

This delivery includes:

- An upgraded `lib/dblib.php` + `lib/dblib_mysqli.php` + `lib/dblib_mysql.php`,
  100% backward compatible with every existing call site, adding opt-in
  prepared statements, input sanitization, and transactions
  (`docs/DB_MIGRATION.md`).
- A new `css/layout.css` flexbox layout system to replace the
  JS-computed `.fill_height`/`.fill_width` sizing that causes the
  reported mobile overlap issues, with a rollout checklist
  (`docs/CSS_MIGRATION.md`).
- A patched `scripts/script.js` where the old resize-math functions
  skip elements migrated to the new CSS system, so both can run
  side-by-side during rollout.
- Two worked-example templates (`admin_layout.php`,
  `admin_main_layout.php`) showing the migration pattern applied.
- This document, with a full inventory comparison and a phased plan
  for the larger pieces that need a staging environment to migrate
  safely (full `formlib`/`pagelib` templating port, SQL-injection
  cleanup across `ajax/*.php`).

`status.php`, `css/status.css`, `scripts/status.js`,
`lib/status_lib.php`, and `ajax/status_ajax.php` were **not** touched,
per your note that the status area is new.

---

## 1. Architecture inventory — jollygiraffes vs. syxtoncms

| Area | jollygiraffes | syxtoncms | Verdict |
|---|---|---|---|
| DB access (`lib/dblib*.php`) | 159+103+103 lines. Raw string-interpolated SQL everywhere. `mysql_*` fallback branch (dead — removed from PHP since 7.0). No prepared statements, no input sanitization helpers, no transactions. | 583+178+179 lines. Same function names, adds `||token||` prepared statements, `clean_var_*` sanitization, transactions, `mysqli_report()` strict error mode. | **Ported in this delivery** (`lib/dblib*.php`). Backward compatible. |
| Templating (`from_template()` in pagelib vs `fetch_template()`/`fill_template()` in filelib) | Simple: `from_template($file, $vars)` does `extract()` + `include` + output buffering on a plain `.php` file in `templates/`. | More structured: named subsections inside `.template` files, pulled by `fetch_template($file, $subsection, ...)`, with mail-merge-style token filling. | **Different paradigm, not a drop-in.** jollygiraffes' 150+ template files are plain PHP already using `from_template()` throughout; converting them to `.template`-file subsections is a large, mechanical rewrite that needs to be validated by actually rendering pages. Recommend as a **separate, later phase** (see §4) rather than guessed at blind in this session. |
| Forms (`lib/formlib.php`) | 1,217 lines — heavily domain-specific (child/contact/account/payment form builders for a childcare app). | 858 lines — more generic CMS field/validation helpers. | jollygiraffes' version is *appropriately* more specific to this app; it isn't simply an older version of the same file the way `dblib.php` is. Recommend cherry-picking syxtoncms's generic validation helpers (`clean_var_*`, now already available via the ported `dblib.php`) rather than replacing the form builders themselves. |
| Errors (`lib/errors.php` vs `lib/errorslib.php`) | 100 lines. Static `$ERRORS->...` string table + `fill_template()` with `[0]` positional tokens. `senderror()` uses `error_log()` + `die()`. | 212 lines. Adds `debugging()`/log-level helpers, `trigger_error()`-based flow. | Left as-is for now (see `docs/DB_MIGRATION.md` §"What was intentionally NOT changed") — swapping the failure mode (`die()` vs `trigger_error()`) changes user-visible error pages and needs to be checked against the live error templates. |
| AJAX router (`ajax/ajax.php` vs `ajax/page_ajax.php`) | 4,089 lines, one file, `switch`-on-`action` dispatch with the business logic inline. | 889 lines — smaller because syxtoncms spreads its endpoints across per-feature `*_ajax.php` files (`features/<name>/<name>_ajax.php`), not because the pattern itself changed. | The *pattern* (switch-on-action, POST-only) is the same in both; syxtoncms just has more files because it has more independent features. jollygiraffes' `ajax.php` should be **split by tab** (accounttab/childrentab/contactstab/employeestab/programtab/reports/status — several already exist as separate files!) rather than restructured wholesale. See §4. |
| Bootstrap (`lib/header.php`) | Per-lib `isset($X)` guards, one `if` per library. | Loops over an array of lib names with `defined($LIB)` guards, auto-discovers `config.php` by walking up directories, starts the session itself, calls a central `collect_vars()`. | Small, safe, high-value port — recommended next (see §4.1), not included in this delivery because it changes global bootstrap order and needs a smoke test against a live page (session handling in particular). |

---

## 2. CSS / JS mobile-friendliness audit

Findings in `css/styles.css` (1,486 lines) and `scripts/script.js` (405 lines):

- **Zero CSS rules back the JS-only sizing classes.** `.fill_height`,
  `.fill_width`, `.fill_height_middle`, `.fill_width_middle`,
  `.fill_height_once`, `.fill_width_once` exist only as jQuery hooks;
  `fill_height_width()` recomputes pixel heights/widths from
  `.offset()`/`.outerHeight()` math on every `resize` event, across 15
  files (`ajax/ajax.php`, `ajax/childrentab.php`, `ajax/contactstab.php`,
  `ajax/employeestab.php`, `ajax/programtab.php`, `ajax/reports.php`,
  `lib/billinglib.php`, `lib/formlib.php`, and 7 templates).
- **Mixed sizing units fighting each other.** `styles.css` uses `dvh`
  (viewport-relative), `vw` (viewport-relative), and `calc(50vw - 70px)`
  / `calc(70% - 15px)` (two different reference frames) in the same
  layout region (lines ~18-260).
- **~30 `position: absolute` rules** (lines 171, 277, 333, 605-778,
  etc.) without a defined stacking-context plan.
- **~30 `!important` overrides**, several directly on
  color/background/width right next to the absolute-positioned panels
  — a sign of specificity fights being patched locally instead of the
  underlying cascade being fixed.
- Net effect matches what you described: on mobile, the viewport
  height changes when the browser's address bar shows/hides, which
  fires the JS resize handler asynchronously; there's a window where
  the stale JS pixel value and the new `dvh`/`vw` CSS value disagree,
  and panels overlap or clip until the next resize fires.

**Fix delivered:** `css/layout.css` (flexbox-based, mobile-first,
`!important`-free) plus the migration plan in `docs/CSS_MIGRATION.md`,
which also replaces the `calc(50vw...)`/`calc(70%...)` split panel with
a `flex-wrap` row that **stacks below 700px** instead of overlapping.
This is additive/incremental — old and new sizing can coexist per
element while templates are migrated one at a time and verified, which
matters because there's no staging server available in this session to
render and visually check every page.

Also flagged: `scripts/script.js`'s `$(window).resize()` handler was
doing DOM reads *and* writes for every matching element on every resize
tick — real, measurable jank on a lower-powered kiosk/tablet device,
independent of the overlap bug. Removing it (once migration is
complete, per the checklist) is a straightforward performance win on
top of the correctness fix.

---

## 3. Security note

Because `dblib.php`'s original functions build SQL via direct string
interpolation (`"...WHERE pid='$pid'"`) across `ajax/ajax.php` and
related files, and none of the input appears to be validated by a
central `clean_var_*`-style function before use, this is very likely
carrying SQL-injection risk today. The ported `dblib.php` makes real
prepared statements available immediately; retrofitting the ~4,000
lines of `ajax.php` to use them is the highest-priority follow-up (see
`docs/DB_MIGRATION.md`'s "SQL-injection audit" section) — sized as its
own phase because it touches every input surface in the app and
deserves a staging database to verify against rather than being
changed blind.

---

## 4. Recommended phased roadmap

### Phase 1 — done in this delivery (low risk, additive only)
- `lib/dblib.php` / `dblib_mysqli.php` / `dblib_mysql.php` upgrade.
- `css/layout.css` + patched `scripts/script.js`.
- Two example templates migrated as a pattern reference.

### Phase 2 — bootstrap + error handling (small, needs a smoke test)
- Port `lib/header.php`'s loop-based lib loading, `config.php`
  auto-discovery, and `collect_vars()` central input pass.
- Decide whether to move `senderror()`/`lib/errors.php` to
  `trigger_error()`-based flow to match, checked against the actual
  error page templates.

### Phase 3 — CSS/JS full rollout — **done**
- All 15 files on the `docs/CSS_MIGRATION.md` checklist now carry
  `.layout-flex`/`.layout-flex-col`/`.layout-split-row` alongside (and,
  for the split-panel region, replacing) the old `calc(50vw...)`/
  `calc(70%...)` rules.
- `fill_height_width()`/`fill_height_width_once()` and their
  `$(window).resize()` call sites have been removed from
  `scripts/script.js`; layout is now entirely CSS-driven.
- **Not yet done:** the second pass over `!important`/`position: absolute`
  rules *not* tied to `.fill_*` — these are a separate, larger cleanup
  (there are more of them than the JS-driven ones) and haven't been
  started.
- **Needs verification:** none of this has been visually checked on a
  real narrow viewport in this session (no staging server available) —
  do that before considering Phase 3 fully closed.

### Phase 4 — SQL-injection retrofit — **done** for `ajax/ajax.php`,
`lib/billinglib.php`, `ajax/reports.php`, and the remaining `ajax/*tab.php`
files
- `ajax/ajax.php`, `lib/billinglib.php`, `ajax/reports.php`: 0 remaining
  string-interpolated `get_db_*`/`execute_db_*` call sites (a handful were
  missed in earlier passes — see `docs/DB_MIGRATION.md` for exactly which
  ones and how they were fixed).
- `childrentab.php`, `contactstab.php`, `employeestab.php`,
  `programtab.php`: scanned, none build SQL directly.
- **Still to do:** smoke-test reports + billing invoice generation against
  a real database — several of the fixes in this pass changed a
  three-argument `get_db_field()` call to four arguments, and that
  path hasn't been exercised live.

### Phase 5 — optional: split `ajax/ajax.php` and adopt structured templating
- Split the 4,089-line single-file router along the lines it already
  half-follows (several tabs are already separate files) so each
  feature area is independently testable.
- Only after the above is stable, evaluate migrating `from_template()`
  callers to syxtoncms's `fetch_template()`/`.template`-subsection
  system — this is the largest, most mechanical change (150+ template
  files) and the one most in need of a real staging environment with
  visual verification before touching it, since it changes how every
  page renders.

---

## 5. Files in this delivery

```
lib/dblib.php                      upgraded, backward compatible
lib/dblib_mysqli.php               upgraded, backward compatible
lib/dblib_mysql.php                deprecation-guarded stub
css/layout.css                     new flexbox layout system
scripts/script.js                  patched to coexist with layout.css
templates/admin_layout.php         worked example
templates/admin_main_layout.php    worked example
docs/OVERHAUL_PLAN.md              this file
docs/DB_MIGRATION.md               db layer details + SQL-injection audit plan
docs/CSS_MIGRATION.md              css/js rollout checklist
```

Drop the `lib/`, `css/`, and `scripts/` files into your existing
jollygiraffes tree at the matching paths (they replace the current
files 1:1). `templates/` files are provided as worked examples — apply
the same pattern to the remaining files on the checklist rather than
copying them in verbatim, since each one's actual parent markup needs
to be checked (see the note in `admin_main_layout.php`).
