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
            'description' => __('<code>[nalu_ai_chatbot]</code> shows the floating chatbot on a page, optionally resized: <code>[nalu_ai_chatbot width="420px" height="640px"]</code>. <code>[nalu_ai_chatbot mode="inline" height="640px"]</code> puts the chat right where the shortcode is, as part of the page. Add <code>agent="slug"</code> to use one of the agents under More agents. Pages with a chatbot shortcode don\'t also get the automatic floating chat.', 'nalu-ai'),
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
        'extra_agents' => array(
            'label' => __('More agents', 'nalu-ai'),
            'type' => 'agents',
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

    if ($field['type'] === 'agents') {
        nalu_ai_render_extra_agents($value);
        return;
    }

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

/**
 * Table of extra agents: the saved ones plus one empty row for adding another. "Add another
 * agent" appends more empty rows (without JavaScript, saving adds a fresh empty row instead).
 * Clearing a row's slug removes that agent. Saved secrets are never printed into the page.
 */
function nalu_ai_render_extra_agents($agents) {
    $agents = is_array($agents) ? array_values($agents) : array();
    $empty = array('agent_slug' => '', 'client_id' => '', 'client_secret' => '');
    $rows = array_merge($agents, array($empty));
    ?>
    <table class="widefat striped nalu-ai-agents">
        <thead>
            <tr>
                <th scope="col"><?php esc_html_e('Agent slug', 'nalu-ai'); ?></th>
                <th scope="col"><?php esc_html_e('Client ID', 'nalu-ai'); ?></th>
                <th scope="col"><?php esc_html_e('Client secret', 'nalu-ai'); ?></th>
            </tr>
        </thead>
        <tbody data-nalu-ai-agent-rows data-next-index="<?php echo esc_attr(count($rows)); ?>">
            <?php foreach ($rows as $index => $agent) : ?>
                <?php nalu_ai_render_extra_agent_row($index, $agent); ?>
            <?php endforeach; ?>
        </tbody>
    </table>
    <template data-nalu-ai-agent-row-template>
        <?php nalu_ai_render_extra_agent_row('__INDEX__', $empty); ?>
    </template>
    <p>
        <button type="button" class="button" data-nalu-ai-add-agent hidden>
            <?php esc_html_e('Add another agent', 'nalu-ai'); ?>
        </button>
    </p>
    <p class="description">
        <?php echo wp_kses(
            __('Optional. Agents for pages that need a different chatbot, such as a lead agent on one page: <code>[nalu_ai_chatbot agent="slug" mode="inline"]</code>. Each needs its own credentials from Nalu and this site in its Allowed Origins. Slugs are case-sensitive, so copy them exactly. Clear a slug to remove that agent.', 'nalu-ai'),
            array('code' => array())
        ); ?>
    </p>
    <?php
}

function nalu_ai_render_extra_agent_row($index, $agent) {
    $name = NALU_AI_SETTINGS_OPTION . "[extra_agents][{$index}]";
    $is_saved = $agent['agent_slug'] !== '';
    ?>
    <tr>
        <td>
            <input type="text" class="regular-text"
                aria-label="<?php esc_attr_e('Agent slug', 'nalu-ai'); ?>"
                name="<?php echo esc_attr("{$name}[agent_slug]"); ?>"
                value="<?php echo esc_attr($agent['agent_slug']); ?>"
                placeholder="<?php echo $is_saved ? '' : esc_attr__('Add an agent…', 'nalu-ai'); ?>" />
        </td>
        <td>
            <input type="text" class="regular-text"
                aria-label="<?php esc_attr_e('Client ID', 'nalu-ai'); ?>"
                name="<?php echo esc_attr("{$name}[client_id]"); ?>"
                value="<?php echo esc_attr($agent['client_id']); ?>" />
        </td>
        <td>
            <input type="password" class="regular-text" autocomplete="new-password"
                aria-label="<?php esc_attr_e('Client secret', 'nalu-ai'); ?>"
                name="<?php echo esc_attr("{$name}[client_secret]"); ?>"
                value=""
                placeholder="<?php echo $is_saved && $agent['client_secret'] !== '' ? esc_attr__('Saved. Leave blank to keep it.', 'nalu-ai') : ''; ?>" />
        </td>
    </tr>
    <?php
}

add_action('admin_enqueue_scripts', function ($hook_suffix) {
    if ($hook_suffix !== 'settings_page_' . NALU_AI_SETTINGS_PAGE) {
        return;
    }

    wp_enqueue_style(
        'nalu-ai-admin',
        plugins_url('assets/css/nalu-ai-admin.css', NALU_AI_FILE),
        array(),
        NALU_AI_VERSION
    );

    wp_enqueue_script(
        'nalu-ai-admin',
        plugins_url('assets/js/nalu-ai-admin.js', NALU_AI_FILE),
        array(),
        NALU_AI_VERSION,
        true
    );
});

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
                    <p><code><?php echo nl2br(esc_html($test_result['details'])); ?></code></p>
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
        // The main agent, then each agent under More agents, each with its own credentials.
        $agents = array($settings);

        foreach ((array) $settings['extra_agents'] as $extra) {
            $agents[] = array_merge($settings, $extra);
        }

        $failures = array();
        $details = array();

        foreach ($agents as $agent) {
            if ($agent['client_id'] === '' || $agent['client_secret'] === '') {
                $failures[] = $agent['agent_slug'];
                $details[] = sprintf('%s · client ID or secret missing', $agent['agent_slug']);
                continue;
            }

            $token = nalu_ai_request_widget_token($agent);

            if (is_wp_error($token)) {
                $data = $token->get_error_data();
                $failures[] = $agent['agent_slug'];
                $details[] = sprintf(
                    '%s · %s · POST %s · status %s · siteOrigin %s',
                    $agent['agent_slug'],
                    $token->get_error_message(),
                    isset($data['requestUrl']) ? $data['requestUrl'] : 'n/a',
                    !empty($data['status']) ? $data['status'] : 'no response',
                    isset($data['siteOrigin']) ? $data['siteOrigin'] : 'n/a'
                );
            } else {
                // A working connection should take effect immediately, not after a cached failure expires.
                delete_transient(nalu_ai_token_transient($agent));
            }
        }

        if ($failures) {
            $result = array(
                'ok' => false,
                /* translators: %s: comma-separated agent slugs */
                'message' => sprintf(__('Connection failed for: %s', 'nalu-ai'), implode(', ', $failures)),
                'details' => implode("\n", $details),
            );
        } else {
            $result = array(
                'ok' => true,
                'message' => $settings['enabled'] === '1'
                    ? sprintf(
                        /* translators: %d: number of agents */
                        _n('Connected. Nalu issued a widget token for this site.', 'Connected. Nalu issued widget tokens for all %d agents.', count($agents), 'nalu-ai'),
                        count($agents)
                    )
                    : __('Connected. Enable the chatbot to show it to visitors.', 'nalu-ai'),
            );
        }
    }

    set_transient(nalu_ai_test_result_key(), $result, MINUTE_IN_SECONDS);
    wp_safe_redirect(nalu_ai_settings_page_url());
    exit;
});
