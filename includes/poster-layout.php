<?php
/**
 * Deterministic A1-family poster grid measurements, in TCPDF millimetres.
 * No database, TCPDF or WordPress side effects; test separately from PDF I/O.
 */
defined('ABSPATH') || exit;

function facewall_poster_grid_layout($members, $page_width, $usable_height, $margin_left, $margin_right) {
    $members = (int) $members;
    $page_width = (float) $page_width;
    $usable_height = (float) $usable_height;
    $margin_left = (float) $margin_left;
    $margin_right = (float) $margin_right;

    if ($members < 1) {
        throw new InvalidArgumentException('Cannot lay out an empty member poster');
    }
    $rows = min(10, $members);
    $columns = (int) ceil($members / $rows);
    $available_width = $page_width - $margin_left - $margin_right;
    $min_spacing = 5.0;
    $name_clearance = 3.0; // member names are printed immediately below each photo
    $aspect_ratio = 204.0 / 175.0;
    if ($available_width <= 0 || $usable_height <= 0) {
        throw new RuntimeException('Poster page has no usable area');
    }

    // Respect both horizontal and vertical bounds. The previous calculation
    // made portraits wider than a whole row of the poster for small member
    // sets, and divided by zero when there was only one column or row.
    $max_width_by_columns = ($available_width - ($columns - 1) * $min_spacing) / $columns;
    $max_height_per_image =
        ($usable_height - ($rows - 1) * $min_spacing - $rows * $name_clearance) / $rows;
    $max_width_by_height = $max_height_per_image / $aspect_ratio;

    // One member should not become a half-metre-wide portrait.
    $max_single_column_width = $columns === 1 ? 60.0 : INF;
    $image_width = min($max_width_by_columns, $max_width_by_height, $max_single_column_width);
    if ($image_width <= 0) {
        throw new RuntimeException('Poster page is too small for member photographs');
    }

    $image_height = $image_width * $aspect_ratio;
    $spacer_x = $columns > 1
        ? ($available_width - $image_width * $columns) / ($columns - 1)
        : 0.0;
    $spacer_y = $rows > 1
        ? ($usable_height - $image_height * $rows) / ($rows - 1)
        : 0.0;

    // Centre a single column instead of placing it against the left edge.
    $start_x = $columns === 1
        ? $margin_left + ($available_width - $image_width) / 2
        : $margin_left;

    return [
        'rows' => $rows,
        'columns' => $columns,
        'image_width' => $image_width,
        'image_height' => $image_height,
        'spacer_x' => $spacer_x,
        'spacer_y' => $spacer_y,
        'start_x' => $start_x,
    ];
}
