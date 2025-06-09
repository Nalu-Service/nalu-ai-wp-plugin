<?php

/**
 * Plugin Name: Nalu AI
 * Plugin URI:
 * Description: Nalu AI is a WordPress plugin for floating AI chatbots on your website.
 * Author: Dardee Jay Calura
 * version: 1.0.0
 * Author URI: https://github.com/dardeejay
 */


if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

add_action('admin_menu', function () {
    add_options_page(
        'Chatbot Embed Settings',
        'Chatbot Embed',
        'manage_options',
        'chatbot-embed',
        'chatbot_embed_settings_page'
    );
});

function chatbot_embed_settings_page() {
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('chatbot_embed_save', 'chatbot_embed_nonce')) {
        if (isset($_POST['chatbot_embed_html'])) {
            // Save raw HTML (we'll escape on output instead)
            update_option('chatbot_embed_html', wp_unslash($_POST['chatbot_embed_html']));
            echo '<div class="updated"><p>Saved!</p></div>';
        }
    }

    $embed_code = get_option('chatbot_embed_html', '');
    ?>
    <div class="wrap">
        <h1>Chatbot Embed Settings</h1>
        <form method="post">
            <?php wp_nonce_field('chatbot_embed_save', 'chatbot_embed_nonce'); ?>
            <textarea name="chatbot_embed_html" rows="8" cols="80" style="width: 100%;"><?php echo esc_textarea($embed_code); ?></textarea>
            <p><input type="submit" class="button-primary" value="Save"></p>
        </form>
    </div>
    <?php
}


add_action('wp_footer', function () {
    if (!is_admin()) {
        echo '
        <script src="https://ai-support.naluservices.com/assets/chatbot.min.js" type="module"></script>
        <style>
            my-chatbot-widget {
                --chatbot-width: 400px;
                --chatbot-header-color: #4FB3A1;
            }
        </style>
        ';
        echo get_option('chatbot_embed_html', '');
    }
});

