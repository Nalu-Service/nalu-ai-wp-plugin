<?php
/**
 * Settings screen (Settings > Nalu AI) and the "Test connection" action.
 */

defined('ABSPATH') || exit;

const NALU_AI_SETTINGS_PAGE = 'nalu-ai-settings';

function nalu_ai_settings_page_url() {
    return admin_url('options-general.php?page=' . NALU_AI_SETTINGS_PAGE);
}

add_action('admin_menu', function () {
    add_options_page(
        __('Nalu AI Settings', 'nalu-ai'),
        __('Nalu AI', 'nalu-ai'),
        'manage_options',
        NALU_AI_SETTINGS_PAGE,
        'nalu_ai_render_settings_page'
    );
});

add_filter('plugin_action_links_' . plugin_basename(NALU_AI_FILE), function ($links) {
    array_unshift($links, sprintf(
        '<a href="%s">%s</a>',
        esc_url(nalu_ai_settings_page_url()),
        esc_html__('Settings', 'nalu-ai')
    ));

    return $links;
});

function nalu_ai_settings_fields() {
    return array(
        'enabled' => array(
            'label' => __('Enabled', 'nalu-ai'),
            'type' => 'checkbox',
            'checkbox_label' => __('Show the chatbot on the site', 'nalu-ai'),
        ),
        'load_mode' => array(
            'label' => __('Display mode', 'nalu-ai'),
            'type' => 'select',
            'options' => array(
                'auto' => __('Every page', 'nalu-ai'),
                'shortcode' => __('Only pages with the shortcode', 'nalu-ai'),
            ),
            'description' => __('Add <code>[nalu_ai_chatbot]</code> to a page or post to show the chatbot there. It always floats in the corner; the shortcode can override its size, e.g. <code>[nalu_ai_chatbot width="420px" height="640px"]</code>.', 'nalu-ai'),
        ),
        'base_url' => array(
            'label' => __('Nalu URL', 'nalu-ai'),
            'type' => 'url',
            'placeholder' => 'https://dreamit.naluservices.com',
            'description' => __('The Nalu app your agent lives in. Visitors\' browsers load the widget from here.', 'nalu-ai'),
        ),
        'server_base_url' => array(
            'label' => __('Server-side Nalu URL', 'nalu-ai'),
            'type' => 'url',
            'placeholder' => 'http://host.docker.internal:3000',
            'description' => __('Optional. Used only by WordPress to request widget tokens, for setups where WordPress cannot reach the Nalu URL above (for example WordPress running in Docker).', 'nalu-ai'),
        ),
        'agent_slug' => array(
            'label' => __('Agent slug', 'nalu-ai'),
            'type' => 'text',
        ),
        'client_id' => array(
            'label' => __('Client ID', 'nalu-ai'),
            'type' => 'text',
        ),
        'client_secret' => array(
            'label' => __('Client secret', 'nalu-ai'),
            'type' => 'secret',
        ),
        'width' => array(
            'label' => __('Widget width', 'nalu-ai'),
            'type' => 'text',
            'placeholder' => '500px',
        ),
        'height' => array(
            'label' => __('Widget height', 'nalu-ai'),
            'type' => 'text',
            'placeholder' => '600px',
            'description' => __('Desktop size, e.g. 600px or 80vh. On phones the chat fills the screen.', 'nalu-ai'),
        ),
    );
}

add_action('admin_init', function () {
    add_settings_section('nalu_ai_main', '', '__return_false', NALU_AI_SETTINGS_PAGE);

    foreach (nalu_ai_settings_fields() as $key => $field) {
        add_settings_field(
            "nalu_ai_{$key}",
            $field['label'],
            'nalu_ai_render_field',
            NALU_AI_SETTINGS_PAGE,
            'nalu_ai_main',
            array('key' => $key, 'label_for' => $field['type'] === 'checkbox' ? '' : "nalu_ai_{$key}") + $field
        );
    }
});

function nalu_ai_render_field($field) {
    $settings = nalu_ai_get_stored_settings();
    $key = $field['key'];
    $id = "nalu_ai_{$key}";
    $name = NALU_AI_SETTINGS_OPTION . "[{$key}]";
    $value = $settings[$key];
    $description = isset($field['description']) ? $field['description'] : '';

    switch ($field['type']) {
        case 'checkbox':
            printf(
                '<label><input type="checkbox" name="%s" value="1" %s /> %s</label>',
                esc_attr($name),
                checked($value, '1', false),
                esc_html($field['checkbox_label'])
            );
            break;

        case 'select':
            printf('<select id="%s" name="%s">', esc_attr($id), esc_attr($name));
            foreach ($field['options'] as $option_value => $option_label) {
                printf(
                    '<option value="%s" %s>%s</option>',
                    esc_attr($option_value),
                    selected($value, $option_value, false),
                    esc_html($option_label)
                );
            }
            echo '</select>';
            break;

        case 'secret':
            // The saved secret is never printed into the page.
            if (nalu_ai_secret_is_constant()) {
                printf('<input type="password" id="%s" class="regular-text" disabled placeholder="••••••••" />', esc_attr($id));
                $description = __('Set by <code>NALU_AI_CLIENT_SECRET</code> in wp-config.php.', 'nalu-ai');
            } else {
                printf(
                    '<input type="password" id="%s" name="%s" class="regular-text" value="" autocomplete="new-password" placeholder="%s" />',
                    esc_attr($id),
                    esc_attr($name),
                    esc_attr($value !== '' ? __('Saved. Leave blank to keep it.', 'nalu-ai') : '')
                );
                $description = __('You can instead define <code>NALU_AI_CLIENT_SECRET</code> in wp-config.php to keep it out of the database.', 'nalu-ai');
            }
            break;

        default:
            printf(
                '<input type="%s" id="%s" name="%s" class="regular-text" value="%s" placeholder="%s" />',
                esc_attr($field['type']),
                esc_attr($id),
                esc_attr($name),
                esc_attr($value),
                esc_attr(isset($field['placeholder']) ? $field['placeholder'] : '')
            );
    }

    if ($description !== '') {
        printf('<p class="description">%s</p>', wp_kses($description, array('code' => array())));
    }
}

function nalu_ai_render_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $site_origin = nalu_ai_get_site_origin();
    $test_result = get_transient(nalu_ai_test_result_key());

    if ($test_result !== false) {
        delete_transient(nalu_ai_test_result_key());
    }
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Nalu AI Settings', 'nalu-ai'); ?></h1>

        <?php if (is_array($test_result)) : ?>
            <div class="notice notice-<?php echo $test_result['ok'] ? 'success' : 'error'; ?> is-dismissible">
                <p><?php echo esc_html($test_result['message']); ?></p>
                <?php if (!empty($test_result['details'])) : ?>
                    <p><code><?php echo esc_html($test_result['details']); ?></code></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <p><?php esc_html_e('In Nalu, open your agent, set its platform to WordPress, and copy the slug, client ID, and client secret from its embed credentials.', 'nalu-ai'); ?></p>
        <p>
            <?php esc_html_e('Add this site\'s origin to the agent\'s Allowed Origins:', 'nalu-ai'); ?>
            <code><?php echo esc_html($site_origin !== '' ? $site_origin : __('Unavailable', 'nalu-ai')); ?></code>
        </p>

        <form method="post" action="options.php">
            <?php
            settings_fields('nalu_ai');
            do_settings_sections(NALU_AI_SETTINGS_PAGE);
            submit_button(__('Save settings', 'nalu-ai'));
            ?>
        </form>

        <hr />
        <h2><?php esc_html_e('Test connection', 'nalu-ai'); ?></h2>
        <p><?php esc_html_e('Requests a widget token from Nalu with the saved settings. Save any changes first.', 'nalu-ai'); ?></p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="nalu_ai_test_connection" />
            <?php wp_nonce_field('nalu_ai_test_connection'); ?>
            <?php submit_button(__('Test connection', 'nalu-ai'), 'secondary', 'submit', false); ?>
        </form>
    </div>
    <?php
}

function nalu_ai_test_result_key() {
    return 'nalu_ai_test_result_' . get_current_user_id();
}

add_action('admin_post_nalu_ai_test_connection', function () {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You are not allowed to do this.', 'nalu-ai'), 403);
    }

    check_admin_referer('nalu_ai_test_connection');

    $settings = nalu_ai_get_settings();

    if (empty($settings['base_url']) || empty($settings['agent_slug']) || empty($settings['client_id']) || empty($settings['client_secret'])) {
        $result = array(
            'ok' => false,
            'message' => __('Fill in the Nalu URL, agent slug, client ID, and client secret, then save before testing.', 'nalu-ai'),
        );
    } else {
        $token = nalu_ai_request_widget_token($settings);

        if (is_wp_error($token)) {
            $data = $token->get_error_data();
            $result = array(
                'ok' => false,
                /* translators: %s: error message from Nalu or the HTTP client */
                'message' => sprintf(__('Connection failed: %s', 'nalu-ai'), $token->get_error_message()),
                'details' => sprintf(
                    'POST %s · status %s · siteOrigin %s',
                    isset($data['requestUrl']) ? $data['requestUrl'] : 'n/a',
                    !empty($data['status']) ? $data['status'] : 'no response',
                    isset($data['siteOrigin']) ? $data['siteOrigin'] : 'n/a'
                ),
            );
        } else {
            // A working connection should take effect immediately, not after a cached failure expires.
            delete_transient(NALU_AI_TOKEN_TRANSIENT);
            $result = array(
                'ok' => true,
                'message' => $settings['enabled'] === '1'
                    ? __('Connected. Nalu issued a widget token for this site.', 'nalu-ai')
                    : __('Connected. Nalu issued a widget token for this site. Enable the chatbot to show it to visitors.', 'nalu-ai'),
            );
        }
    }

    set_transient(nalu_ai_test_result_key(), $result, MINUTE_IN_SECONDS);
    wp_safe_redirect(nalu_ai_settings_page_url());
    exit;
});
