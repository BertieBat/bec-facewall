<?php
/**
 * Security and privacy helpers for FaceWall's public views.
 *
 * A CiviCRM FaceWall Public value of 1 is affirmative consent. Missing or
 * unrecognised values fail closed. Never trust names, summaries or image URLs
 * merely because they came from the CiviCRM database.
 */
defined('ABSPATH') || exit;

function facewall_is_public($value) {
    return (string) $value === '1';
}

function facewall_h($value) {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Preserve only the line breaks intentionally generated in a membership summary. */
function facewall_summary_html($summary) {
    $summary = (string) ($summary ?? '');
    if (function_exists('wp_kses')) {
        return wp_kses($summary, ['br' => []]);
    }

    // Useful for standalone regression tests; normal WordPress uses wp_kses.
    $summary = preg_replace('~<br\s*/?>~i', "\n", $summary);
    return nl2br(facewall_h(strip_tags($summary)), false);
}

/** Turn a CiviCRM image_URL into one local JPG/PNG basename, or reject it. */
function facewall_image_filename($stored) {
    if (!is_string($stored) || $stored === '' || strlen($stored) > 1024) {
        return '';
    }
    $parts = parse_url($stored);
    if (!is_array($parts)) {
        return '';
    }

    $candidate = $parts['path'] ?? '';
    if (isset($parts['query'])) {
        parse_str($parts['query'], $params);
        if (isset($params['photo']) && is_string($params['photo'])) {
            $candidate = $params['photo'];
        }
    }
    $candidate = rawurldecode($candidate);
    $candidate = str_replace('\\', '/', $candidate);
    // Reject traversal rather than merely removing its components.
    if (strpos($candidate, '..') !== false || strpos($candidate, "\0") !== false) {
        return '';
    }
    $candidate = basename($candidate);
    if (strncmp($candidate, 'tn_', 3) === 0) {
        $candidate = substr($candidate, 3);
    }
    // Common camera/upload filenames may include spaces, plus signs,
    // parentheses or Unicode letters. Never permit separators, traversal,
    // extensions other than local JPEG/PNG, or control characters.
    if (!preg_match('~^[\\p{L}\\p{N}][\\p{L}\\p{N}._ ()+\\-]{0,179}\\.(?:jpe?g|png)$~iuD', $candidate)) {
        return '';
    }
    return $candidate;
}

function facewall_unknown_image_html($name = '') {
    $url = function_exists('plugins_url')
        ? plugins_url('img/unknown.jpg', dirname(__DIR__) . '/facewall.php')
        : '/wp/wp-content/plugins/facewall/img/unknown.jpg';
    return '<img src="' . facewall_h($url)
        . '" class="fw_image" width="174" height="205" loading="lazy" alt="'
        . facewall_h($name !== '' ? 'No picture for ' . $name : 'No picture') . '">';
}

/**
 * Read-only renderer. Public page requests must never generate image files.
 * The nightly cron can generate validated missing thumbnails separately.
 */
function facewall_photo_html($stored, $name = '', $is_public = false) {
    if (!$is_public) {
        return facewall_unknown_image_html($name);
    }

    $filename = facewall_image_filename($stored);
    if ($filename === '' || !defined('WP_CONTENT_DIR')) {
        return facewall_unknown_image_html($name);
    }

    $relative = '/uploads/civicrm/custom/tn/174/tn_' . $filename;
    $path = WP_CONTENT_DIR . $relative;
    if (!is_file($path)) {
        return facewall_unknown_image_html($name);
    }

    // The URL needs encoding, but filesystem checks must use the real name.
    // This preserves safe legacy filenames containing spaces or Unicode.
    $web_relative = '/uploads/civicrm/custom/tn/174/tn_' . rawurlencode($filename);
    $url = function_exists('content_url')
        ? content_url($web_relative)
        : '/wp/wp-content' . $web_relative;
    return '<img src="' . facewall_h($url)
        . '" class="fw_image" width="174" height="205" loading="lazy" alt="'
        . facewall_h($name) . '">';
}

/** Validated local path for a generated PDF thumbnail. No external URLs. */
function facewall_full_size_thumbnail_path($stored, $is_public = false) {
    if (!$is_public || !defined('WP_CONTENT_DIR')) {
        return '';
    }
    $filename = facewall_image_filename($stored);
    if ($filename === '') {
        return '';
    }
    $path = WP_CONTENT_DIR . '/uploads/civicrm/custom/tn/full_scale/tn_' . $filename;
    return is_file($path) ? $path : '';
}
