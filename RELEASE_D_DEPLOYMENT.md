# FaceWall Release D — housekeeping and documentation (v1.1.8)

## Summary of code changes

1. The public shortcode routing and FaceWall → Shortcodes administrator
   catalog now use ONE definition in includes/shortcodes.php. All 12 scopes
   keep their original handlers and argument lists, including the two-part
   student and joint-member pages.
2. Eight unregistered legacy administrator list callbacks and their
   commented-out menu entries have been deleted. The original FaceWall
   current-member admin preview, 3 poster menus, Shortcodes, Diagnostics
   and Photo Repair remain available.
3. The obsolete facewall_pad_grid helper is deleted: its old blank divs
   are not used after the responsive CSS Grid changes.
4. The old, unused image-directory and thumbnail configuration assignments
   have been removed from public photo view functions. The real thumbnail
   generator, database queries and membership eligibility are unchanged.
5. Two identical joint-card rules inside CSS @media queries were removed.
   The main joint-grid rule still uses 2 columns (1 at <=380px). The
   separate Shape5 navigation correction and inline image-overlay fallback
   were deliberately NOT modified because the site has had CSS regressions.
6. The CI workflow now runs Release D routing tests and triggers on CSS
   edits as well as PHP edits. The README documents supported scopes,
   ownership, privacy, maintenance and retained legacy files.

## Explicitly preserved

- All 41 SHA-256 Release B image-grid HTML snapshots.
- The previous under-30/AGM, 1960 history, and current committee-year rules.
- The existing public (type <8) and legacy admin/poster (type <9)
  membership eligibility distinction.
- Joint-member consent rules and recent CiviCRM API optimisations.
- Current versus historical photograph ordering, missing-photo placeholders,
  hover/focus behaviour and mobile card grid breakpoints.
- Four-table InnoDB transaction checks, advisory lock and rollback.
- Secured poster download, photo repair queue and diagnostics.
- Live website external references are UNKNOWN, so the old global CSS
  files, duplicated bat icons and retired cron stub have been retained for
  compatibility. FaceWall itself does not load those CSS files.

## Automated verification

- Existing PHP 8.2 and 8.4 syntax and regression suites.
- 41 exact HTML snapshots, unchanged since Release B.
- New tests that enumerate every supported shortcode, verify the precise
  handler and argument lists, and test composite joint/student call order.
- Legacy code audit tests: retired callbacks/padding absent, poster nonce
  remains in place and only configured scopes can dispatch.
- Existing full disposable MariaDB rebuild and transaction/rollback suite.
- CSS tests that prohibit FaceWall layout changes from leaking into global
  theme navigation and preserve the inline hover fallback.

## Live/staging checklist

1. Back up plugin files and CiviCRM database.
2. On staging, compare all FaceWall shortcode pages with v1.1.7:
   current, probationary, grace, LYBNTY, joint, life, student,
   officer/year, historical text/grid and prorata fees.
3. Verify FaceWall → Shortcodes lists all 12 active scopes and exactly
   matches what WordPress renders. Check year=current.
4. Ensure FaceWall admin current preview, A1/RA1/SRA1 PDFs,
   Diagnostics and Photo Repair are still accessible.
5. Test photo hover/focus overlays on desktop, keyboard and mobile/touch,
   and sticky Shape5/Vertex navigation before/after scrolling.
6. Verify 1-, 2-, 5-, 6- and 11-card layouts and the page footer.
7. Check CiviCRM reporting counts and a sample historical membership
   record, but do not run a rebuild just to deploy this PHP-only cleanup.
8. Clear all WordPress, CDN and browser caches after deploying.

Server update after successful staging verification:

    cd /home/bertie/public_html/wp/wp-content/plugins/bec-facewall
    git pull --ff-only origin main

No schema changes and no data migration are required. This release is
code/documentation housekeeping; it does not automatically change live
WordPress files when merged on GitHub.

## Rollback

Use the previously tested v1.1.7 code if an external theme or script
relies on one of the deleted legacy PHP functions. No database rollback
is required. Document any unexpected outside references before restoring
unused code permanently.

The old CSS assets and retired cron stub remain until the server-side
dependency audit confirms no direct external references exist.
