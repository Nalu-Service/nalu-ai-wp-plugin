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
        // More agents for [nalu_ai_chatbot agent="..."], each with its own embed credentials:
        // list of array('agent_slug' => ..., 'client_id' => ..., 'client_secret' => ...).
        'extra_agents' => array(),
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

/**
 * Agent slugs are letters, digits, hyphens, and underscores. Nalu slugs are case-sensitive
 * (e.g. "UxXoq"), so the case is kept exactly as entered.
 */
function nalu_ai_sanitize_agent_slug($value) {
    $value = trim((string) $value);

    return preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]*$/', $value) ? $value : '';
}

/**
 * Settings for one agent: the main agent when $slug is empty or matches it, otherwise
 * the "More agents" entry with that slug, with its own credentials. Null when no
 * configured agent matches, so only agents the admin added can get a widget token.
 */
function nalu_ai_get_agent_settings($settings, $slug = '') {
    $slug = (string) $slug;

    if ($slug === '' || $slug === $settings['agent_slug']) {
        return nalu_ai_is_ready($settings) ? $settings : null;
    }

    if (!nalu_ai_is_ready($settings)) {
        return null;
    }

    foreach ((array) $settings['extra_agents'] as $agent) {
        if (
            is_array($agent) &&
            isset($agent['agent_slug'], $agent['client_id'], $agent['client_secret']) &&
            $agent['agent_slug'] === $slug &&
            $agent['client_id'] !== '' &&
            $agent['client_secret'] !== ''
        ) {
            return array_merge($settings, array(
                'agent_slug' => $agent['agent_slug'],
                'client_id' => $agent['client_id'],
                'client_secret' => $agent['client_secret'],
            ));
        }
    }

    return null;
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
        'extra_agents' => nalu_ai_sanitize_extra_agents(
            isset($input['extra_agents']) ? $input['extra_agents'] : array(),
            $current['extra_agents']
        ),
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

/**
 * Rows from the "More agents" table. A row without a slug is dropped (that's how an agent
 * is removed); a blank secret keeps the one saved for that slug, which is never echoed back.
 */
function nalu_ai_sanitize_extra_agents($rows, $current_rows) {
    $saved_secrets = array();

    foreach ((array) $current_rows as $row) {
        if (is_array($row) && !empty($row['agent_slug']) && isset($row['client_secret'])) {
            $saved_secrets[$row['agent_slug']] = $row['client_secret'];
        }
    }

    $agents = array();

    foreach (is_array($rows) ? $rows : array() as $row) {
        if (!is_array($row)) {
            continue;
        }

        $field = function ($key) use ($row) {
            return isset($row[$key]) ? trim(sanitize_text_field((string) $row[$key])) : '';
        };
        $raw_slug = $field('agent_slug');

        if ($raw_slug === '') {
            continue;
        }

        $slug = nalu_ai_sanitize_agent_slug($raw_slug);

        if ($slug === '' || isset($agents[$slug])) {
            add_settings_error(NALU_AI_SETTINGS_OPTION, 'invalid_extra_agent', sprintf(
                /* translators: %s: the agent slug the admin entered */
                __('"%s" is not a valid agent slug or is listed twice, so that row was not saved.', 'nalu-ai'),
                $raw_slug
            ));
            continue;
        }

        $secret = $field('client_secret');

        $agents[$slug] = array(
            'agent_slug' => $slug,
            'client_id' => $field('client_id'),
            'client_secret' => $secret !== '' ? $secret : (isset($saved_secrets[$slug]) ? $saved_secrets[$slug] : ''),
        );
    }

    return array_values($agents);
}

add_action('init', function () {
    register_setting('nalu_ai', NALU_AI_SETTINGS_OPTION, array(
        'type' => 'object',
        'sanitize_callback' => 'nalu_ai_sanitize_settings',
        'default' => nalu_ai_default_settings(),
        'show_in_rest' => false,
    ));
});
