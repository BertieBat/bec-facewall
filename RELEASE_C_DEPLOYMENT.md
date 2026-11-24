# FaceWall Release C (v1.1.7): queries and history processing

Release C is intentionally separate from the Release B rendering refactor.
It contains no database schema changes, no new CiviCRM custom fields, and
no changes to shortcode HTML, photo rules or WordPress navigation.

## Main changes

- Shared, read-only CiviCRM member-source joins in includes/member-query.php
  for legacy, current, under-30 and probationary grids. The public consent,
  deceased-member exclusions, membership type/status boundaries and sort
  order stay as they were. Numeric comparison and IN filters are validated:
  malformed caller-supplied SQL fragments return no members.
- Shared legacy history-year calculation in includes/history.php, used
  by both historical membership and officer table rebuilds. The original
  1960 calendar-year exception and 1 October short-year rule are kept.
- Historical summaries are grouped by BOTH contact ID and membership
  number. Membership periods are still merged with the existing
  membership_details() and consolidateArray() formatters, preserving
  display text, spelling, order and photograph reference for normal data.
- If one membership number belongs to two contacts in an eligible summary
  source, history rebuilding refuses to publish either person's data in
  place of the other. The Phase 3A transaction rolls the entire rebuild back.
- The unusual-joint-members grid still uses CiviCRM API3 for each person,
  but a contact repeated in the same request has its Membership response
  reused. Type and status label caches remain enabled.

## Read-only predeployment identity check

Run this on the actual CiviCRM database, not information_schema:

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

Ideally this returns no rows. If it lists duplicates, review the CiviCRM
membership number assignments before triggering the new reporting rebuild.
No numbers are automatically reassigned or deleted.

## Deployment sequence

1. Back up the FaceWall plugin files and full CiviCRM database.
2. Run the read-only identity check above.
3. Deploy to staging and confirm SQL queries still return the expected
   current/grace/LYBNTY/life/student/probationary counts, and joint members
   remain in the correct normal and unusual-pairs sections.
4. Check the under-30 and 1960/October date boundaries, historical
   membership year counts, officer list and chart totals.
5. Trigger a rebuild on staging through FaceWall → Diagnostics, verify
   all four reporting tables commit, and compare sample summaries to the
   previous release (names, historical years, committee post abbreviations,
   and photos).
6. If Diagnostics reports "membership identifiers", investigate duplicate
   membership numbers. Do not disable the safe failure check to hide
   the problem. Previously committed reporting tables remain available.
7. After successful staging checks, deploy to the live WordPress server
   and clear the site/page cache. A GitHub merge does not itself deploy code.

## Automated tests

- PHP 8.2 and 8.4 syntax and existing regression suites.
- All 41 Release B byte-identical output snapshots.
- Release C safe query filters, historic date/1960/October rules,
  non-adjacent groupings, overlapping periods and duplicate-ID guards.
- Joint API response call-count checks.
- Disposable MariaDB integration for the real CiviCRM-like rebuild,
  original SQL eligibility, failed SQL mid-rebuild and duplicate-number
  rollback of all four published reporting tables.

The disposable fixture cannot prove that the production CiviCRM database
has no duplicate membership numbers or that every historical period is
semantically correct. The staging comparisons remain essential.

## Deliberately out of scope

- No change to BEC membership type policies: the public current grid uses
  type <8, while the old admin view and poster use type <9.
- No alteration of the existing historical 1960 or October 1 conventions.
- No edits to CiviCRM records, member photographs, recurring jobs or
  public visibility.
- No bulk import, schema/index migration or broad redesign of the
  complex joint-relationship eligibility SQL (leave for a separate
  migration with real-data comparison).

Rollback: revert the deployed plugin to version 1.1.6 and clear caches.
No schema rollback is needed; any already committed new report contents
can be regenerated under the earlier version using the existing controlled
maintenance process.
