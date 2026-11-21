<?php
/**
 * Loads the frontend script in automatic mode, or on pages that use the shortcode.
 */

defined('ABSPATH') || exit;

// Registered on init (not wp_enqueue_scripts) because block themes render the
// shortcode before wp_enqueue_scripts fires.
add_action('init', function () {
    $settings = nalu_ai_get_settings();

    wp_register_script(
        'nalu-ai',
        plugins_url('assets/js/nalu-ai.js', NALU_AI_FILE),
        array(),
        NALU_AI_VERSION,
        // In the head, deferred: it still runs after the page is parsed, and it loads on templates
        // that never call wp_footer() (landing pages, page-builder canvases), so the floating chat
        // shows sitewide. A shortcode that enqueues it after wp_head (classic themes) still lands
        // in the footer, which is the only place left to print it.
        array('in_footer' => false, 'strategy' => 'defer')
    );
    wp_add_inline_script(
        'nalu-ai',
        'window.NaluAIConfig = ' . wp_json_encode(array(
            'configUrl' => rest_url('nalu-ai/v1/widget-config'),
            // "Every page" mode: show the floating chat unless the page already has a chatbot shortcode.
            'autoFloating' => $settings['load_mode'] === 'auto',
        )) . ';',
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
 * [nalu_ai_chatbot]
 * [nalu_ai_chatbot width="420px" height="640px"]
 * [nalu_ai_chatbot mode="inline" agent="parts-lead" height="640px"]
 *
 * Floating (the default): shows the floating chatbot on this page, optionally resized;
 * where the shortcode sits in the content doesn't matter.
 * Inline: shows the chat right where the shortcode is, as part of the page, with no
 * launcher or header. `width` is then its maximum width; it fills the content column.
 *
 * `agent` picks one of the agents under Settings > Nalu AI > More agents; without it the
 * main agent is used. A page with a chatbot shortcode doesn't also get the automatic
 * floating chat.
 */
add_shortcode('nalu_ai_chatbot', function ($atts) {
    $settings = nalu_ai_get_settings();

    $atts = shortcode_atts(array(
        'width' => '',
        'height' => '',
        'mode' => 'floating',
        'agent' => '',
    ), $atts, 'nalu_ai_chatbot');

    $mode = $atts['mode'] === 'inline' ? 'inline' : 'floating';
    $agent = nalu_ai_sanitize_agent_slug($atts['agent']);

    // A typo in agent="..." shows nothing rather than quietly using the main agent.
    if ((trim((string) $atts['agent']) !== '' && $agent === '') || !nalu_ai_get_agent_settings($settings, $agent)) {
        return '';
    }

    wp_enqueue_script('nalu-ai');

    $attributes = sprintf(' data-mode="%s"', esc_attr($mode));

    if ($agent !== '') {
        $attributes .= sprintf(' data-agent="%s"', esc_attr($agent));
    }

    foreach (array('width', 'height') as $key) {
        $value = trim((string) $atts[$key]);

        if ($value !== '' && nalu_ai_is_css_length($value)) {
            $attributes .= sprintf(' data-%s="%s"', $key, esc_attr($value));
        }
    }

    // An inline chat takes up its place in the content; a floating one only marks the page.
    return sprintf(
        '<div class="nalu-ai-chatbot nalu-ai-chatbot--%s" data-nalu-ai-chatbot%s%s></div>',
        esc_attr($mode),
        $mode === 'floating' ? ' hidden' : '',
        $attributes
    );
});
