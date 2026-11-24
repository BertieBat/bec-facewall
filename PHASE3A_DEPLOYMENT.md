# FaceWall Phase 3A — deployment and operations

Phase 3A changes the maintenance pipeline, not the public shortcode layouts.
It protects FaceWall's four existing reporting tables by rebuilding all of
them inside a **single InnoDB transaction**. The result is either all four
updated tables or the last committed versions of all four tables.

## Mandatory checks before production deployment

1. Back up the live CiviCRM database and the FaceWall plugin files.
2. Confirm that these **existing** tables use InnoDB:

   - civicrm_BEC_historical_membership
   - civicrm_BEC_historical_summary
   - civicrm_BEC_historical_officer_membership
   - civicrm_BEC_current_year

   Read-only SQL for a database administrator:

       SELECT TABLE_NAME, ENGINE
       FROM information_schema.TABLES
       WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME IN (
           'civicrm_BEC_historical_membership',
           'civicrm_BEC_historical_summary',
           'civicrm_BEC_historical_officer_membership',
           'civicrm_BEC_current_year'
         );

   All four rows must exist and say InnoDB. Missing/unknown engine
   information, MyISAM, or missing metadata access makes the new maintenance
   job **refuse to modify the tables**. Never convert a live table's engine
   without a database backup and a separate compatibility review.

3. Stage the new plugin against a WordPress/CiviCRM copy. Verify the current
   members grid, Joint Members, past member and officer lists, and posters
   still match the intended privacy permissions established in Phase 2.
4. Go to WordPress **FaceWall → Diagnostics**. It shows the last attempt,
   success/failure, failure stage, per-stage row counts and timings, and next
   WordPress scheduled run. No member details are included.
5. Use **Queue one reporting rebuild**. The request is nonce protected,
   requires the manage_options capability, and runs on WP-Cron rather than
   inside a public page request.
6. Confirm all four stages succeed and reporting numbers are reasonable.
   Confirm that an injected staging-only failure preserves the previously
   committed reporting tables. No public visitor should trigger a rebuild.
7. Check PHP error logs for FaceWall maintenance errors, then deploy the
   staging-tested version to the live site.

## Scheduling and diagnostics

The old facewall_cron WordPress hook remains unchanged. If a scheduled event
already exists, the plugin preserves it. When a site administrator visits
WordPress and there is **no** scheduled facewall_cron event, the plugin creates
a weekly fallback schedule one hour later.

The diagnostics page displays a warning in wp-admin after a failed run,
or when the last successful run is over eight days old. A missing previous
run is shown as unknown rather than as success.

Optional failure emails are **off by default**. An administrator can enable
them on the Diagnostics page; they go only to the WordPress admin email
address and are rate limited to at most one delivery in 24 hours.

## Failure behaviour

- Non-InnoDB reporting table -> refuse before DELETE, with a diagnostic error.
- Second rebuild already running -> skip without overwriting current status.
- Source unavailable or unexpectedly empty -> abort and keep previous data.
- SQL insert failure or unexpected exception -> ROLLBACK all four tables.
- A historical table rebuilt with zero rows -> abort and ROLLBACK.
- A zero-row chart table is allowed when no current-year membership data exists.
- Failure storing diagnostics after COMMIT does not change the database result.

The four reporting-table schemas are unchanged. Previously committed data
should remain readable to other connections throughout the transaction.
Because SQL transactions may hold large undo logs and row locks, run the
first staged rebuild outside busy hours.

A failed database ROLLBACK is reported as **uncertain** and requires manual
investigation before the next retry. MySQL crash recovery and storage-engine
behaviour remain the responsibility of the DB server.

## Verification in GitHub CI

The pull request runs:

- PHP 8.2 and 8.4 syntax checks and existing security regression tests.
- Unit tests of lock failures, nontransactional engines, incomplete stages,
  rollback, zero-row safety and privacy-safe diagnostics.
- Real MariaDB tests that prove readers see the old committed data while a
  rebuild is in progress, and that ROLLBACK restores all four tables.
- A disposable CiviCRM-like MariaDB schema that exercises the **actual**
  historical membership, summary, officer and chart rebuild functions,
  including an injected SQL failure part way through the process.

The disposable fixture is NOT your real CiviCRM database. Always perform
one staging-site run against the real custom-field and reporting-table schema.

## Important boundary

Phase 3A is GitHub development; merging a pull request alone does not deploy
the plugin to the web server. Direct URL access to original CiviCRM uploaded
photos and any previously generated public PDFs are **host-side Phase 3B**
checks and are not solved by this change.
