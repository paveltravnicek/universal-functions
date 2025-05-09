<?php
add_filter('login_display_language_dropdown', '__return_false');

add_filter('wp_handle_upload', 'resize_uploaded_image_if_needed');

function resize_uploaded_image_if_needed($upload) {
    $image_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $file_type = wp_check_filetype($upload['file']);
    if (!in_array($file_type['type'], $image_types)) {
        return $upload;
    }

    $editor = wp_get_image_editor($upload['file']);
    if (is_wp_error($editor)) {
        return $upload;
    }

    $size = $editor->get_size();
    $width = $size['width'];
    $height = $size['height'];
    $max_dimension = 2000;

    if ($width > $max_dimension || $height > $max_dimension) {
        $editor->resize($max_dimension, $max_dimension, false); // false = zachová poměr stran
        $editor->save($upload['file']);
    }

    return $upload;
}

add_action('admin_notices', function() {
    $current_screen = get_current_screen();
    if ($current_screen->base !== 'dashboard') {
        return;
    }

    $updates = get_site_transient('update_plugins');
    if (!empty($updates->response)) {
        $ignore_plugins = [
            'webtoffee-gdpr-cookie-consent/webtoffee-gdpr-cookie-consent.php',
            'webtoffee-gdpr-cookie-consent/webtoffee-cookie-consent.php',
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
            echo '<div style="border: 1px solid #F6C6CA; border-radius: 5px; background-color: #F9D7DA; color: #721C23; padding: 15px; margin: 20px 20px 20px 0px; font-size: 16px;">
                    <h2 style="font-size: 18px; color: #721C23;"><strong>Váš redakční systém by mohl těžit z nejnovějších aktualizací</strong></h2>
                    <p>To zajistí jeho bezproblémový chod a přístup k nejnovějším funkcím. Máte několik možností, jak to zařídit:</p>
                    <p><strong>Nechte to na nás:</strong><br>Objednejte si naši službu <a href="https://smart-websites.cz/levne-webove-stranky/sprava-webovych-stranek/" target="_blank" style="color:#AF2279;">Správa webu</a> a my se o aktualizace a bezpečí vašeho webu postaráme kompletně za vás. Získáte tak bezstarostný provoz a vždy aktuální systém.</p>
                    <p><strong>Aktualizujte si systém sami:</strong><br>Pokud máte administrátorský přístup, můžete aktualizace provést svépomocí. Po aktualizaci doporučujeme důkladně otestovat funkčnost webu.</p>
                    <p><strong>Již máte Správu webu?</strong><br>V tom případě o potřebných aktualizacích již víme a pracujeme na jejich co nejrychlejší implementaci. Děkujeme za trpělivost.</p>
                    <p>V případě jakýchkoliv dotazů se <a href="https://smart-websites.cz/kontakt/" target="_blank" style="color:#AF2279;">neváhejte na nás obrátit</a>.</p>
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


function skryt_radek_akci_pro_chranene_pluginy() {
    $current_user = wp_get_current_user();
    
    if ($current_user->user_login === 'paveltravnicek') {
        return;
    }

    ?>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const chranenePluginy = [
                "Branda Pro",
                "Defender Pro"
            ];
            
            document.querySelectorAll("#the-list tr").forEach(tr => {
                let pluginName = tr.querySelector(".plugin-title strong")?.innerText.trim();
                
                if (pluginName && chranenePluginy.includes(pluginName)) {
                    let rowActions = tr.querySelector(".row-actions");
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


function hide_specific_admin_menu_items() {
    if (!is_admin()) {
        return;
    }
    
    $current_user = wp_get_current_user();
    
    if ($current_user->user_login !== 'paveltravnicek' && $current_user->user_login !== 'lukashulka') {
        remove_menu_page('branding'); // Branda Pro
        remove_menu_page('wp-defender'); // Defender Pro
    }
}
add_action('admin_menu', 'hide_specific_admin_menu_items', 999);


function vlozit_script_do_zapati_administrace() {
    $povolene_domeny = [
        'smart-websites.cz',
        'a2development.cz',
        'aramtor.com',
        'busplanservis.cz',
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
        'privacychoices.eu',
        'vitalplan.cz',
        'zzstar.cz',
    ];

    $aktualni_domena = $_SERVER['HTTP_HOST'];

    if (!in_array($aktualni_domena, $povolene_domeny)) {
        return;
    }

    ?>
    <script type="text/javascript" id="zohodeskasap">
        var d = document;
        var s = d.createElement("script");
        s.type = "text/javascript";
        s.id = "zohodeskasapscript";
        s.defer = true;
        s.nonce = "{place_your_nonce_value_here}"; // Nahraď skutečnou nonce hodnotou nebo tento řádek smaž
        s.src = "https://desk.zoho.eu/portal/api/web/asapApp/197085000000339427?orgId=20105462640";
        var t = d.getElementsByTagName("script")[0];
        t.parentNode.insertBefore(s, t);

        window.ZohoDeskAsapReady = function(callback) {
            var queue = window.ZohoDeskAsap__asyncalls = window.ZohoDeskAsap__asyncalls || [];
            if (window.ZohoDeskAsapReadyStatus) {
                if (callback) queue.push(callback);
                queue.forEach(fn => fn && fn());
                window.ZohoDeskAsap__asyncalls = null;
            } else if (callback) {
                queue.push(callback);
            }
        };
    </script>
    <?php
}

add_action('admin_footer', 'vlozit_script_do_zapati_administrace');
?>
