<?php

if (!defined('DISALLOW_FILE_EDIT')) {
    define('DISALLOW_FILE_EDIT', true);
}

add_action('init', function() {
    remove_action('wp_head', 'wp_generator');
    remove_action('wp_head', 'rsd_link');
    remove_action('wp_head', 'wlwmanifest_link');
    remove_action('wp_head', 'wp_oembed_add_discovery_links');
    remove_action('wp_head', 'wp_shortlink_wp_head');
    add_filter('emoji_svg_url', '__return_false');

    add_filter('auto_update_core', '__return_false'); 
    add_filter('auto_update_plugin', '__return_false'); 
    add_filter('auto_update_theme', '__return_false'); 
});

add_action('init', function() {
    add_action('admin_notices', function() {
        $current_screen = get_current_screen();
        if ($current_screen->base !== 'dashboard') {
            return;
        }

        $updates = get_site_transient('update_plugins');
        if (!empty($updates->response)) {
            $ignore_plugins = [
                'webtoffee-gdpr-cookie-consent/webtoffee-gdpr-cookie-consent.php',
                'wordpress-seo-premium/wp-seo-premium.php',
                'ultimate-elementor/ultimate-elementor.php',
                'bdthemes-element-pack/bdthemes-element-pack.php',
                'modern-events-calendar/mec.php'
            ];

            $updates_needed = false;
            foreach ($updates->response as $plugin_file => $plugin_data) {
                if (!in_array($plugin_file, $ignore_plugins)) {
                    $updates_needed = true;
                    break;
                }
            }

            if ($updates_needed) {
                echo '<div style="border: 1px solid #F6C6CA; border-radius: 5px; background-color: #F9D7DA; color: #721C23; padding: 15px; margin: 20px; font-size: 16px;">'
                    . '<h2><strong>Váš redakční systém by mohl těžit z nejnovějších aktualizací</strong></h2>'
                    . '<p>To zajistí jeho bezproblémový chod a přístup k nejnovějším funkcím...</p>'
                    . '</div>';
            }
        }
    });
});

add_filter('rest_authentication_errors', function($result) {
    if (!is_user_logged_in()) {
        return new WP_Error('rest_forbidden', 'REST API is restricted to authenticated users.', array('status' => 401));
    }
    return $result;
});

if (!function_exists('vlozit_script_do_zapati_administrace')) {
    function vlozit_script_do_zapati_administrace() {
        $povolene_domeny = [
            'smart-websites.cz',
            'aramtor.com',
        ];

        $aktualni_domena = $_SERVER['HTTP_HOST'];
        if (!in_array($aktualni_domena, $povolene_domeny)) {
            return;
        }

        echo '<script type="text/javascript">'
            . 'var supportBoxChatId = 2781;'
            . 'var supportBoxChatSecret = "ba9d4c68795805e1987db16bc7f3b1ae";'
            . '</script>'
            . '<script src="https://chat.supportbox.cz/web-chat/entry-point" async defer></script>';
    }
}
add_action('admin_footer', 'vlozit_script_do_zapati_administrace');

add_action('init', function() {
    add_action('admin_menu', 'hide_specific_admin_menu_items', 999);
});

function hide_specific_admin_menu_items() {
    if (!is_admin()) {
        return;
    }
    $current_user = wp_get_current_user();
    if ($current_user->user_login !== 'paveltravnicek') {
        remove_menu_page('branding');
        remove_menu_page('wp-defender');
    }
}

add_filter('user_has_cap', function($allcaps, $caps, $args) {
    if (isset($args[2]) && $args[2] == get_user_by('login', 'paveltravnicek')->ID) {
        if (in_array($args[0], ['delete_users', 'remove_users', 'edit_users'])) {
            $allcaps[$args[0]] = false;
        }
    }
    return $allcaps;
}, 10, 3);

add_action('init', function() {
    add_action('admin_footer', function() {
        $current_user = wp_get_current_user();
        if ($current_user->user_login === 'paveltravnicek') {
            return;
        }
        echo '<script>document.addEventListener("DOMContentLoaded", function() {'
            . 'document.querySelectorAll("#the-list tr").forEach(tr => {'
            . 'if (["Branda Pro", "Defender Pro"].includes(tr.querySelector(".plugin-title strong")?.innerText.trim())) {'
            . 'tr.querySelector(".row-actions")?.style.display = "none";'
            . '}});});</script>';
    });
});

?>
