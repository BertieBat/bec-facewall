# FaceWall — BEC membership and officers

FaceWall is the BEC WordPress plugin that reads CiviCRM contact and membership
records and displays current members, historical memberships, joint members,
students/under-30s, probationary members, and committee officers.

Release: **1.1.12** (repair approved BEC application photo originals into FaceWall caches).

## Administrator screens

- **FaceWall** — legacy read-only current-member preview.
- **FaceWall → Shortcodes** — supported scopes, examples and notes.
- **FaceWall → Diagnostics** — reporting rebuild status, scheduling and
  controlled manual queue; optional failure-alert emails.
- **FaceWall → Photo Repair** — queue missing thumbnails in small batches,
  for contacts explicitly consenting to public FaceWall display. Its live,
  read-only poster audit reports how many eligible members have full-size or
  small cached photos, missing caches with originals available, or missing
  original CiviCRM files/references.
- **A1 / RA1 / SRA1 Poster** — administrator-only nonce-protected PDFs.

Public shortcode rendering never rebuilds membership data or creates files.

## Public shortcode reference

Use a WordPress Shortcode block:

| Shortcode | Function |
| --- | --- |
| [facewall] | Current members |
| [facewall scope="current"] | Current members |
| [facewall scope="probationary"] | Probationary members |
| [facewall scope="grace"] | Grace-status members |
| [facewall scope="lybnty"] | Last Year But Not This Year |
| [facewall scope="joint"] | Regular and unusual joint-member pairs |
| [facewall scope="life"] | Life membership types |
| [facewall scope="student"] | Student-discount and under-30 sections |
| [facewall scope="officer"] | Historical officer text list |
| [facewall scope="officer_grid_year"] | All officer photo years |
| [facewall scope="officer_grid_year" year="current"] | Current committee photos |
| [facewall scope="historical_membership_list"] | Historical member text list |
| [facewall scope="historical_membership_grid"] | Historical member photographs |
| [facewall scope="prorata_fees"] | Prorated CiviCRM fees — verify before publishing |

The retired membership_fees scope is not a working FaceWall shortcode;
the separate WPCode implementation is outside this repository.

The catalog in includes/shortcodes.php is the **single source of truth**
for both runtime dispatch and administrator documentation. All handlers and
eligibility filters are plugin-defined. User input cannot select arbitrary
PHP functions or SQL filters.

## File map

| File | Responsibility |
| --- | --- |
| facewall.php | Plugin bootstrap, admin menu and poster authorization |
| includes/shortcodes.php | Registry, dispatch, scoped CSS fallback and help screen |
| includes/member-query.php | Shared read-only CiviCRM membership queries |
| includes/render.php | Shared member photo cards and grid row HTML |
| includes/security.php | Public consent, output escaping, safe local images |
| current_list.php | Legacy current and joint-member views, photo generator |
| officer_grid_year.php | Photo grids, officer views and reporting source builders |
| membership_list.php | Historical membership and chart data |
| includes/history.php | Historical year calculation and safe grouping |
| includes/calendar.php | BEC AGM date and committee handover |
| includes/maintenance.php | InnoDB-only locked/transactional reporting rebuild |
| includes/diagnostics.php | Rebuild administration, status and alerts |
| includes/photo-repair.php | Batched thumbnail restoration |
| poster.php and includes/pdf.php | Safe PDF download and TCPDF loading |
| includes/poster-layout.php | PDF poster layout calculations |
| membership_fees.php | Working prorata fee shortcode |
| css/facewall.css | FaceWall-only responsive layout and overlays |
| css/facewall-menu-contrast.css | Shape5/Vertex dropdown text contrast |

## Privacy and images

The FaceWall Public custom field (facewall_public_84) must equal 1.
Joint members both need affirmative consent. Only validated local JPEG/PNG
thumbnails are displayed. Small photographs are in
wp-content/uploads/civicrm/custom/tn/174/ and larger poster copies
are in tn/full_scale/. **PDF posters prefer the full-size copy but fall back
read-only to the smaller cached photo rather than the lamp when the poster
copy is missing.** A historical photo reference takes priority
where provided; some views fall back to the current CiviCRM photo.

CiviCRM can store photo URLs as HTML, with `&amp;photo=` rather than
`&photo=`. FaceWall decodes HTML entities in the query portion *before*
extracting the image filename. It still accepts only local JPG/JPEG/PNG
basenames after strict validation and never downloads the URL.

BEC Membership Applications optimizes approved applicants' JPEG photographs into
`wp-content/uploads/bec-membership-photos/`. Only files matching that
workflow's precise `application-<id>-<24-character-token>.jpg` filename pattern
may be used as additional local originals. FaceWall Photo Repair validates
the resolved file and creates the same two cache sizes in the existing CiviCRM
thumbnail directories; it does not move, modify or expose the source upload.
The administrator audit now identifies missing caches whose application
original is available.

Public pages never create thumbnails or fetch arbitrary image URLs.
Use **FaceWall → Photo Repair** to rebuild missing caches.

The hover history has TWO deliberately retained style safeguards:
the external scoped FaceWall CSS and a minimal inline shortcode fallback,
needed when the site's theme or CDN skips an external stylesheet.
Do not remove either without verifying the live website. Shape5 dropdown
correction CSS is also separate to avoid global theme side effects.

## Database rebuild

The registered WordPress event facewall_cron rebuilds four tables together:
civicrm_BEC_historical_membership,
civicrm_BEC_historical_officer_membership,
civicrm_BEC_historical_summary and
civicrm_BEC_current_year.

All four must be InnoDB. The runner obtains an advisory lock and only commits
the report when every stage succeeds; failures roll back all stages.
Duplicate BEC membership numbers belonging to different contacts abort the
rebuild rather than overwrite a person's history.
Queue one rebuild from **FaceWall → Diagnostics**.

## Files intentionally retained for compatibility

- css/bootstrap-responsive.css and css/custom.css are NOT enqueued by FaceWall.
  They may still be used by external theme references, so do not delete them
  without checking the live server/template.
- The two identical smallbat.png files remain for possible external image URLs.
- facewall_cron.php is a retired, non-executable stub kept in case an old
  server cron still refers to its path. The supported hook is facewall_cron.

Release D removed only unregistered admin callbacks, a no-longer-used
pre-CSS-Grid padding function and unused assignments in read-only views.
Public display SQL, privacy and photo priority remain unchanged.

## Testing and deployment

GitHub Actions tests PHP 8.2/8.4, 41 identical Release B rendering snapshots,
membership safety, calendars, shortcode routing and a disposable MariaDB
transaction/rollback fixture.

**GitHub CI cannot validate production membership records or the live
Shape5/Vertex menu, hover overlay, mobile view and poster appearance.**
Follow the release deployment guide on staging first.

After a plugin/database backup and staging check:

    cd /home/bertie/public_html/wp/wp-content/plugins/facewall
    git pull --ff-only origin main

Clear WordPress/CDN/browser caches and check public and admin displays.
A GitHub merge does not automatically deploy this repository to WordPress.

Prior stage notes: RELEASE_B_DEPLOYMENT.md and RELEASE_C_DEPLOYMENT.md.
Membership-type policies are intentionally unchanged: public current
members use type <8 while the legacy admin preview and poster use <9.
