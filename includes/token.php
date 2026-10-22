<?php
/**
 * Exchanges the client credentials for a short-lived Nalu widget access token.
 *
 * The token is scoped to the agent and this site's origin, not to a visitor, so
 * one cached token serves every page view. It is only reused while it still has
 * NALU_AI_TOKEN_MIN_LIFETIME left, so every visitor gets at least that long.
 */

defined('ABSPATH') || exit;

const NALU_AI_TOKEN_MIN_LIFETIME = 90 * MINUTE_IN_SECONDS;
const NALU_AI_TOKEN_ERROR_TTL = MINUTE_IN_SECONDS;

/**
 * Calls Nalu's POST /api/v1/widget/token.
 *
 * @return array{accessToken: string, expiresAt: int|null}|WP_Error Error data holds
 *         diagnostics (status, requestUrl, siteOrigin) meant for admins only.
 */
function nalu_ai_request_widget_token($settings) {
    $site_origin = nalu_ai_get_site_origin();

    if ($site_origin === '') {
        return new WP_Error('nalu_ai_invalid_origin', __('Unable to determine this site\'s origin from the Site Address setting.', 'nalu-ai'));
    }

    $server_base_url = !empty($settings['server_base_url']) ? $settings['server_base_url'] : $settings['base_url'];
    $request_url = untrailingslashit($server_base_url) . '/api/v1/widget/token';

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

    $diagnostics = array(
        'requestUrl' => $request_url,
        'siteOrigin' => $site_origin,
    );

    if (is_wp_error($response)) {
        return new WP_Error('nalu_ai_request_failed', $response->get_error_message(), $diagnostics + array('status' => 0));
    }

    $status = (int) wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);

    if ($status !== 200 || empty($body['accessToken'])) {
        $message = is_array($body) && !empty($body['error'])
            ? (string) $body['error']
            : __('Nalu did not return a widget token.', 'nalu-ai');

        return new WP_Error('nalu_ai_token_rejected', $message, $diagnostics + array('status' => $status));
    }

    return array(
        'accessToken' => (string) $body['accessToken'],
        'expiresAt' => isset($body['expiresAt']) && is_numeric($body['expiresAt']) ? (int) $body['expiresAt'] : null,
    );
}

/**
 * Changes whenever anything the token depends on changes, so saving new settings
 * never reuses a token or cached failure from the old ones.
 */
function nalu_ai_token_cache_key($settings) {
    return hash('sha256', implode("\n", array(
        $settings['base_url'],
        $settings['server_base_url'],
        $settings['agent_slug'],
        $settings['client_id'],
        $settings['client_secret'],
        nalu_ai_get_site_origin(),
    )));
}

/**
 * Cached wrapper around nalu_ai_request_widget_token(). Failures are cached for a
 * minute so an outage or bad credentials don't send one request per page view.
 */
function nalu_ai_get_widget_token($settings) {
    $key = nalu_ai_token_cache_key($settings);
    $cached = get_transient(NALU_AI_TOKEN_TRANSIENT);

    if (is_array($cached) && isset($cached['key']) && hash_equals($cached['key'], $key)) {
        if (!empty($cached['failed'])) {
            return new WP_Error('nalu_ai_token_unavailable', __('Nalu widget token is temporarily unavailable.', 'nalu-ai'));
        }

        if (!empty($cached['expiresAt']) && $cached['expiresAt'] - time() >= NALU_AI_TOKEN_MIN_LIFETIME) {
            return array(
                'accessToken' => $cached['accessToken'],
                'expiresAt' => $cached['expiresAt'],
            );
        }
    }

    $token = nalu_ai_request_widget_token($settings);

    if (is_wp_error($token)) {
        $data = $token->get_error_data();
        error_log(sprintf(
            '[nalu-ai] Widget token request failed (status %s, %s): %s',
            isset($data['status']) ? $data['status'] : 'n/a',
            isset($data['requestUrl']) ? $data['requestUrl'] : 'n/a',
            $token->get_error_message()
        ));
        set_transient(NALU_AI_TOKEN_TRANSIENT, array('key' => $key, 'failed' => true), NALU_AI_TOKEN_ERROR_TTL);

        return $token;
    }

    $cache_ttl = $token['expiresAt'] ? $token['expiresAt'] - time() - NALU_AI_TOKEN_MIN_LIFETIME : 0;

    if ($cache_ttl > 0) {
        set_transient(NALU_AI_TOKEN_TRANSIENT, array('key' => $key) + $token, $cache_ttl);
    } else {
        delete_transient(NALU_AI_TOKEN_TRANSIENT);
    }

    return $token;
}
