<?php
defined('ABSPATH') || exit;

/**
 * Retired standalone script.
 *
 * Do not run FaceWall reporting jobs by requesting a PHP file directly.
 * The supported entry point is the registered WordPress cron hook
 * "facewall_cron" in facewall.php, which uses a database concurrency lock.
 */
