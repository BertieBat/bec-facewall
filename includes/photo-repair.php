<?php
/**
 * Restore missing FaceWall thumbnails in small WordPress-Cron batches.
 *
 * Never generate files during public shortcode rendering. This maintenance
 * worker reads ONLY contacts whose CiviCRM FaceWall Public custom field is 1,
 * and only local, validated JPEG/PNG files are eligible.
 *
 * No individual names, photo URLs or contact IDs are stored in diagnostics.
 */
defined('ABSPATH') || exit;

require_once __DIR__ . '/photo-audit.php';

function facewall_photo_repair_status() {
    $status = get_option('facewall_photo_repair_status', []);
    return is_array($status) ? $status : [];
}

function facewall_photo_repair_directory_ready() {
    if (!defined('WP_CONTENT_DIR')) {
        return false;
    }
    $base = realpath(WP_CONTENT_DIR . '/uploads/civicrm/custom');
    if (!$base || !is_dir($base)) {
        return false;
    }

    // Paths are fixed, not constructed from uploaded filenames. Only cron
    // creates these directories, never a visitor to a FaceWall page.
    foreach ([$base . '/tn', $base . '/tn/174', $base . '/tn/full_scale'] as $directory) {
        if (!is_dir($directory)) {
            if (!function_exists('wp_mkdir_p') || !wp_mkdir_p($directory)) {
                return false;
            }
        }
        $resolved = realpath($directory);
        if (!$resolved || strpos($resolved, $base . DIRECTORY_SEPARATOR) !== 0) {
            return false;
        }
    }
    return true;
}

function facewall_photo_repair_batch() {
    global $wpdb;

    $lock_name = 'bec_facewall_photo_repair_' .
        (function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 1);
    $locked = false;
    $status = facewall_photo_repair_status();

    try {
        $lock_result = $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', $lock_name));
        if ($lock_result === null || $lock_result === false) {
            throw new RuntimeException('Photo maintenance database lock unavailable');
        }
        if ((int) $lock_result !== 1) {
            // Another worker may still be running; retry this cursor later.
            wp_schedule_single_event(time() + 90, 'facewall_repair_photos');
            return;
        }
        $locked = true;

        if (!facewall_photo_repair_directory_ready()) {
            throw new RuntimeException('Thumbnail directories are unavailable or unsafe');
        }

        $cursor = max(0, (int) ($status['cursor'] ?? 0));
        $limit = 20;
        $query = $wpdb->prepare(
            "SELECT c.id, c.image_URL, hist.image_URL AS historical_image_URL
             FROM civicrm_contact AS c
             INNER JOIN custom_value_1_BEC AS consent
               ON consent.entity_id = c.id AND consent.facewall_public_84 = 1
             LEFT JOIN civicrm_BEC_historical_summary AS hist
               ON hist.contact_id = c.id AND hist.id = consent.Membership_Number
             WHERE c.id > %d AND (
               (c.image_URL IS NOT NULL AND c.image_URL <> '') OR
               (hist.image_URL IS NOT NULL AND hist.image_URL <> '')
             )
             ORDER BY c.id ASC LIMIT %d",
            $cursor, $limit
        );
        $rows = $wpdb->get_results($query);
        if (!is_array($rows)) {
            throw new RuntimeException('Consenting photo query failed');
        }

        $status = array_merge([
            'checked' => 0,
            'existing' => 0,
            'created' => 0,
            'invalid' => 0,
            'failed' => 0,
            'cursor' => 0,
        ], $status);

        foreach ($rows as $row) {
            // Re-check consent at every batch through the SQL join.
            $status['cursor'] = max($status['cursor'], (int) $row->id);
            $status['checked']++;
            // Some older members have a usable historical photograph even
            // though civicrm_contact.image_URL is now empty or points to a
            // missing original. Try both references for the same contact.
            // This never changes a public-consent setting or overwrites
            // existing cached files.
            $references = [$row->image_URL, $row->historical_image_URL ?? ''];
            $seen = [];
            $had_valid_name = false;
            $existing = false;
            $created = false;
            foreach ($references as $reference) {
                $name = facewall_image_filename($reference);
                if ($name === '' || isset($seen[$name])) {
                    continue;
                }
                $had_valid_name = true;
                $seen[$name] = true;
                $cache_dir = WP_CONTENT_DIR . '/uploads/civicrm/custom/tn/';
                $thumbnail = $cache_dir . '174/tn_' . $name;
                $poster_image = $cache_dir . 'full_scale/tn_' . $name;
                if (is_file($thumbnail) && is_file($poster_image)) {
                    $existing = true;
                    break;
                }
                if (facewall_create_thumbnail('', '', '', 174, $reference) &&
                    is_file($thumbnail) && is_file($poster_image)) {
                    $created = true;
                    break;
                }
            }
            if ($created) {
                $status['created']++;
            } elseif ($existing) {
                $status['existing']++;
            } elseif ($had_valid_name) {
                $status['failed']++;
            } else {
                $status['invalid']++;
            }
        }

        $more = count($rows) === $limit;
        $status['state'] = $more ? 'running' : 'completed';
        $status['last_update'] = gmdate('c');
        unset($status['error']);
        update_option('facewall_photo_repair_status', $status, false);
        if ($more && !wp_schedule_single_event(time() + 30, 'facewall_repair_photos')) {
            $status['state'] = 'failed';
            $status['error'] = 'Could not schedule the next thumbnail batch';
            update_option('facewall_photo_repair_status', $status, false);
        }
    } catch (Throwable $error) {
        $status['state'] = 'failed';
        $status['last_update'] = gmdate('c');
        // Only fixed, non-personal errors may be shown in wp-admin.
        $status['error'] = 'Photo maintenance could not access its files or database';
        update_option('facewall_photo_repair_status', $status, false);
        error_log('FaceWall photo repair: batch could not be completed');
    } finally {
        if ($locked) {
            try {
                $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));
            } catch (Throwable $ignored) {
                error_log('FaceWall photo repair: advisory lock release unavailable');
            }
        }
    }
}
add_action('facewall_repair_photos', 'facewall_photo_repair_batch');

function facewall_photo_repair_menu() {
    add_submenu_page(
        'facewall_plugin',
        'Photo Repair',
        'Photo Repair',
        'manage_options',
        'facewall_photo_repair',
        'facewall_photo_repair_page'
    );
}
add_action('admin_menu', 'facewall_photo_repair_menu');

function facewall_photo_repair_page() {
    if (!current_user_can('manage_options')) {
        wp_die('You do not have permission to repair FaceWall photos.');
    }

    $status = facewall_photo_repair_status();
    $fields = [
        'state' => 'Status',
        'last_update' => 'Last updated (UTC)',
        'checked' => 'Consenting contacts checked',
        'existing' => 'Already cached',
        'created' => 'New thumbnails generated',
        'invalid' => 'Unrecognised photo filenames',
        'failed' => 'Missing or unprocessable sources',
    ];
    echo '<div class="wrap"><h1>FaceWall Photo Repair</h1>';
    echo '<p>Restore missing photo thumbnails for members with FaceWall Public consent. '
        . 'Original photographs stay in CiviCRM, and visitors cannot trigger this process.</p>';
    echo '<table class="widefat striped"><tbody>';
    foreach ($fields as $name => $caption) {
        echo '<tr><th scope="row">' . esc_html($caption) . '</th><td>'
            . esc_html((string) ($status[$name] ?? 'Not recorded')) . '</td></tr>';
    }
    echo '</tbody></table>';
    if (!empty($status['error'])) {
        echo '<div class="notice notice-error"><p>' .
            esc_html((string) $status['error']) . '</p></div>';
    }
    $scheduled = wp_next_scheduled('facewall_repair_photos');
    echo '<p>Next repair batch: '
        . esc_html($scheduled ? gmdate('Y-m-d H:i:s', $scheduled) . ' UTC' : 'Not scheduled')
        . '</p>';
    echo '<p><strong>Note:</strong> This is a batched maintenance job. It runs through WordPress '
        . 'scheduling and may need website traffic or a server cron runner to process all batches.</p>';
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    echo '<input type="hidden" name="action" value="facewall_photo_repair_queue">';
    wp_nonce_field('facewall_photo_repair_queue');
    submit_button('Repair missing thumbnails', 'primary');
    echo '</form>';
    // Investigate individual missing poster photos without altering data.
    // The audit is restricted to administrators and is read-only.
    echo facewall_poster_photo_audit_html(facewall_poster_photo_audit($GLOBALS['wpdb']));
    echo '</div>';
}

function facewall_photo_repair_queue_action() {
    if (!current_user_can('manage_options')) {
        wp_die('You do not have permission to repair FaceWall photos.');
    }
    check_admin_referer('facewall_photo_repair_queue');

    $existing_status = facewall_photo_repair_status();
    $updated = !empty($existing_status['last_update'])
        ? strtotime((string) $existing_status['last_update']) : false;
    $busy = ($existing_status['state'] ?? '') === 'running' &&
        $updated && $updated > time() - 15 * 60;

    if (!wp_next_scheduled('facewall_repair_photos') && !$busy) {
        $initial = [
            'state' => 'queued',
            'last_update' => gmdate('c'),
            'cursor' => 0,
            'checked' => 0,
            'existing' => 0,
            'created' => 0,
            'invalid' => 0,
            'failed' => 0,
        ];
        if (wp_schedule_single_event(time() + 10, 'facewall_repair_photos')) {
            update_option('facewall_photo_repair_status', $initial, false);
        } else {
            $initial['state'] = 'failed';
            $initial['error'] = 'WordPress could not schedule the photo repair task';
            update_option('facewall_photo_repair_status', $initial, false);
        }
    }

    wp_safe_redirect(admin_url('admin.php?page=facewall_photo_repair'));
    exit;
}
add_action('admin_post_facewall_photo_repair_queue', 'facewall_photo_repair_queue_action');
