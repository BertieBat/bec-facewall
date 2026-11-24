<?php
/**
 * Administrator-only, read-only audit of photo coverage for the member
 * population eligible for the PDF poster. No member/photo data is saved.
 */
defined('ABSPATH') || exit;

function facewall_poster_photo_audit($db) {
    if (!current_user_can('manage_options') || !defined('WP_CONTENT_DIR')) {
        return ['error' => 'Poster image audit is available to administrators only'];
    }

    // Equivalent eligibility to poster.php; DISTINCT counts contacts rather
    // than repeated membership rows. This is a diagnostic, not a PDF source.
    $sql = "SELECT DISTINCT c.id, c.display_name, c.image_URL
        FROM civicrm_contact AS c
        INNER JOIN custom_value_1_BEC AS consent
            ON consent.entity_id = c.id AND consent.facewall_public_84 = 1
        INNER JOIN civicrm_membership AS mem
            ON mem.contact_id = c.id AND mem.membership_type_id < 18
        INNER JOIN civicrm_membership_type AS mtype
            ON mtype.id = mem.membership_type_id
        WHERE mtype.id < 9 AND mem.status_id < 4 AND c.is_deceased != 1
        ORDER BY c.display_name";
    $rows = $db->get_results($sql);
    if (!is_array($rows)) {
        return ['error' => 'Could not read consenting members for the poster audit'];
    }

    $counts = [
        'members' => 0,
        'complete' => 0,
        'full_only' => 0,
        'small_only' => 0,
        'needs_repair' => 0,
        'no_source' => 0,
        'no_reference' => 0,
    ];
    $issues = [];
    $base = WP_CONTENT_DIR . '/uploads/civicrm/custom';
    $safe_base = realpath($base);
    foreach ($rows as $row) {
        $counts['members']++;
        $name = facewall_image_filename($row->image_URL ?? null);
        if ($name === '') {
            $status = 'No usable CiviCRM photo reference';
            $counts['no_reference']++;
        } else {
            $small = $base . '/tn/174/tn_' . $name;
            $full = $base . '/tn/full_scale/tn_' . $name;
            $has_small = is_file($small);
            $has_full = is_file($full);
            if ($has_small && $has_full) {
                $counts['complete']++;
                continue;
            }
            if ($has_full) {
                $status = 'Poster photo available; website thumbnail missing';
                $counts['full_only']++;
            } elseif ($has_small) {
                $status = 'Smaller photo available; poster-size copy missing';
                $counts['small_only']++;
            } else {
                // The generator requires the original file to exist inside
                // the expected CiviCRM custom upload directory.
                $source = $safe_base ? realpath($safe_base . '/' . $name) : false;
                $source_valid = $source && strpos($source, $safe_base . DIRECTORY_SEPARATOR) === 0
                    && is_file($source);
                if ($source_valid) {
                    $status = 'Both thumbnails missing; Photo Repair can try the original';
                    $counts['needs_repair']++;
                } else {
                    $status = 'No cached photo or original at the expected path';
                    $counts['no_source']++;
                }
            }
        }
        // Never write member names, IDs or image paths to options/logs.
        // Show only the first 60 issues to authorized admins.
        if (count($issues) < 60) {
            $issues[] = [
                'contact_id' => (int) ($row->id ?? 0),
                'member' => (string) ($row->display_name ?? ''),
                'status' => $status,
            ];
        }
    }
    return ['counts' => $counts, 'issues' => $issues];
}

/** Render aggregate coverage and the first 60 photo issues in wp-admin. */
function facewall_poster_photo_audit_html($audit) {
    if (!current_user_can('manage_options')) {
        return '';
    }
    if (!empty($audit['error'])) {
        return '<p>' . esc_html($audit['error']) . '</p>';
    }
    $counts = $audit['counts'];
    $labels = [
        'members' => 'Distinct consenting members eligible for the poster',
        'complete' => 'Both sizes cached',
        'full_only' => 'Poster-size photo available, small image missing',
        'small_only' => 'Only small photo available (poster now uses it)',
        'needs_repair' => 'Both cached sizes missing, original exists',
        'no_source' => 'No cached size and original not found locally',
        'no_reference' => 'No usable photo reference in CiviCRM',
    ];
    $html = '<h2>Current poster photograph audit</h2>'
        . '<p>This is a live, read-only file check. It counts distinct consenting contacts, '
        . 'so totals may differ from poster membership rows if someone has multiple memberships.</p>'
        . '<table class="widefat striped"><tbody>';
    foreach ($labels as $key => $label) {
        $html .= '<tr><th scope="row">' . esc_html($label) . '</th><td>'
            . esc_html((string) ($counts[$key] ?? 0)) . '</td></tr>';
    }
    $html .= '</tbody></table>';
    if (!empty($audit['issues'])) {
        $html .= '<h3>Photographs needing attention (first 60)</h3>'
            . '<table class="widefat striped"><thead><tr><th>Contact ID</th>'
            . '<th>Member</th><th>Issue</th></tr></thead><tbody>';
        foreach ($audit['issues'] as $issue) {
            $html .= '<tr><td>' . esc_html((string) $issue['contact_id']) . '</td><td>'
                . esc_html($issue['member']) . '</td><td>'
                . esc_html($issue['status']) . '</td></tr>';
        }
        $html .= '</tbody></table>';
    }
    return $html;
}
