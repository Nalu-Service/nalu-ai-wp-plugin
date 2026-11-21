<?php
/**
 * Removes the plugin's settings and cached token when the plugin is deleted.
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

delete_option('nalu_ai_settings');
delete_transient('nalu_ai_widget_token');

// Cached tokens, one per agent (nalu_ai_widget_token_<md5 of the slug>).
global $wpdb;
$wpdb->query($wpdb->prepare(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
    $wpdb->esc_like('_transient_nalu_ai_widget_token_') . '%',
    $wpdb->esc_like('_transient_timeout_nalu_ai_widget_token_') . '%'
));
