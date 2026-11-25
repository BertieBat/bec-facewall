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
    // CiviCRM may store the URL as escaped HTML (e.g. &amp;photo= or
    // &#38;photo=). Decode once before parse_url: otherwise the "#" in a
    // numeric entity is misread as a URL fragment delimiter.
    $decoded_url = html_entity_decode($stored, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $parts = parse_url($decoded_url);
    if (!is_array($parts)) {
        return '';
    }

    $candidate = $parts['path'] ?? '';
    if (isset($parts['query'])) {
        // The URL was already HTML-decoded above. Inspect only "photo",
        // then validate its local JPG/PNG basename; never fetch the URL.
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


/**
 * Resolve a validated CiviCRM image basename to an existing local original.
 *
 * The approved BEC application workflow stores its optimized JPEGs in the
 * WordPress uploads/bec-membership-photos directory. Only its exact generated
 * application-<number>-<token>.jpg filenames may use that second source;
 * arbitrary CiviCRM URLs cannot read unrelated files from WordPress uploads.
 * Never fetch remote URLs, follow a symlink outside the approved root or
 * expose the original on a public page.
 */
function facewall_photo_original_path($stored) {
    if (!defined('WP_CONTENT_DIR')) {
        return '';
    }
    $filename = facewall_image_filename($stored);
    if ($filename === '') {
        return '';
    }
    $primary = WP_CONTENT_DIR . '/uploads/civicrm/custom';
    $roots = [$primary];
    if (preg_match('/^application-[1-9][0-9]*-[a-z0-9]{24}\.jpg$/D', $filename)) {
        $roots[] = WP_CONTENT_DIR . '/uploads/bec-membership-photos';
    }
    foreach ($roots as $root) {
        $root_real = realpath($root);
        if ($root_real === false || !is_dir($root_real)) {
            continue;
        }
        $source = realpath($root_real . '/' . $filename);
        if ($source !== false && strpos($source, $root_real . DIRECTORY_SEPARATOR) === 0
            && is_file($source)) {
            return $source;
        }
    }
    return '';
}

function facewall_unknown_image_html($name = '') {
    $url = function_exists('plugins_url')
        ? plugins_url('img/unknown.jpg', dirname(__DIR__) . '/bec-facewall.php')
        : '/wp/wp-content/plugins/' . rawurlencode(basename(dirname(__DIR__))) . '/img/unknown.jpg';
    return '<img src="' . facewall_h($url)
        . '" class="fw_image" width="174" height="205" loading="lazy" alt="'
        . facewall_h($name !== '' ? 'No picture for ' . $name : 'No picture') . '">';
}

/**
 * Read-only renderer. Public page requests must never generate image files.
 * The nightly cron can generate validated missing thumbnails separately.
 */
function facewall_photo_html($stored, $name = '', $is_public = false, $fallback_stored = '')
{
    if (!$is_public || !defined('WP_CONTENT_DIR')) {
        return facewall_unknown_image_html($name);
    }

    // Historical summary images can be missing or stale after a rebuild.
    // Try both stored references without writing files or fetching remote URLs.
    // Consent is checked BEFORE either reference is considered.
    foreach ([$stored, $fallback_stored] as $candidate) {
        $filename = facewall_image_filename($candidate);
        if ($filename === '') {
            continue;
        }
        $relative = '/uploads/civicrm/custom/tn/174/tn_' . $filename;
        $path = WP_CONTENT_DIR . $relative;
        if (!is_file($path)) {
            continue;
        }

        // Use the real local filename for filesystem access and encode the
        // URL segment separately (spaces, parentheses, and Unicode).
        $web_relative = '/uploads/civicrm/custom/tn/174/tn_' . rawurlencode($filename);
        $url = function_exists('content_url')
            ? content_url($web_relative)
            : '/wp/wp-content' . $web_relative;
        return '<img src="' . facewall_h($url)
            . '" class="fw_image" width="174" height="205" loading="lazy" alt="'
            . facewall_h($name) . '">';
    }
    return facewall_unknown_image_html($name);
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


/**
 * A poster prefers the larger cached crop, but a consenting member's
 * existing 174px FaceWall thumbnail is better than the placeholder.
 * This is a read-only path decision: never create files during a PDF request.
 */
function facewall_poster_image_path($stored, $is_public = false, $fallback_stored = '') {
    if (!$is_public || !defined('WP_CONTENT_DIR')) {
        return '';
    }
    // Current CiviCRM photos may have been replaced or cleared while an
    // existing consenting historical FaceWall summary still has a usable
    // cached photo. Match the fallback by contact ID at SQL call sites.
    foreach ([$stored, $fallback_stored] as $reference) {
        $full = facewall_full_size_thumbnail_path($reference, true);
        if ($full !== '') {
            return $full;
        }
        $filename = facewall_image_filename($reference);
        if ($filename === '') {
            continue;
        }
        $path = WP_CONTENT_DIR . '/uploads/civicrm/custom/tn/174/tn_' . $filename;
        if (is_file($path)) {
            return $path;
        }
    }
    return '';
}
