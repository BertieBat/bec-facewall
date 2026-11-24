# FaceWall Release B (v1.1.6) — shared photograph rendering

## Scope of this release

All existing photo card HTML now comes from the pure
facewall_render_member_card() function in includes/render.php.
The existing standard photo rows and officer-year heading rows share
facewall_render_grid_row_open().

The view functions stay in current_list.php and officer_grid_year.php.
**Their membership SQL, date calculations, privacy filtering, historical
summaries, photo fallback order, and displayed counters are unchanged.**

| View | Renderer call sites | Original behaviour retained |
| --- | ---: | --- |
| Legacy current member list | 1 | Name, photo, legacy anchor, no hover history |
| Current, under-30, probationary | 3 | Membership hover/focus history, name and periods |
| Historical all-members list | 1 | Historical ID/name and hover/focus history |
| Officer photo grid | 1 | Year breaks, role name, joining/leaving notes |
| Joint and unusual joint | 4 | Two independent cards per joint pair, privacy and history |

There are **10 shared card call sites across eight public display routines**.

## Regression protection

tests/release-b-render-hashes.json contains 41 SHA-256 snapshots generated
from the *pre-refactor* implementation, with counts 1, 2, 5, 6 and 11 plus
officer-year breaks. tests/release-b-render-snapshots.php now asserts all
41 HTML outputs remain byte-for-byte identical (except for normalization
of the naturally changing elapsed-duration phrase in the probationary view).
The fixture fixes the WordPress local date to 24 November 2026.

tests/release-b-renderer-regression.php checks the shared component's HTML
contracts, overlay, accessible keyboard focus, officer trailing text and all
10 converted card call sites. The existing PHP 8.2/8.4 lint tests, grid
balancing tests, privacy/image regressions and disposable MariaDB integration
tests also run.

These synthetic data fixtures are not a substitute for a real WordPress /
CiviCRM rendering check against the exact live Shape5/Vertex template.

## Deployment checklist

1. Back up the plugin directory and CiviCRM database.
2. On a staging copy, verify the following FaceWall shortcodes: current,
   probationary, under-30, joint, historical membership grid, officer photo
   grid (current and all). Confirm the existing officer-year headings and
   member totals.
3. Confirm the desktop membership summary appears on photograph hover,
   disappears on pointer exit, and is accessible by keyboard focus / tap.
4. Check the unusual joint-members section, member cards for people without
   a photograph and the original-photo fallbacks.
5. Inspect the sticky navigation on the FaceWall page after scrolling; this
   release must not change Shape5 menu colours, alignment or dropdown text.
6. Check mobile layouts at 1, 2, 5, 6 and more than 10 member cards. Verify
   there are no rogue closing DIVs and the external WordPress page footer
   remains visible.
7. Deploy the staging-tested version and clear website/CDN/browser caches.

The refactor introduces **no schema changes**, updates no live records and
does not affect WordPress scheduled tasks. All data mutations remain owned
by the existing maintenance jobs.

## What remains for Releases C / D

Release B deliberately leaves data retrieval and query optimisation alone.
Repeated SQL joins, CiviCRM API usage in unusual joint views, legacy
variables, unused CSS and non-rendering housekeeping can be considered in
Releases C and D without combining those changes with a rendering refactor.

Rollback: return to the previously tested FaceWall 1.1.5 code and clear any
object/page cache. This is a PHP-only update and requires no database rollback.
