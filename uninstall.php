<?php
/**
 * Removes the plugin's settings and cached token when the plugin is deleted.
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

delete_option('nalu_ai_settings');
delete_transient('nalu_ai_widget_token');
