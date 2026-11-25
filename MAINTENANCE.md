# BEC FaceWall — maintenance and deployment

See [README.md](README.md) for shortcodes and architecture. The private repository is BertieBat/bec-facewall; WordPress loads bec-facewall/bec-facewall.php. GitHub merges do not deploy to WordPress.

## Routine updates

Back up plugin files and CiviCRM database, verify a clean checkout, confirm relevant CI checks pass and test on staging against the real custom-field and reporting-table schema. Then:

```sh
cd /home/bertie/public_html/wp/wp-content/plugins/bec-facewall
git status --short
git remote -v
git pull --ff-only origin main
```

Origin should point to BertieBat/bec-facewall using the existing authenticated transport. Stop if unexpected local changes or errors occur. Ordinary updates need no deactivation. Clear WordPress, CDN and browser caches.

### Verification checklist

- BEC FaceWall opens Diagnostics; Diagnostics, Shortcodes and Photo Repair work. The old preview and poster menu entries are retired.
- Check current, grace, LYBNTY, life, student/under-30, probationary, joint (both sections), historical text/grid and officer/year views, expected counts, year="current", AGM rollover, historic dates and prorata fees.
- Check consent, both joint partners, placeholders, image fallbacks, hover/focus history, mobile/touch layout (1, 2, 5, 6 and 11 cards), year headings and footer.
- Check sticky Colibri navigation on ordinary and FaceWall pages. Theme menu colours belong in WordPress Additional CSS.
- Where affected, verify the existing administrator-only nonce-protected A1/RA1/SRA1 poster download flow (not retired menu links); consenting members only, streamed PDFs rather than public files.
- Check schedules and PHP logs. Do not rebuild reporting data merely to deploy PHP-only or documentation-only changes.

## Reporting rebuild and diagnostics

The facewall_cron event rebuilds four reporting tables in one InnoDB transaction under an advisory lock. Existing schedules are preserved; if absent, an administrator visit creates a weekly fallback starting one hour later. The retired direct PHP cron stub is not a supported execution method.

Use BEC FaceWall → Diagnostics → Queue one reporting rebuild. The nonce-protected administrator action queues WP-Cron, not a public request rebuild. Diagnostics records attempts, success, stage, row counts, timings and next run without member details. Unknown prior status is not success. Failure or success older than eight days generates an admin warning. Optional failure emails default off, go to the site admin and are limited to one per 24 hours.

Before a first or changed rebuild, back up and check the actual reporting database:

```sql
SELECT TABLE_NAME, ENGINE
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN (
    'civicrm_BEC_historical_membership',
    'civicrm_BEC_historical_summary',
    'civicrm_BEC_historical_officer_membership',
    'civicrm_BEC_current_year'
  );
```

All four rows must exist and be InnoDB. Missing tables, unknown engines or insufficient metadata access cause refusal before writes. If no rows are returned, confirm selected schema and permissions. Never convert live engines without backup and compatibility review.

Check conflicting membership numbers on the actual CiviCRM database:

```sql
SELECT cust.Membership_Number,
       COUNT(DISTINCT c.id) AS distinct_contacts
FROM civicrm_contact AS c
JOIN custom_value_1_BEC AS cust ON cust.entity_id = c.id
JOIN civicrm_membership AS mem ON mem.contact_id = c.id
WHERE mem.membership_type_id IN (1,2,3,4,5,6,7,18)
  AND mem.status_id < 90
  AND mem.is_test = 0
  AND cust.Membership_Number BETWEEN 1 AND 4999
GROUP BY cust.Membership_Number
HAVING COUNT(DISTINCT c.id) > 1
ORDER BY cust.Membership_Number;
```

Ideally no rows. Investigate duplicates in CiviCRM; do not disable the guard or automatically reassign numbers. Historical summaries group by contact ID and membership number and preserve period formatting.

### Failure and recovery

- Non-InnoDB/unknown engine: refuse before DELETE.
- Advisory lock held: skip without overwriting current status.
- Unavailable or unexpectedly empty source, SQL error, exception or zero-row historical table: abort and roll back all four tables.
- Zero-row chart table is allowed where no current-year data exists.
- Failed diagnostic write after COMMIT does not undo committed data.
- Failed ROLLBACK is uncertain: investigate manually before retrying.

Previously committed tables should remain readable during the transaction. Run first staging rebuild outside busy hours due to undo logs and row locks. Verify stage counts and sample summaries; inject failures only on staging. Crash recovery remains the database server's responsibility.

## Privacy, photographs and host security

Public membership displays require live affirmative custom_value_1_BEC.facewall_public_84 = 1; missing/unrecognised values fail closed. Both joint partners must consent. Historical reporting tables are internal. Verify 1 is the intended club consent setting. Public rendering never creates thumbnails, downloads arbitrary photos or writes/truncates the relationship cache. Use supported CiviCRM maintenance for incomplete relationship caches.

Use BEC FaceWall → Photo Repair for batched cache repair and the read-only poster audit to diagnose missing originals/caches. Original CiviCRM and approved application photos remain unmodified; see README for allowed names and cache paths. Posters prefer full-size caches, falling back read-only to smaller caches.

The plugin .htaccess denies direct HTTP access to PHP/PDF/TXT/log and other sensitive extensions on Apache 2.4+; Nginx requires equivalent rules. Check the live server for legacy artifacts and remove only if confirmed obsolete:

- wp-content/plugins/bec-facewall/zzpluralzalpha/ (old Mailman files)
- wp-content/plugins/bec-facewall/facewall_cron.txt (old log)
- wp-content/plugins/bec-facewall/poster_output/*.pdf (old generated posters)

Never delete wp-content/uploads/civicrm/custom or application photo uploads. FaceWall consent does not itself revoke access to original uploaded URLs or previously published PDFs. Audit host upload permissions and external URLs separately. Avoid restoring releases with known privacy defects.

## Automated testing

The authoritative test list is .github/workflows/facewall-php-checks.yml. It runs PHP 8.2/8.4 syntax and mocked regressions plus MariaDB integration. Representative local checks from the plugin root:

```sh
find . -maxdepth 2 -name '*.php' -print0 | xargs -0 -n1 php -l
php tests/facewall-regression.php
php tests/joint-membership-regression.php
php tests/facewall-security-regression.php
php tests/shortcodes-and-style-regression.php
php tests/admin-menu-regression.php
php tests/release-b-render-snapshots.php
```

The 41 historical rendering snapshots cover eight routines and officer-year boundaries, normalizing elapsed probationary duration and installation-dependent placeholder paths. Other tests cover query filters, calendars, joint eligibility, photo repair, PDF layout, routing, grid markup, locks, rollback and duplicate-number guards. Disposable MariaDB fixtures exercise actual rebuild functions and injected failures, not production records. Never point disposable tests at a live database. CI cannot prove real-data accuracy, browser rendering, original-image protection or live scheduling.

## Rollback and compatibility

Restore a previously tested plugin revision from backup or deploy a reviewed Git revert, clear caches and verify. No schema rollback is needed for code-only updates without schema changes. Committed reports may need regeneration through controlled maintenance under a compatible revision. Investigate uncertain database rollback before retrying.

The directory and repository ownership migration was completed in November 2026. Historical migration/reversal instructions are in Git history (formerly RENAME_DEPLOYMENT.md). Never activate both old and new plugin copies. A future plugin identity change requires deactivation before renaming and reactivation afterward; do not uninstall or delete reporting tables or duplicate cron jobs.

Retain legacy assets and retired cron stub until external server/template dependencies have been checked. Previous release-specific documents remain in Git history.
