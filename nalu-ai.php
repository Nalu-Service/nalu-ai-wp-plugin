<?php

/**
 * Plugin Name: Nalu AI
 * Plugin URI:
 * Description: Nalu AI is a WordPress plugin for floating AI chatbots on your website.
 * Author: Dardee Jay Calura
 * version: 1.1.0
 * Author URI: https://github.com/dardeejay
 */

if (!defined('ABSPATH')) {
    exit;
}

const NALU_AI_SETTINGS_OPTION = 'nalu_ai_settings';

function nalu_ai_default_settings() {
    return array(
        'enabled' => '0',
        'base_url' => 'https://ai-support.naluservices.com',
        'server_base_url' => '',
        'agent_slug' => '',
        'client_id' => '',
        'client_secret' => '',
        'width' => '500px',
        'height' => '600px',
    );
}

function nalu_ai_get_settings() {
    $saved_settings = get_option(NALU_AI_SETTINGS_OPTION, array());

    return wp_parse_args($saved_settings, nalu_ai_default_settings());
}

function nalu_ai_get_site_origin() {
    $home_url = home_url('/');
    $parts = wp_parse_url($home_url);

    if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
        return '';
    }

    $origin = $parts['scheme'] . '://' . $parts['host'];

    if (!empty($parts['port'])) {
        $origin .= ':' . $parts['port'];
    }

    return $origin;
}

function nalu_ai_normalize_base_url($value) {
    $value = trim((string) $value);
    return untrailingslashit($value);
}

add_action('admin_menu', function () {
    add_options_page(
        'Nalu AI Settings',
        'Nalu AI',
        'manage_options',
        'nalu-ai-settings',
        'nalu_ai_settings_page'
    );
});

add_action('rest_api_init', function () {
    register_rest_route('nalu-ai/v1', '/widget-config', array(
        'methods' => 'GET',
        'callback' => 'nalu_ai_rest_widget_config',
        'permission_callback' => '__return_true',
    ));
});

function nalu_ai_settings_page() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('nalu_ai_save_settings', 'nalu_ai_nonce')) {
        $settings = array(
            'enabled' => isset($_POST['enabled']) ? '1' : '0',
            'base_url' => isset($_POST['base_url']) ? nalu_ai_normalize_base_url(wp_unslash($_POST['base_url'])) : '',
            'server_base_url' => isset($_POST['server_base_url']) ? nalu_ai_normalize_base_url(wp_unslash($_POST['server_base_url'])) : '',
            'agent_slug' => isset($_POST['agent_slug']) ? sanitize_text_field(wp_unslash($_POST['agent_slug'])) : '',
            'client_id' => isset($_POST['client_id']) ? sanitize_text_field(wp_unslash($_POST['client_id'])) : '',
            'client_secret' => isset($_POST['client_secret']) ? sanitize_text_field(wp_unslash($_POST['client_secret'])) : '',
            'width' => isset($_POST['width']) ? sanitize_text_field(wp_unslash($_POST['width'])) : '500px',
            'height' => isset($_POST['height']) ? sanitize_text_field(wp_unslash($_POST['height'])) : '600px',
        );

        update_option(NALU_AI_SETTINGS_OPTION, $settings);
        echo '<div class="updated"><p>Saved!</p></div>';
    }

    $settings = nalu_ai_get_settings();
    $site_origin = nalu_ai_get_site_origin();
    ?>
    <div class="wrap">
        <h1>Nalu AI Settings</h1>
        <p>Copy the WordPress integration values from your Nalu agent dashboard and paste them here.</p>

        <form method="post">
            <?php wp_nonce_field('nalu_ai_save_settings', 'nalu_ai_nonce'); ?>

            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">Enabled</th>
                        <td>
                            <label>
                                <input type="checkbox" name="enabled" value="1" <?php checked($settings['enabled'], '1'); ?> />
                                Load the chatbot on the frontend
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="base_url">Nalu Base URL</label></th>
                        <td>
                            <input
                                name="base_url"
                                id="base_url"
                                type="url"
                                class="regular-text"
                                value="<?php echo esc_attr($settings['base_url']); ?>"
                                placeholder="https://ai-support.naluservices.com"
                            />
                            <p class="description">Used by the browser to load the chatbot script and API URL.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="server_base_url">Server Base URL</label></th>
                        <td>
                            <input
                                name="server_base_url"
                                id="server_base_url"
                                type="url"
                                class="regular-text"
                                value="<?php echo esc_attr($settings['server_base_url']); ?>"
                                placeholder="http://host.docker.internal:3000"
                            />
                            <p class="description">Optional. Used by WordPress when requesting the widget token. Helpful when WordPress runs in Docker and the public base URL is not reachable from the container.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="agent_slug">Agent Slug</label></th>
                        <td>
                            <input
                                name="agent_slug"
                                id="agent_slug"
                                type="text"
                                class="regular-text"
                                value="<?php echo esc_attr($settings['agent_slug']); ?>"
                            />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="client_id">WordPress Client ID</label></th>
                        <td>
                            <input
                                name="client_id"
                                id="client_id"
                                type="text"
                                class="regular-text"
                                value="<?php echo esc_attr($settings['client_id']); ?>"
                            />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="client_secret">WordPress Client Secret</label></th>
                        <td>
                            <input
                                name="client_secret"
                                id="client_secret"
                                type="password"
                                class="regular-text"
                                value="<?php echo esc_attr($settings['client_secret']); ?>"
                                autocomplete="off"
                            />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="width">Widget Width</label></th>
                        <td>
                            <input
                                name="width"
                                id="width"
                                type="text"
                                class="regular-text"
                                value="<?php echo esc_attr($settings['width']); ?>"
                                placeholder="500px"
                            />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="height">Widget Height</label></th>
                        <td>
                            <input
                                name="height"
                                id="height"
                                type="text"
                                class="regular-text"
                                value="<?php echo esc_attr($settings['height']); ?>"
                                placeholder="600px"
                            />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">This Site Origin</th>
                        <td>
                            <code><?php echo esc_html($site_origin ?: 'Unavailable'); ?></code>
                            <p class="description">Add this exact origin to the agent's Allowed Origins in Nalu.</p>
                        </td>
                    </tr>
                </tbody>
            </table>

            <?php submit_button('Save Settings'); ?>
        </form>
    </div>
    <?php
}

function nalu_ai_issue_widget_token($settings) {
    $site_origin = nalu_ai_get_site_origin();

    if (empty($site_origin)) {
        return new WP_Error('nalu_ai_invalid_origin', 'Unable to determine this WordPress site origin.');
    }

    $server_base_url = !empty($settings['server_base_url']) ? $settings['server_base_url'] : $settings['base_url'];
    $server_base_url = nalu_ai_normalize_base_url($server_base_url);
    $request_url = $server_base_url . '/api/v1/widget/token';

    $response = wp_remote_post($request_url, array(
        'timeout' => 10,
        'headers' => array(
            'Content-Type' => 'application/json',
            'x-nalu-client-id' => $settings['client_id'],
            'x-nalu-client-secret' => $settings['client_secret'],
        ),
        'body' => wp_json_encode(array(
            'agentSlug' => $settings['agent_slug'],
            'siteOrigin' => $site_origin,
        )),
    ));

    if (is_wp_error($response)) {
        return $response;
    }

    $status = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);

    if ($status !== 200 || empty($body['accessToken'])) {
        $error_message = !empty($body['error']) ? $body['error'] : 'Failed to fetch Nalu widget token.';
        return new WP_Error('nalu_ai_widget_token_failed', $error_message);
    }

    return $body['accessToken'];
}

function nalu_ai_rest_widget_config() {
    $settings = nalu_ai_get_settings();

    if (
        $settings['enabled'] !== '1' ||
        empty($settings['base_url']) ||
        empty($settings['agent_slug']) ||
        empty($settings['client_id']) ||
        empty($settings['client_secret'])
    ) {
        return new WP_Error(
            'nalu_ai_not_configured',
            'Nalu AI plugin is not fully configured.',
            array('status' => 400)
        );
    }

    $access_token = nalu_ai_issue_widget_token($settings);

    if (is_wp_error($access_token)) {
        return new WP_Error(
            'nalu_ai_widget_token_failed',
            $access_token->get_error_message(),
            array('status' => 500)
        );
    }

    $base_url = nalu_ai_normalize_base_url($settings['base_url']);

    return rest_ensure_response(array(
        'ok' => true,
        'scriptUrl' => $base_url . '/assets/chatbot.min.js',
        'apiUrl' => $base_url . '/api/v1/agents/' . rawurlencode($settings['agent_slug']),
        'accessToken' => $access_token,
        'width' => trim((string) $settings['width']),
        'height' => trim((string) $settings['height']),
    ));
}

add_action('wp_footer', function () {
    $settings = nalu_ai_get_settings();

    if (
        $settings['enabled'] !== '1' ||
        empty($settings['base_url']) ||
        empty($settings['agent_slug'])
    ) {
        return;
    }

    $config_url = esc_url(rest_url('nalu-ai/v1/widget-config'));
    ?>
    <script>
        (function () {
            const ensureScript = (src) =>
                new Promise((resolve, reject) => {
                    const existing = document.querySelector(`script[data-nalu-ai-script="1"][src="${src}"]`);
                    if (existing) {
                        if (existing.dataset.loaded === "1") {
                            resolve();
                            return;
                        }
                        existing.addEventListener("load", () => resolve(), { once: true });
                        existing.addEventListener("error", () => reject(new Error("Failed to load Nalu widget script")), { once: true });
                        return;
                    }

                    const script = document.createElement("script");
                    script.src = src;
                    script.defer = true;
                    script.dataset.naluAiScript = "1";
                    script.addEventListener("load", () => {
                        script.dataset.loaded = "1";
                        resolve();
                    }, { once: true });
                    script.addEventListener("error", () => reject(new Error("Failed to load Nalu widget script")), { once: true });
                    document.head.appendChild(script);
                });

            const mountWidget = async () => {
                try {
                    const response = await fetch(<?php echo wp_json_encode($config_url); ?>, {
                        credentials: "same-origin",
                    });

                    if (!response.ok) {
                        throw new Error("Failed to fetch Nalu widget config");
                    }

                    const payload = await response.json();
                    await ensureScript(payload.scriptUrl);

                    if (document.querySelector("my-chatbot-widget[data-nalu-ai-widget='1']")) {
                        return;
                    }

                    const widget = document.createElement("my-chatbot-widget");
                    widget.dataset.naluAiWidget = "1";
                    widget.setAttribute("api-url", payload.apiUrl);
                    widget.setAttribute("access-token", payload.accessToken);

                    if (payload.width) {
                        widget.setAttribute("width", payload.width);
                    }

                    if (payload.height) {
                        widget.setAttribute("height", payload.height);
                    }

                    document.body.appendChild(widget);
                } catch (error) {
                    console.error("Nalu AI plugin error:", error);
                }
            };

            if (document.readyState === "loading") {
                document.addEventListener("DOMContentLoaded", mountWidget, { once: true });
            } else {
                mountWidget();
            }
        })();
    </script>
    <?php
});
