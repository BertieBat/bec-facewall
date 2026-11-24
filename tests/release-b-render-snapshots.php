<?php
/**
 * Release B: snapshot the exact HTML emitted by all eight FaceWall photo
 * views, before extracting their shared card markup. The fixture uses fake
 * CiviCRM rows but exercises the actual public rendering functions.
 *
 * Until snapshots are frozen, print deterministic SHA-256 hashes in CI.
 */
require __DIR__ . '/grid-markup-regression.php';

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
            $hashes[$prefix . $view] = hash('sha256', $html);
        }
    }
    $wpdb->rows = [
        grid_member(1, 2027),
        grid_member(2, 2027),
        grid_member(3, 2026),
        grid_member(4, 2026),
        grid_member(5, 2025),
    ];
    $hashes['officer-year-boundaries'] = hash('sha256', display_officer_grid_year('all'));
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
