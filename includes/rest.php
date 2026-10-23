<?php
/**
 * Public endpoint the frontend script calls for the widget config and access token.
 *
 * It is public on purpose (every visitor's browser needs a token), so errors are
 * generic: URLs, origins, and Nalu's responses are logged, never returned.
 */

defined('ABSPATH') || exit;

add_action('rest_api_init', function () {
    register_rest_route('nalu-ai/v1', '/widget-config', array(
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'nalu_ai_rest_widget_config',
        'permission_callback' => '__return_true',
    ));
});

function nalu_ai_rest_widget_config() {
    $settings = nalu_ai_get_settings();
    $token = nalu_ai_is_ready($settings) ? nalu_ai_get_widget_token($settings) : null;

    if (!is_array($token)) {
        return new WP_Error(
            'nalu_ai_unavailable',
            __('The chatbot is not available right now.', 'nalu-ai'),
            array('status' => 503)
        );
    }

    $base_url = untrailingslashit($settings['base_url']);

    // Response fields are unchanged from 1.x, so pages cached with the old inline script still work.
    return new WP_REST_Response(array(
        'ok' => true,
        'scriptUrl' => $base_url . '/assets/chatbot.min.js',
        'apiUrl' => $base_url . '/api/v1/agents/' . rawurlencode($settings['agent_slug']),
        'accessToken' => $token['accessToken'],
        'expiresAt' => $token['expiresAt'],
        // Relative lifetime, so the browser's refresh timer doesn't depend on the visitor's clock.
        'expiresIn' => $token['expiresAt'] ? max(0, $token['expiresAt'] - time()) : null,
        'width' => $settings['width'],
        'height' => $settings['height'],
    ), 200, array(
        'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
    ));
}
