# BEC FaceWall directory migration

The plugin entry point is now `bec-facewall/bec-facewall.php`. The existing `[facewall]` shortcode, database tables, options, admin slugs, and `facewall_cron` hook are unchanged.

## Deployment

Do not pull the rename while the old plugin is active. Take a database and plugin-directory backup, confirm the Git checkout is clean, and use the WordPress Plugins screen to deactivate FaceWall first. Do not delete or uninstall it. From the WordPress plugins directory:

```sh
cd /home/bertie/public_html/wp/wp-content/plugins
# Verify the destination does not exist, and stop if it does.
test ! -e bec-facewall && mv facewall bec-facewall
cd bec-facewall
git status --short
git pull --ff-only origin main
```

Only run the pull after the rename changes have been merged into main. If the repository itself has been renamed on GitHub, update origin to the equivalent new URL, preserving the current SSH/HTTPS transport. Activate BEC FaceWall in WordPress. Clear page/CDN caches. Check Diagnostics, Shortcodes, Photo Repair, public photo grids, placeholder photos, hover overlays, and the existing `facewall_cron` event. Check any external deployment/cron paths referencing the old directory. The retired direct PHP cron stub must not be used to run the job.

## Rollback

Deactivate BEC FaceWall, restore the previous plugin directory from the backup (including its original `facewall.php`), ensure only one copy is present within the plugins directory, and reactivate FaceWall. Restore any changed deployment paths. Do not delete the reporting tables or create a second scheduled event.
