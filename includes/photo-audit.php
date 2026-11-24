<?php
/**
 * Administrator-only diagnostic of missing FaceWall photographs.
 *
 * Read-only: member names and image references are shown in wp-admin only.
 * This module never persists or logs personal details.
 */
defined('ABSPATH') || exit;

/** Read CiviCRM's configured custom-file directory if its API is available. */
function facewall_photo_audit_civicrm_directory() {
    try {
        if (function_exists('civicrm_initialize')) {
            civicrm_initialize();
        }
        if (class_exists('Civi') && method_exists('Civi', 'settings')) {
            $configured = Civi::settings()->get('customFileUploadDir');
            if (is_string($configured) && $configured !== '') {
                if (class_exists('Civi') && method_exists('Civi', 'paths')) {
                    $configured = Civi::paths()->getPath($configured);
                }
                if (!is_string($configured) || $configured === '') {
                    return '';
                }
                $real = realpath($configured);
                if ($real && is_dir($real)) {
                    return $real;
                }
            }
        }
    } catch (Throwable $ignored) {
        // This diagnostic cannot depend on an initialized CiviCRM instance.
    }
    return '';
}

/** Safely check both cache files and the original for one CiviCRM reference. */
function facewall_photo_audit_candidate($reference, $base, $configured) {
    $filename = facewall_image_filename($reference);
    if ($filename === '') {
        return null;
    }
    $small = is_file($base . '/tn/174/tn_' . $filename);
    $full = is_file($base . '/tn/full_scale/tn_' . $filename);
    $safe_base = realpath($base);
    $original = $safe_base ? realpath($safe_base . '/' . $filename) : false;
    $has_original = $original && strpos($original, $safe_base . DIRECTORY_SEPARATOR) === 0
        && is_file($original);
    // The application workflow writes optimized portraits in a dedicated,
    // trusted uploads folder rather than CiviCRM's custom directory.
    $approved_original = facewall_photo_original_path($filename);
    $application_original = !$has_original && $approved_original !== '';
    $alternate = false;
    if (!$has_original && $configured !== '' && $configured !== $safe_base) {
        $other = realpath($configured . '/' . $filename);
        $alternate = $other && strpos($other, $configured . DIRECTORY_SEPARATOR) === 0
            && is_file($other);
    }
    return [
        'filename' => $filename,
        'small' => (bool) $small,
        'full' => (bool) $full,
        'original' => (bool) ($has_original || $application_original),
        'application_original' => (bool) $application_original,
        'alternate' => (bool) $alternate,
        'rank' => ($small && $full ? 8 : 0)
            + ($full ? 4 : 0) + ($small ? 2 : 0)
            + (($has_original || $application_original) ? 1 : 0),
    ];
}

/**
 * Audit the exact member eligibility used by the PDF, but include the
 * historical photo reference from the SAME contact and membership number.
 */
function facewall_poster_photo_audit($db) {
    if (!current_user_can('manage_options') || !defined('WP_CONTENT_DIR')) {
        return ['error' => 'Poster image audit is available to administrators only'];
    }
    $sql = "SELECT DISTINCT c.id, c.display_name, c.image_URL,
                hist.image_URL AS historical_image_URL
        FROM civicrm_contact AS c
        INNER JOIN custom_value_1_BEC AS consent
            ON consent.entity_id = c.id AND consent.facewall_public_84 = 1
        INNER JOIN civicrm_membership AS mem
            ON mem.contact_id = c.id AND mem.membership_type_id < 18
        INNER JOIN civicrm_membership_type AS mtype
            ON mtype.id = mem.membership_type_id
        LEFT JOIN civicrm_BEC_historical_summary AS hist
            ON hist.contact_id = c.id AND hist.id = consent.Membership_Number
        WHERE mtype.id < 9 AND mem.status_id < 4 AND c.is_deceased != 1
        ORDER BY c.display_name";
    $rows = $db->get_results($sql);
    if (!is_array($rows)) {
        return ['error' => 'Could not read consenting members for the poster audit'];
    }
    $base = WP_CONTENT_DIR . '/uploads/civicrm/custom';
    $configured = facewall_photo_audit_civicrm_directory();
    $counts = [
        'members' => 0,
        'complete' => 0,
        'full_only' => 0,
        'small_only' => 0,
        'needs_repair' => 0,
        'application_source' => 0,
        'alternate_source' => 0,
        'no_source' => 0,
        'no_reference' => 0,
        'history_selected' => 0,
    ];
    $issues = [];
    foreach ($rows as $row) {
        $counts['members']++;
        $current = facewall_photo_audit_candidate($row->image_URL ?? '', $base, $configured);
        $historical = facewall_photo_audit_candidate($row->historical_image_URL ?? '', $base, $configured);
        // Mirror the PDF's actual order: current full/small cache first,
        // historical full/small cache second. When neither has a cache,
        // favour the reference whose original is still recoverable.
        $current_cached = $current && ($current['small'] || $current['full']);
        $historical_cached = $historical && ($historical['small'] || $historical['full']);
        $current_original = $current && ($current['original'] || $current['alternate']);
        $historical_original = $historical && ($historical['original'] || $historical['alternate']);
        $prefer_historical = !$current_cached && (
            $historical_cached || (!$current_original && $historical_original)
            || (!$current && $historical)
        );
        $selected = $prefer_historical ? $historical : $current;
        $origin = $prefer_historical ? 'Historical FaceWall' : 'Current CiviCRM';
        if ($prefer_historical) {
            $counts['history_selected']++;
        }

        if ($selected && !empty($selected['application_original'])) {
            $counts['application_source']++;
        }
        if (!$selected) {
            $counts['no_reference']++;
            $status = 'Neither CiviCRM nor FaceWall history has a usable photo filename';
        } elseif ($selected['small'] && $selected['full']) {
            $counts['complete']++;
            $status = 'Both cached images available';
        } elseif ($selected['full']) {
            $counts['full_only']++;
            $status = 'Poster photo available; web-grid thumbnail missing';
        } elseif ($selected['small']) {
            $counts['small_only']++;
            $status = 'Web thumbnail available; poster uses the smaller photo';
        } elseif ($selected['original']) {
            $counts['needs_repair']++;
            $status = !empty($selected['application_original'])
                ? 'BEC application original exists; run Photo Repair to generate both thumbnails'
                : 'Original exists; both thumbnails missing (run Photo Repair)';
        } elseif ($selected['alternate']) {
            $counts['alternate_source']++;
            $status = 'Original found in CiviCRM configured directory, not FaceWall expected directory';
        } else {
            $counts['no_source']++;
            $status = 'No cached photo and no local original located';
        }
        // Show historical-reference recoveries too: these reveal exactly
        // why the poster and website could previously disagree.
        if ((($status !== 'Both cached images available') || $origin === 'Historical FaceWall')
            && count($issues) < 120) {
            $issues[] = [
                'contact_id' => (int) ($row->id ?? 0),
                'member' => (string) ($row->display_name ?? ''),
                'status' => $status,
                'selected' => $origin,
                'current' => $current['filename'] ?? 'None',
                'historical' => $historical['filename'] ?? 'None',
            ];
        }
    }
    return [
        'counts' => $counts,
        'issues' => $issues,
        'directories' => [
            'facewall_expected' => $base,
            'facewall_exists' => is_dir($base),
            'civicrm_configured' => $configured,
            'same' => $configured !== '' && realpath($base) === $configured,
        ],
    ];
}

function facewall_poster_photo_audit_html($audit) {
    if (!current_user_can('manage_options')) {
        return '';
    }
    if (!empty($audit['error'])) {
        return '<p>' . esc_html($audit['error']) . '</p>';
    }
    $labels = [
        'members' => 'Eligible consenting contacts checked',
        'complete' => 'Both cached thumbnail sizes found',
        'full_only' => 'Only poster-size thumbnail found',
        'small_only' => 'Only small web thumbnail found',
        'needs_repair' => 'Original available in a trusted folder; cache missing',
        'application_source' => 'Original recovered from BEC Membership Applications uploads (may overlap other counts)',
        'alternate_source' => 'Original found in a different configured CiviCRM folder',
        'no_source' => 'No thumbnail or original found',
        'no_reference' => 'No valid photograph reference in either source',
        'history_selected' => 'Best available reference came from historical FaceWall data',
    ];
    $html = '<h2>FaceWall photograph investigation</h2>'
        . '<p>Live, read-only audit using current CiviCRM and matching historical '
        . 'FaceWall photo references. Only administrators can see the member names. '
        . 'Results are not stored. The poster may contain duplicate membership '
        . 'rows; this report counts distinct contacts.</p>'
        . '<table class="widefat striped"><tbody>';
    foreach ($labels as $key => $label) {
        $html .= '<tr><th scope="row">' . esc_html($label) . '</th><td>'
            . esc_html((string) ($audit['counts'][$key] ?? 0)) . '</td></tr>';
    }
    $html .= '</tbody></table>';
    $dirs = $audit['directories'] ?? [];
    if ($dirs) {
        $html .= '<h3>Server file locations (admin only)</h3><p>FaceWall expects: <code>'
            . esc_html($dirs['facewall_expected']) . '</code></p>'
            . '<p>Expected directory exists: ' . (!empty($dirs['facewall_exists']) ? 'Yes' : 'No') . '</p>'
            . '<p>CiviCRM configured Custom Files directory: <code>'
            . esc_html($dirs['civicrm_configured'] ?: 'Not available') . '</code></p>';
        if (!empty($dirs['civicrm_configured']) && empty($dirs['same'])) {
            $html .= '<p><strong>Check the directory mismatch.</strong> FaceWall currently '
                . 'reads its thumbnails from the expected WordPress path, but '
                . 'CiviCRM reports a different custom-file directory.</p>';
        }
    }
    if (!empty($audit['issues'])) {
        $html .= '<h3>Members needing attention or using historical references (first 120)</h3>'
            . '<table class="widefat striped"><thead><tr>'
            . '<th>CiviCRM ID</th><th>Member</th><th>Finding</th>'
            . '<th>Selected source</th><th>Current filename</th>'
            . '<th>Historical filename</th></tr></thead><tbody>';
        foreach ($audit['issues'] as $issue) {
            $html .= '<tr>';
            foreach (['contact_id','member','status','selected','current','historical'] as $key) {
                $html .= '<td>' . esc_html((string) ($issue[$key] ?? '')) . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
    }
    return $html;
}
