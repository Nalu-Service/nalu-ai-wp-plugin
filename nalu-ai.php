<?php
/**
 * Plugin Name:       Nalu AI
 * Description:       Adds your Nalu AI chatbot to your WordPress site as a floating chat widget.
 * Version:           2.0.0
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Author:            Nalu Services
 * Author URI:        https://naluservices.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       nalu-ai
 */

defined('ABSPATH') || exit;

const NALU_AI_VERSION = '2.0.0';
const NALU_AI_FILE = __FILE__;
const NALU_AI_SETTINGS_OPTION = 'nalu_ai_settings';
const NALU_AI_TOKEN_TRANSIENT = 'nalu_ai_widget_token';

require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/token.php';
require_once __DIR__ . '/includes/rest.php';
require_once __DIR__ . '/includes/frontend.php';

if (is_admin()) {
    require_once __DIR__ . '/includes/admin.php';
}
