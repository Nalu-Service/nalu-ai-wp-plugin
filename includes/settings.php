<?php
/**
 * Settings storage, sanitizing, and readiness checks.
 *
 * Settings live in the `nalu_ai_settings` option with the same keys as 1.x,
 * so existing installs keep working after an upgrade.
 */

defined('ABSPATH') || exit;

function nalu_ai_default_settings() {
    return array(
        'enabled' => '0',
        'load_mode' => 'auto',
        'base_url' => '',
        'server_base_url' => '',
        'agent_slug' => '',
        'client_id' => '',
        'client_secret' => '',
        'width' => '500px',
        'height' => '600px',
    );
}

/**
 * Saved settings merged with defaults, without the wp-config.php secret override.
 */
function nalu_ai_get_stored_settings() {
    $saved = get_option(NALU_AI_SETTINGS_OPTION, array());

    return wp_parse_args(is_array($saved) ? $saved : array(), nalu_ai_default_settings());
}

function nalu_ai_secret_is_constant() {
    return defined('NALU_AI_CLIENT_SECRET') && (string) NALU_AI_CLIENT_SECRET !== '';
}

/**
 * Effective settings. NALU_AI_CLIENT_SECRET in wp-config.php wins over the saved secret.
 */
function nalu_ai_get_settings() {
    $settings = nalu_ai_get_stored_settings();

    if (nalu_ai_secret_is_constant()) {
        $settings['client_secret'] = (string) NALU_AI_CLIENT_SECRET;
    }

    return $settings;
}

function nalu_ai_is_ready($settings) {
    return (
        $settings['enabled'] === '1' &&
        !empty($settings['base_url']) &&
        !empty($settings['agent_slug']) &&
        !empty($settings['client_id']) &&
        !empty($settings['client_secret'])
    );
}

function nalu_ai_get_site_origin() {
    $parts = wp_parse_url(home_url('/'));

    if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
        return '';
    }

    $origin = $parts['scheme'] . '://' . $parts['host'];

    if (!empty($parts['port'])) {
        $origin .= ':' . $parts['port'];
    }

    return $origin;
}

/**
 * Returns an http(s) URL without a trailing slash, or '' when the value is not a valid URL.
 */
function nalu_ai_normalize_base_url($value) {
    $value = esc_url_raw(trim((string) $value), array('http', 'https'));

    if ($value === '' || empty(wp_parse_url($value, PHP_URL_HOST))) {
        return '';
    }

    return untrailingslashit($value);
}

function nalu_ai_is_css_length($value) {
    return (bool) preg_match('/^\d+(\.\d+)?(px|%|em|rem|vw|vh|dvh|svh|lvh)$/', $value);
}

/**
 * Settings API sanitize callback for the `nalu_ai_settings` option.
 */
function nalu_ai_sanitize_settings($input) {
    // options.php has already unslashed the posted values.
    $input = is_array($input) ? $input : array();
    $current = nalu_ai_get_stored_settings();
    $text = function ($key) use ($input) {
        return isset($input[$key]) ? trim(sanitize_text_field((string) $input[$key])) : '';
    };

    $settings = array(
        'enabled' => !empty($input['enabled']) ? '1' : '0',
        'load_mode' => $text('load_mode') === 'shortcode' ? 'shortcode' : 'auto',
        'base_url' => $current['base_url'],
        'server_base_url' => $current['server_base_url'],
        'agent_slug' => $text('agent_slug'),
        'client_id' => $text('client_id'),
        // A blank secret field means "keep the saved secret"; it is never echoed back to the page.
        'client_secret' => $text('client_secret') !== '' ? $text('client_secret') : $current['client_secret'],
        'width' => $current['width'],
        'height' => $current['height'],
    );

    foreach (array('base_url', 'server_base_url') as $key) {
        $raw = $text($key);
        $url = nalu_ai_normalize_base_url($raw);

        if ($raw !== '' && $url === '') {
            add_settings_error(NALU_AI_SETTINGS_OPTION, "invalid_{$key}", sprintf(
                /* translators: %s: the URL the admin entered */
                __('"%s" is not a valid http(s) URL, so the previous value was kept.', 'nalu-ai'),
                $raw
            ));
            continue;
        }

        $settings[$key] = $url;
    }

    foreach (array('width', 'height') as $key) {
        $raw = $text($key);

        if ($raw === '') {
            $settings[$key] = nalu_ai_default_settings()[$key];
        } elseif (nalu_ai_is_css_length($raw)) {
            $settings[$key] = $raw;
        } else {
            add_settings_error(NALU_AI_SETTINGS_OPTION, "invalid_{$key}", sprintf(
                /* translators: %s: the size the admin entered */
                __('"%s" is not a valid CSS size such as 500px, 80vh, or 90%%, so the previous value was kept.', 'nalu-ai'),
                $raw
            ));
        }
    }

    return $settings;
}

add_action('init', function () {
    register_setting('nalu_ai', NALU_AI_SETTINGS_OPTION, array(
        'type' => 'object',
        'sanitize_callback' => 'nalu_ai_sanitize_settings',
        'default' => nalu_ai_default_settings(),
        'show_in_rest' => false,
    ));
});
