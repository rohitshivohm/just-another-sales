<?php
/**
 * Plugin Name: Just Another QR
 * Description: Feature-rich QR code generator for WordPress with static/dynamic codes, analytics, campaigns, and bulk tools.
 * Version: 0.1.0
 * Author: Just Another Sales
 * Requires at least: 6.3
 * Requires PHP: 8.0
 * Text Domain: just-another-qr
 */

if (! defined('ABSPATH')) {
    exit;
}

define('JAQR_VERSION', '0.1.0');
define('JAQR_FILE', __FILE__);
define('JAQR_DIR', plugin_dir_path(__FILE__));
define('JAQR_URL', plugin_dir_url(__FILE__));

require_once JAQR_DIR . 'includes/class-plugin.php';

\JAQR\Plugin::instance();
