<?php
/**
 * Updates from GitHub Releases through WordPress's own update system.
 *
 * The `Update URI` plugin header makes WordPress ask the `update_plugins_github.com`
 * filter (instead of wordpress.org) whether a newer version exists. Updates then
 * show on the Plugins screen and work with one-click and automatic updates.
 *
 * A release must be tagged vX.Y.Z and carry a `nalu-ai.zip` asset; the release
 * workflow in .github/workflows/release.yml builds both.
 */

defined('ABSPATH') || exit;

const NALU_AI_GITHUB_REPO = 'Nalu-Service/nalu-ai-wp-plugin';
const NALU_AI_RELEASE_ASSET = 'nalu-ai.zip';
const NALU_AI_RELEASE_TRANSIENT = 'nalu_ai_latest_release';

/**
 * Latest published release, cached for 6 hours (1 hour after a failed check).
 *
 * @return array{version: string, package: string, url: string, notes: string, published: string}|null
 */
function nalu_ai_get_latest_release() {
    $cached = get_site_transient(NALU_AI_RELEASE_TRANSIENT);

    if (is_array($cached)) {
        return empty($cached['version']) ? null : $cached;
    }

    $response = wp_remote_get('https://api.github.com/repos/' . NALU_AI_GITHUB_REPO . '/releases/latest', array(
        'timeout' => 10,
        'headers' => array('Accept' => 'application/vnd.github+json'),
    ));
    $body = json_decode(wp_remote_retrieve_body($response), true);
    $release = is_array($body) ? nalu_ai_parse_release($body) : null;

    if ($release === null) {
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 404) {
            error_log('[nalu-ai] Update check failed: ' . (is_wp_error($response)
                ? $response->get_error_message()
                : 'HTTP ' . wp_remote_retrieve_response_code($response)));
        }

        set_site_transient(NALU_AI_RELEASE_TRANSIENT, array(), HOUR_IN_SECONDS);

        return null;
    }

    set_site_transient(NALU_AI_RELEASE_TRANSIENT, $release, 6 * HOUR_IN_SECONDS);

    return $release;
}

/**
 * Reads a GitHub release payload. Only a vX.Y.Z tag with a nalu-ai.zip asset
 * downloaded from this repository's releases counts as an update.
 */
function nalu_ai_parse_release($body) {
    $tag = isset($body['tag_name']) ? (string) $body['tag_name'] : '';

    if (!preg_match('/^v?(\d+\.\d+\.\d+)$/', $tag, $matches) || !empty($body['draft']) || !empty($body['prerelease'])) {
        return null;
    }

    $download_prefix = 'https://github.com/' . NALU_AI_GITHUB_REPO . '/releases/download/';

    foreach (isset($body['assets']) && is_array($body['assets']) ? $body['assets'] : array() as $asset) {
        $url = isset($asset['browser_download_url']) ? (string) $asset['browser_download_url'] : '';

        if (isset($asset['name']) && $asset['name'] === NALU_AI_RELEASE_ASSET && strpos($url, $download_prefix) === 0) {
            return array(
                'version' => $matches[1],
                'package' => $url,
                'url' => isset($body['html_url']) ? (string) $body['html_url'] : '',
                'notes' => isset($body['body']) ? (string) $body['body'] : '',
                'published' => isset($body['published_at']) ? (string) $body['published_at'] : '',
            );
        }
    }

    return null;
}

function nalu_ai_plugin_slug() {
    return dirname(plugin_basename(NALU_AI_FILE));
}

add_filter('update_plugins_github.com', function ($update, $plugin_data, $plugin_file) {
    if ($plugin_file !== plugin_basename(NALU_AI_FILE)) {
        return $update;
    }

    $release = nalu_ai_get_latest_release();

    if ($release === null) {
        return $update;
    }

    // WordPress compares this version with the installed one and only offers it when newer.
    return array(
        'slug' => nalu_ai_plugin_slug(),
        'version' => $release['version'],
        'url' => $release['url'],
        'package' => $release['package'],
        'requires_php' => isset($plugin_data['RequiresPHP']) ? $plugin_data['RequiresPHP'] : '',
    );
}, 10, 3);

// Content for the "View version x.y.z details" popup on the Plugins screen.
add_filter('plugins_api', function ($result, $action, $args) {
    if ($action !== 'plugin_information' || !isset($args->slug) || $args->slug !== nalu_ai_plugin_slug()) {
        return $result;
    }

    $release = nalu_ai_get_latest_release();
    $plugin = get_plugin_data(NALU_AI_FILE, false, false);

    return (object) array(
        'name' => $plugin['Name'],
        'slug' => nalu_ai_plugin_slug(),
        'version' => $release ? $release['version'] : $plugin['Version'],
        'author' => $plugin['Author'],
        'homepage' => 'https://github.com/' . NALU_AI_GITHUB_REPO,
        'requires' => $plugin['RequiresWP'],
        'requires_php' => $plugin['RequiresPHP'],
        'last_updated' => $release ? $release['published'] : '',
        'download_link' => $release ? $release['package'] : '',
        'sections' => array(
            'description' => esc_html($plugin['Description']),
            'changelog' => $release && $release['notes'] !== ''
                ? nl2br(esc_html($release['notes']))
                : esc_html__('No release notes.', 'nalu-ai'),
        ),
    );
}, 10, 3);

/*
 * The release zip unpacks to nalu-ai/, but existing sites may have the plugin in
 * another folder (e.g. nalu-ai-wp-plugin-1.0.0 from a GitHub source download).
 * Unpacking into a different folder would change the plugin's path and leave it
 * deactivated, so the update is moved into the folder the site already uses.
 */
add_filter('upgrader_source_selection', function ($source, $remote_source, $upgrader, $hook_extra = array()) {
    global $wp_filesystem;

    if (empty($hook_extra['plugin']) || $hook_extra['plugin'] !== plugin_basename(NALU_AI_FILE) || is_wp_error($source)) {
        return $source;
    }

    $desired = trailingslashit($remote_source) . nalu_ai_plugin_slug() . '/';

    if (untrailingslashit($source) === untrailingslashit($desired)) {
        return $source;
    }

    if (!$wp_filesystem->move($source, $desired, true)) {
        return new WP_Error('nalu_ai_update_rename_failed', __('Could not prepare the Nalu AI update.', 'nalu-ai'));
    }

    return $desired;
}, 10, 4);

// Dashboard > Updates > "Check again" and finished updates skip the 6-hour cache.
add_action('upgrader_process_complete', function () {
    delete_site_transient(NALU_AI_RELEASE_TRANSIENT);
});

add_action('load-update-core.php', function () {
    if (!empty($_GET['force-check']) && current_user_can('update_plugins')) {
        delete_site_transient(NALU_AI_RELEASE_TRANSIENT);
    }
});
