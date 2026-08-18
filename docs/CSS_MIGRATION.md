# CSS Layout Migration: JS pixel math → Flexbox

## The problem, concretely

`css/styles.css` has no rules at all for `.fill_height` / `.fill_width` /
`.fill_height_middle` / `.fill_width_middle` / `.fill_height_once` /
`.fill_width_once`. Every one of those is a bare hook that
`scripts/script.js` reads with jQuery `.offset()`/`.outerHeight()` and
turns into an inline `px` height/width — recomputed on every `resize`
event:

```js
$('.fill_height').each(function() {
    var offset = $(this).offset();
    var poffset = $(this).parent().offset();
    ...
    $(this).height((poffset.top + $(this).parent().height() - pmargins) - offset.top - margins);
});
```

On top of that, `css/styles.css` mixes `dvh`, `vw`, `calc(50vw - 70px)`,
`position: absolute`, and about 30 separate `!important` overrides in the
same layout regions (e.g. lines 71, 92, 128, 171, 180, 199, 240, 277,
333, 605-778). Each of those is a different sizing system fighting for
the same pixel:

- `vw`/`dvh` size relative to the *viewport*, not the parent element.
- `calc(50vw - 70px)` breaks the moment the viewport is narrower than
  ~140px wider than the subtracted constant — exactly what happens on
  phones.
- `position: absolute` panels stacked without a defined stacking
  context (no `isolation`/`z-index` plan) overlap unpredictably once
  their vw/dvh-based sizes disagree.
- The JS resize handler runs *after* the browser has already painted,
  and on mobile the viewport height itself changes when the address
  bar shows/hides — so there's a visible window where the stale JS
  pixel value and the new CSS vw/dvh value disagree and panels overlap
  or clip. This matches the "strange overlapping areas" reported.

## The fix

`css/layout.css` (new file, included in this delivery) replaces the JS
math with flexbox. A parent becomes a flex column/row
(`.layout-flex-col` / `.layout-flex-row`), and children marked
`.layout-flex` grow to fill the remaining space automatically — no JS,
no resize listener, no stale values, and it responds instantly to
on-screen-keyboard/address-bar changes because it's driven by the
browser's own layout engine, not a `setTimeout`/`resize` callback.

## Rollout strategy (safe, incremental)

This does **not** require a big-bang rewrite of every template. Roll it
out file by file:

1. Add `css/layout.css` to the page `<head>` (after `styles.css`, so it
   can override where needed) — see `header.html`.
2. For a given template, add `.layout-flex-col` (or `-row`) to the
   *parent* of the element that currently has `.fill_height`/
   `.fill_width`, and add `.layout-flex` alongside the existing
   `.fill_height`/`.fill_width` class on the child itself. Do **not**
   remove the old class — `script.js` has been patched to skip any
   element that also has `.layout-flex` (`:not('.layout-flex')`), so
   old and new can coexist per-element while you migrate.
3. Test that template on a real narrow viewport (or Chrome DevTools
   responsive mode with the address-bar-hide simulation) before moving
   to the next file.
4. Once every file below has been migrated and verified, delete
   `fill_height_width()` / `fill_height_width_once()` from
   `scripts/script.js`, remove the `$(window).resize(...)` call to
   them, and remove the plain (non-`.layout-flex`) class names from
   templates.

## Worked example (included in this delivery)

`templates/admin_layout.php` — `#admin_display` changed from:

```html
<div id="admin_display" class="admin_display fill_height">
```

to:

```html
<div id="admin_display" class="admin_display fill_height layout-flex">
```

**Remaining step for this file** (not guessed at in this delivery,
because the enclosing wrapper lives in the string built by
`ajax/ajax.php` around line 595 and needs to be confirmed against the
live page, not assumed): the parent of `.admin_menu` + `#admin_display`
needs `layout-flex-col` added so the flex context exists. Search for
where `from_template("admin_layout.php", ...)` is echoed into the page
and add the class to its wrapping `<div>`.

## Full checklist — every file currently using `.fill_height`/`.fill_width`

- [x] `templates/admin_layout.php` — child class added in this delivery
- [x] `templates/admin_main_layout.php` — child class added in this delivery
- [x] `ajax/ajax.php`
- [x] `ajax/childrentab.php`
- [x] `ajax/contactstab.php`
- [x] `ajax/employeestab.php`
- [x] `ajax/programtab.php`
- [x] `ajax/reports.php`
- [x] `lib/billinglib.php`
- [x] `lib/formlib.php`
- [x] `templates/alphabet.php`
- [x] `templates/checkinout_contact_selector.php`
- [x] `templates/employee_signinout_layout.php`
- [x] `templates/inoutform1.php`
- [x] `templates/inoutform2.php`

## The split-panel overlap (account/child selector, ~styles.css:180-260)

That region uses `width: calc(50vw - 70px)` next to `width: calc(70% -
15px)` — two different reference frames (viewport vs. parent) for two
panels meant to sit side by side. Replace with `.layout-split-row`
(also in `css/layout.css`), which uses `flex: 1 1 320px` so panels
share space proportionally down to 320px each, then **stack instead of
overlap** below 700px width via the included media query. This directly
targets the mobile-friendliness requirement — panels never fight for
the same horizontal pixel because there's no `calc()` involved.

## Explicitly out of scope

`css/status.css`, `scripts/status.js`, `status.php`, `lib/status_lib.php`,
`ajax/status_ajax.php` — per direction, the status area is new and
should not be touched by this pass.


## Completion (this pass)

All checklist files above now carry `.layout-flex` alongside their legacy
`.fill_*` classes. Parents that need a flex context were updated:

- `templates/admin_layout.php` — wrapped `.admin_menu` + `#admin_display`
  in `.admin_layout_wrapper.layout-flex-col`
- `templates/admin_main_layout.php` — wrapped the three containers in
  `.admin_main_layout.layout-flex-col`
- `templates/selectable_list_split_item.php` — wrapped left/right panels
  in `.layout-split-row` (replaces `calc(50vw…)` / `calc(70%…)` fighting)

`css/styles.css` no longer uses `calc(50vw - 70px)` or `calc(70% - 15px)`
on `.list_links` / `.list_box_item_left` / `.list_box_item_right`; those
use flex basis instead.

`scripts/script.js`: `fill_height_width()` and `fill_height_width_once()`
have been removed, along with their `$(window).resize` and `refresh_all`
call sites. Layout is now entirely CSS-driven via `layout.css`.

Status area (`status.css` / `status.js` / etc.) remains out of scope.

## Admin grid (follow-up)

`templates/admin_main_layout.php` now uses `.layout-grid-admin` (CSS Grid)
instead of float + flex for the list | actions | info workspace. Rules live
in `css/layout.css`. Expanded state: add `.is-expanded` on
`.admin_main_layout` and/or keep legacy `.expanded` on children (`:has()`
supported in modern browsers).
