<?php
/**
 * Release B: snapshot the exact HTML emitted by all eight FaceWall photo
 * views, before extracting their shared card markup. The fixture uses fake
 * CiviCRM rows but exercises the actual public rendering functions.
 *
 * Until snapshots are frozen, print deterministic SHA-256 hashes in CI.
 */
// Freeze the BEC AGM year in fixtures so the snapshots remain valid
// after a real calendar-year/AGM rollover.
function wp_date($format) {
    if ($format === 'Y-m-d') { return '2026-11-24'; }
    throw new RuntimeException('Unexpected wp_date format in grid fixture');
}
require __DIR__ . '/grid-markup-regression.php';

// The plugin directory is deliberately variable; preserve the original HTML baseline.
function facewall_snapshot_normalize_plugin_path($html) {
    return str_replace(
        '/wp/wp-content/plugins/' . rawurlencode(basename(dirname(__DIR__))) . '/img/unknown.jpg',
        '/wp/wp-content/plugins/facewall/img/unknown.jpg',
        $html
    );
}

function facewall_release_b_hashes() {
    global $wpdb;
    $hashes = [];
    foreach ([1, 2, 5, 6, 11] as $count) {
        $wpdb->rows = [];
        for ($id = 1; $id <= $count; $id++) {
            $wpdb->rows[] = grid_member($id, 2026);
        }
        $prefix = 'count-' . $count . '-';
        $views = [
            'legacy-current' => display_facewall_normal('Current', '< 4', '< 8'),
            'current' => display_facewall_normal_new('Current', '< 4', '< 8'),
            'under-30' => display_facewall_under_30('Under 30', '< 4', '< 8'),
            'probationary' => display_facewall_probationary('Probationary', '< 4', '= 2'),
            'history' => display_all_membership(),
            'officers' => display_officer_grid_year('all'),
            'joint' => display_facewall_joint('Joint', '< 4', 'IN (3,5,7)'),
            'joint-unusual' => display_facewall_joint_invalid('Joint', '< 4', 'IN (3,5,7)'),
        ];
        foreach ($views as $view => $html) {
            if (!is_string($html) || $html === '') {
                throw new RuntimeException('Snapshot unexpectedly empty for ' . $view);
            }
            if ($view === 'probationary') {
                // This old view calculates the time since join_date with a
                // native new DateTime(). Normalize ONLY that changing number,
                // while preserving all the actual tags and static text.
                $html = preg_replace(
                    '~(<h5 itemprop="name" max-width=250px>)[^<]*(<br>[0-9]{4}, [A-Za-z]+<br>)~',
                    '$1__ELAPSED__$2',
                    $html
                );
            }
            $hashes[$prefix . $view] = hash('sha256', facewall_snapshot_normalize_plugin_path($html));
        }
    }
    $wpdb->rows = [
        grid_member(1, 2027),
        grid_member(2, 2027),
        grid_member(3, 2026),
        grid_member(4, 2026),
        grid_member(5, 2025),
    ];
    $hashes['officer-year-boundaries'] = hash('sha256', facewall_snapshot_normalize_plugin_path(display_officer_grid_year('all')));
    return $hashes;
}

$actual = facewall_release_b_hashes();
$expected_path = __DIR__ . '/release-b-render-hashes.json';
if (is_file($expected_path)) {
    $expected = json_decode(file_get_contents($expected_path), true);
    if (!is_array($expected)) {
        throw new RuntimeException('Release B snapshot file is malformed');
    }
    foreach ($expected as $name => $hash) {
        if (($actual[$name] ?? null) !== $hash) {
            throw new RuntimeException('Release B changed HTML output for ' . $name);
        }
    }
    if (array_keys($actual) !== array_keys($expected)) {
        throw new RuntimeException('Release B snapshot fixture list has changed');
    }
    echo "FaceWall Release B: all " . count($actual) . " rendering snapshots passed.\n";
} else {
    echo "RELEASE_B_BASELINE " . json_encode($actual, JSON_UNESCAPED_SLASHES) . "\n";
}
