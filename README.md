# Daily Status Report - Install Notes

## What this adds
- `https://yoursite.com/status?c=Smith` - read-only daily status page for parents (PIN-protected).
- `https://yoursite.com/status` - staff/admin page (unlocked with the admin PIN) to set each
  child's status: mood timeline, tally counters (diaper/potty/clothing), a menu of the day, and
  notes.

## Installation
Unzip and copy these into your existing site, preserving the folder structure:

```
lib/status_lib.php        -> yoursite/lib/status_lib.php
ajax/status_ajax.php       -> yoursite/ajax/status_ajax.php
status.php                 -> yoursite/status.php
css/status.css             -> yoursite/css/status.css
scripts/status.js          -> yoursite/scripts/status.js
```

Nothing else needs to change. The first time `status.php` (or its AJAX endpoint) runs, it
automatically:
- Adds `chid`, `aid`, `daykey`, `timelog` columns to your existing `events` table (used to log
  mood/tally taps via the same `tag` column your check-in/out events already use - your original
  `in`/`out` event rows are untouched).
- Adds a `daykey` column to your existing `notes` table (so the "note area" entries can be scoped
  to a child + calendar day; older, unrelated notes are never shown on the status page).
- Adds a `link_code` column to `accounts` (the `?c=Smith` part of the parent link).
- Creates one new table, `status_menu`, for the daily menu text per child.

This all runs automatically - no manual SQL needed, and it's safe to load even if it's already
been run before.

## Setting up each family's link
1. Go to `/status` and log in with the admin PIN.
2. Tap "Family Links" in the top right.
3. Each family gets an auto-generated link code (from their account name), which you can edit to
   anything you like (e.g. "Smith"). Save, then use "Copy Link" to grab the full URL to text or
   email to the family.
4. Parents use their existing 4-digit account PIN to log in at their link - the same PIN they
   already use for check-in/out.

## Using the staff/admin view
- Pick a child from the dropdown at the top.
- Tap a mood button to log it (adds a timestamped entry to today's mood timeline - tap as many
  times through the day as moods change).
- Tap "+" on any tally (diaper change, potty success, potty accident, clothing change) each time
  it happens; "-" undoes the most recent tap if you tap by mistake.
- Type today's menu and tap Save.
- To add a note: choose a tag from your existing note categories, write the note, and check
  "Notify parent at sign-out" if you want the `notify` flag set on it (the same field your
  sign-out bulletin feature already checks).
- All of the above only ever applies to **today** - there's no back-editing past days from the
  admin view, by design.

## Using the parent view
- After entering their PIN, parents see today's mood timeline, tally counts, menu, and notes for
  their child (a tab bar appears at the top if they have more than one child enrolled).
- Swipe left/right (or tap the arrows) to look back at previous days. It won't go past today or
  more than ~90 days back.
- Everything is read-only for parents.

## One thing worth fixing on your end
Your `config.php` currently has:
```php
$CFG->timezone = 'America/Indianapolis';
```
`America/Indianapolis` isn't a valid PHP/IANA timezone name - it should be
`America/Indiana/Indianapolis`. As written, this will throw an error anywhere the code calls
`get_today()` or this feature's day-bucketing logic. This looks like a pre-existing typo in your
config rather than something introduced by this feature, but it's worth correcting since both the
existing app and this new page rely on `$CFG->timezone` being valid.
