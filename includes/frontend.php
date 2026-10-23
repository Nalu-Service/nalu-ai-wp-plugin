<?php
/**
 * Loads the frontend script in automatic mode, or on pages that use the shortcode.
 */

defined('ABSPATH') || exit;

// Registered on init (not wp_enqueue_scripts) because block themes render the
// shortcode before wp_enqueue_scripts fires.
add_action('init', function () {
    wp_register_script(
        'nalu-ai',
        plugins_url('assets/js/nalu-ai.js', NALU_AI_FILE),
        array(),
        NALU_AI_VERSION,
        array('in_footer' => true, 'strategy' => 'defer')
    );
    wp_add_inline_script(
        'nalu-ai',
        'window.NaluAIConfig = ' . wp_json_encode(array('configUrl' => rest_url('nalu-ai/v1/widget-config'))) . ';',
        'before'
    );
});

add_action('wp_enqueue_scripts', function () {
    $settings = nalu_ai_get_settings();

    if (nalu_ai_is_ready($settings) && $settings['load_mode'] === 'auto') {
        wp_enqueue_script('nalu-ai');
    }
});

/**
 * [nalu_ai_chatbot width="420px" height="640px"]
 *
 * Shows the floating chatbot on this page and optionally overrides its size. The
 * hosted widget always floats, so the shortcode's position in the content doesn't matter.
 */
add_shortcode('nalu_ai_chatbot', function ($atts) {
    $settings = nalu_ai_get_settings();

    if (!nalu_ai_is_ready($settings)) {
        return '';
    }

    $atts = shortcode_atts(array(
        'width' => '',
        'height' => '',
    ), $atts, 'nalu_ai_chatbot');

    wp_enqueue_script('nalu-ai');

    $attributes = '';

    foreach (array('width', 'height') as $key) {
        $value = trim((string) $atts[$key]);

        if ($value !== '' && nalu_ai_is_css_length($value)) {
            $attributes .= sprintf(' data-%s="%s"', $key, esc_attr($value));
        }
    }

    return '<div class="nalu-ai-chatbot" data-nalu-ai-chatbot hidden' . $attributes . '></div>';
});
