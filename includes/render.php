<?php
/**
 * Small, side-effect-free FaceWall view components.
 *
 * HTML classes, attributes, item schema, and the historical heading layout
 * are retained exactly for compatibility with the site's existing theme.
 * Database queries, privacy decisions, membership calculations and image
 * selection MUST stay in the calling view functions.
 *
 * Heading, trailing heading and overlay arguments contain deliberate HTML.
 * Callers escape dynamic member values with facewall_h() or use the existing
 * facewall_summary_html() before passing them here.
 */
defined('ABSPATH') || exit;

/** A photo row, with the legacy classes and row number unchanged. */
function facewall_render_grid_row_open($row_number, $kind = 'members') {
    $row_class = $kind === 'year' ? 'bec_year_row' : 'bec_members_row';
    return '<div class="items-row cols-4 row-' . (int) $row_number
        . ' row-fluid clearfix ' . $row_class . '">';
}

/**
 * One consistent photo-card renderer for current, historical, probationary,
 * under-30, joint, and officer displays.
 *
 * $heading_html: content inside h5
 * $heading_tail_html: optional material after h5 inside .page-header
 * $overlay_html: null for no overlay; otherwise content inside .text
 * $wrap_anchor and $focusable retain the legacy no-overlay variants.
 */
function facewall_render_member_card(
    $column,
    $heading_html,
    $image_html,
    $overlay_html = null,
    $heading_tail_html = '',
    $wrap_anchor = false,
    $focusable = true
) {
    $html = '<div class="span2">';
    $html .= '<div class="item column-' . (int) $column
        . '" itemprop="blogPost" itemscope="" itemtype="http://schema.org/BlogPosting">';
    $html .= '<div class="page-header">';
    $html .= '<h5 itemprop="name" max-width=250px>' . $heading_html . '</h5>';
    $html .= $heading_tail_html;
    $html .= '</div>';
    $html .= $focusable
        ? '<div class="pull-left item-image" tabindex="0" role="group" aria-label="Hover, focus or tap to show membership information">'
        : '<div class="pull-left item-image">';
    if ($overlay_html !== null) {
        $html .= '<div class="fw_middle"><div class="text">'
            . $overlay_html . '</div></div>';
    }
    if ($wrap_anchor) {
        $html .= '<a>';
    }
    $html .= $image_html;
    if ($wrap_anchor) {
        $html .= '</a>';
    }
    $html .= '</div></div></div>';
    return $html;
}
