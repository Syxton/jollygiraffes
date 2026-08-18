# CSS Migration patch — apply instructions

Extract over your jollygiraffes (or site) root, preserving paths:

```bash
cd /path/to/your/site
unzip -o css_migration_patch.zip
```

## Files included

- css/layout.css          — flex primitives + admin CSS Grid
- css/styles.css          — calc() → flex; grid overrides
- scripts/script.js       — fill_height_width*() removed
- docs/CSS_MIGRATION.md   — checklist + notes
- templates/*             — layout-flex / layout-grid-admin markers
- ajax/*                  — layout-flex on fill_* elements
- lib/billinglib.php, lib/formlib.php

## Requirements

- header.html already loads layout.css after styles.css (branch 2.0 does).
- Do not touch status.* (out of scope).

## Quick verify

1. Admin → Accounts/Programs: list left, actions+info right; no overlap on narrow width.
2. Resize / mobile: list stacks above info under 700px.
3. Check-in/out forms: middle pane fills without JS resize glitches.
