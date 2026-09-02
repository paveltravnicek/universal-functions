<?php
/**
 * Shared functions.php (GitHub) – Smart Websites
 * ------------------------------------------------
 * Verzi níž zvyš při každém pushi. Zobrazuje se v HTML komentáři
 * na konci souboru, takže na kterémkoli webu poznáš, co tam běží.
 * ------------------------------------------------
 * Změny oproti předchozí verzi:
 * - PŘIDÁNO: kanonické adresy (rel=canonical) – SmartCrawl je jádru odebírá a nedodává vlastní
 * - Login mask: slug se čte z Defenderu, fallback na seznam; ošetřen CSRF na auto-logout
 * - Ochrana pluginů: skutečná (server-side), ne skrývání odkazů JavaScriptem
 * - Polyfill str_ends_with přesunut na začátek souboru
 * - Oprava značky proti dvojímu vložení podpisu u textových e-mailů
 * ------------------------------------------------
 */

defined('ABSPATH') || exit;

define('SW_SHARED_VERSION', '2026-09-02.2');


/** ------------------------------------------------
 * Helper: str_ends_with pro starší PHP
 * MUSÍ být před prvním použitím v sw_domain_is_managed()
 * ------------------------------------------------*/
if (!function_exists('str_ends_with')) {
	function str_ends_with($haystack, $needle) {
		if ($needle === '') return true;
		$len = strlen($needle);
		return substr($haystack, -$len) === $needle;
	}
}


/** ------------------------------------------------
 * KONFIGURACE
 * ------------------------------------------------*/

/** Domény se službou Správa webu (ostatní mají jen webhosting). */
function sw_get_managed_domains() {
	return [
		'smart-websites.cz',
		'a2development.cz',
		'aramtor.com',
		'busplanservis.cz',
		'ciraa.eu',
		'cirkularniakademie.cz',
		'crystaldent.cz',
		'daliborsitavanc.cz',
		'dispecer.cz',
		'fyziologickytrenink.cz',
		'guide-jana-zemanova.com',
		'jbbabylon.cz',
		'katerinabakulova.cz',
		'koeltechniek.cz',
		'localdisti.cz',
		'megasrot.cz',
		'oldtimersebek.cz',
		'orangespa.cz',
		'podlahy-mareon.cz',
		'podovi-mareon.hr',
		'privacychoices.eu',
		'spolecne-udrzitelne.cz',
		'vitalplan.cz',
		'zamecnikvpraze.cz',
		'zzstar.cz',
	];
}

/** Uživatelé s plným přístupem (ochrana účtu, skryté pluginy, skrytá média). */
function sw_get_superadmin_logins() {
	return ['paveltravnicek'];
}

/** Uživatelé, kteří smějí vidět menu Branda / Defender. */
function sw_get_admin_menu_whitelist() {
	return ['paveltravnicek', 'lukashulka'];
}

/** Pluginy, které nesmí běžný uživatel deaktivovat ani smazat (podle názvu). */
function sw_get_protected_plugin_names() {
	return ['Branda Pro', 'Defender Pro'];
}

/** True, pokud host je v seznamu spravovaných domén (bere v potaz i subdomény). */
function sw_domain_is_managed($host) {
	$host = strtolower(wp_unslash($host ?? ''));
	if ($host === '') return false;

	foreach (sw_get_managed_domains() as $suffix) {
		if ($host === $suffix || str_ends_with($host, '.' . $suffix)) {
			return true;
		}
	}
	return false;
}

/** True, pokud je přihlášený uživatel na seznamu superadminů. */
function sw_current_user_is_superadmin() {
	$user = wp_get_current_user();
	return $user && $user->exists() && in_array($user->user_login, sw_get_superadmin_logins(), true);
}


/** ------------------------------------------------
 * KANONICKÉ ADRESY (rel=canonical)
 * ------------------------------------------------
 * Proč to tu je: SmartCrawl odebere WP jádru funkci rel_canonical() a vlastní
 * značku vloží jen tam, kde je ručně vyplněná v poli u příspěvku. Většina
 * stránek pak zůstane bez kanonické značky úplně a homepage vždycky, protože
 * tam ji jádro nevkládá ani samo.
 *
 * Značka se vloží jen tehdy, když ji do <head> nedal už někdo jiný.
 *
 * Vypnutí na konkrétním webu:  add_filter('sw_canonical_enabled', '__return_false');
 * Přepsání adresy:             add_filter('sw_canonical_url', function ($url) { ... });
 * ------------------------------------------------*/

/** Nepleteme se do cesty pluginům, které canonical řeší samy a dobře. */
function sw_canonical_is_enabled() {

	if ( defined('WPSEO_VERSION')       // Yoast
	  || defined('SEOPRESS_VERSION')    // SEOPress
	  || defined('AIOSEO_VERSION')      // All in One SEO
	  || class_exists('RankMath') ) {   // Rank Math
		return false;
	}

	return (bool) apply_filters('sw_canonical_enabled', true);
}

/**
 * Sestaví kanonickou adresu pro aktuální zobrazení.
 * Žádná větev nepracuje s parametry dotazu, takže ?orderby=, ?filter_ i UTM
 * parametry se kanonizují na čistou adresu. To je u WooCommerce zásadní –
 * jinak by každá kombinace filtrů vznikla jako samostatná kanonická adresa.
 */
function sw_get_canonical_url() {

	global $wp;

	if ( is_singular() ) {
		$url = get_permalink();

	} elseif ( function_exists('is_shop') && is_shop() ) {
		$url = wc_get_page_permalink('shop');

	} elseif ( is_tax() || is_category() || is_tag() ) {
		$term = get_queried_object();
		$url  = ( $term && ! is_wp_error($term) ) ? get_term_link($term) : '';

	} elseif ( is_post_type_archive() ) {
		$post_type = get_query_var('post_type');
		if ( is_array($post_type) ) {
			$post_type = reset($post_type);
		}
		$url = $post_type ? get_post_type_archive_link($post_type) : '';

	} elseif ( is_front_page() ) {
		$url = home_url('/');

	} elseif ( is_home() ) {
		$page_for_posts = (int) get_option('page_for_posts');
		$url = $page_for_posts ? get_permalink($page_for_posts) : home_url('/');

	} elseif ( is_author() ) {
		$author = get_queried_object();
		$url = ( $author && ! is_wp_error($author) ) ? get_author_posts_url($author->ID) : '';

	} elseif ( is_date() ) {
		$url = ''; // archivy podle data kanonizovat nechceme

	} else {
		$url = home_url( user_trailingslashit( $wp->request ) );
	}

	if ( ! $url || is_wp_error($url) ) {
		return '';
	}

	// Stránkování archivů: /page/2/ musí kanonizovat samo na sebe, ne na první stránku
	$paged = (int) get_query_var('paged');
	if ( $paged > 1 && ! is_singular() ) {
		$url = trailingslashit($url) . user_trailingslashit('page/' . $paged, 'paged');
	}

	return apply_filters('sw_canonical_url', $url);
}

/**
 * Vložení značky do <head>.
 *
 * Zachytíme výstup wp_head do bufferu a značku doplníme, jen když ji tam
 * nikdo jiný nedal. Dřívější test na has_action('wp_head', 'rel_canonical')
 * poznal jen jádro WordPressu, ne SEO plugin, který jádru funkci odebere
 * a vloží vlastní značku – na takovém webu pak vznikly značky dvě.
 *
 * Pokud buffer mezitím zavře jiný plugin, funkce raději neudělá nic,
 * než aby rozbila výstup stránky.
 */
add_action('wp_head', function () {

	if ( ! sw_canonical_is_enabled() ) {
		return;
	}

	$GLOBALS['sw_canonical_ob_level'] = ob_get_level();
	ob_start();

}, 0);

add_action('wp_head', function () {

	if ( ! sw_canonical_is_enabled() || ! isset($GLOBALS['sw_canonical_ob_level']) ) {
		return;
	}

	// Buffer už není náš – nesaháme na to
	if ( ob_get_level() <= $GLOBALS['sw_canonical_ob_level'] ) {
		unset($GLOBALS['sw_canonical_ob_level']);
		return;
	}

	$head = ob_get_clean();
	unset($GLOBALS['sw_canonical_ob_level']);

	echo $head;

	// Kde kanonická značka nedává smysl
	if ( is_404() || is_search() || is_feed() || is_preview() ) {
		return;
	}

	// Značku už někdo vložil – jádro, SmartCrawl nebo cokoli jiného
	if ( stripos($head, 'rel="canonical"') !== false
	  || stripos($head, "rel='canonical'") !== false ) {
		return;
	}

	$url = sw_get_canonical_url();

	if ( $url ) {
		echo '<link rel="canonical" href="' . esc_url($url) . '">' . "\n";
	}

}, 999);


/** ------------------------------------------------
 * UX / drobnosti
 * ------------------------------------------------*/
add_filter('login_display_language_dropdown', '__return_false');


/** ------------------------------------------------
 * Informace o původu odchozího e-mailu
 * ------------------------------------------------*/
add_filter('wp_mail', 'sw_add_origin_site_to_emails', 20);

function sw_add_origin_site_to_emails($args) {

	if ( empty($args['message']) ) {
		return $args;
	}

	// Pojistka proti dvojímu vložení – platí pro HTML i textovou větev
	if ( strpos($args['message'], 'X-Origin-Site-Marker') !== false ) {
		return $args;
	}

	$site_name = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
	$site_url  = home_url('/');
	$host      = wp_parse_url($site_url, PHP_URL_HOST);

	if (!$host) {
		$host = $_SERVER['HTTP_HOST'] ?? '';
	}

	// Hlavičky do pole
	if (empty($args['headers'])) {
		$args['headers'] = [];
	}
	if (is_string($args['headers'])) {
		$args['headers'] = preg_split("/\r\n|\r|\n/", $args['headers']);
	}

	// Je e-mail HTML?
	$is_html = false;
	if (is_array($args['headers'])) {
		foreach ($args['headers'] as $header) {
			if (stripos($header, 'Content-Type: text/html') !== false) {
				$is_html = true;
				break;
			}
		}
	}
	if (!$is_html && $args['message'] !== wp_strip_all_tags($args['message'])) {
		$is_html = true;
	}

	if (is_array($args['headers'])) {
		$args['headers'][] = 'X-Origin-Site: ' . $site_name . (!empty($host) ? ' | ' . $host : '');
	}

	if ($is_html) {
		$label  = '<div style="text-align:center;color:#ffffff;background:#222222;padding:10px 0;font-size:12px;">';
		$label .= 'Odesláno z webu: <strong>' . esc_html($site_name) . '</strong>';
		if (!empty($host)) {
			$label .= ' (<a href="' . esc_url($site_url) . '" style="color:#ffffff;">' . esc_html($host) . '</a>)';
		}
		$label .= '</div>';

		$args['message'] .= "\n<!-- X-Origin-Site-Marker -->\n" . $label;

	} else {
		$label  = "\n\n---\nOdesláno z webu: {$site_name}";
		if (!empty($host)) {
			$label .= " ({$site_url})";
		}
		$label .= "\n[X-Origin-Site-Marker]";

		$args['message'] .= $label;
	}

	return $args;
}


/** ------------------------------------------------
 * Defender Pro Mask Login – auto logout na maskované URL
 * ------------------------------------------------
 * Chování: přihlášený uživatel jde na maskovanou login URL -> odhlásíme ho
 * -> vrátíme zpět na masku -> zobrazí se přihlašovací formulář.
 *
 * Nově:
 * - slug se přednostně čte z nastavení Defenderu, seznam níž je fallback
 * - odhlášení proběhne jen při skutečné navigaci prohlížeče (Sec-Fetch-Dest),
 *   takže admina nejde odhlásit podstrčeným <img src="/administrace/">
 * ------------------------------------------------*/

/** Fallback seznam, když se masku z Defenderu vytáhnout nepodaří. */
function sw_get_login_mask_fallback_slugs() {
	return ['administrace', 'prihlaseni'];
}

/**
 * Vrátí slugy maskovaných login URL.
 * Čtení z Defenderu je best-effort – název volby se mezi verzemi mění,
 * proto zkoušíme víc míst a vždycky přidáme fallback.
 */
function sw_get_login_mask_slugs() {

	$slugs = [];

	$candidates = [
		get_option('wd_masklogin_settings'),
		get_site_option('wd_masklogin_settings'),
	];

	foreach ($candidates as $raw) {
		if (is_string($raw) && $raw !== '') {
			$raw = json_decode($raw, true);
		}
		if (is_array($raw) && !empty($raw['mask_url'])) {
			$slugs[] = trim((string) $raw['mask_url'], '/');
		}
	}

	$slugs = array_merge($slugs, sw_get_login_mask_fallback_slugs());
	$slugs = array_filter(array_unique(array_map('strtolower', $slugs)));

	return apply_filters('sw_login_mask_slugs', array_values($slugs));
}

/**
 * True jen pro skutečnou navigaci prohlížeče.
 * <img>, <iframe> a fetch() posílají jiný Sec-Fetch-Dest, takže se
 * na nich auto-logout nespustí. Když hlavička chybí (starý prohlížeč,
 * proxy), povolíme – jinak by funkce přestala fungovat.
 */
function sw_is_top_level_navigation() {
	$dest = $_SERVER['HTTP_SEC_FETCH_DEST'] ?? '';
	if ($dest === '') {
		return true;
	}
	return $dest === 'document';
}

add_action('init', function () {

	// Přepínání uživatelů (User Switching) – nikdy neshazovat session
	$us_action = $_REQUEST['action'] ?? '';
	if (in_array($us_action, ['switch_to_user', 'switch_to_olduser', 'switch_off'], true)) {
		return;
	}

	$path = wp_parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
	if (!$path) {
		return;
	}

	$path_norm = rtrim(preg_replace('~/+~', '/', $path), '/');

	$matched_slug = null;
	foreach (sw_get_login_mask_slugs() as $slug) {
		if ($path_norm === '/' . trim($slug, '/')) {
			$matched_slug = $slug;
			break;
		}
	}
	if ($matched_slug === null) {
		return;
	}

	// Pojistka proti smyčce
	if (isset($_GET['sw_autologout']) && $_GET['sw_autologout'] === '1') {
		return;
	}

	if (!is_user_logged_in()) {
		return;
	}

	// Jen na skutečnou navigaci, ne na podstrčený obrázek nebo iframe
	if (!sw_is_top_level_navigation()) {
		return;
	}

	// Uživatel je "switched" – neodhlašovat, jen poslat do administrace
	if (function_exists('current_user_switched') && current_user_switched()) {
		wp_safe_redirect(admin_url(), 302);
		exit;
	}

	wp_logout();

	$redirect_back = add_query_arg(
		'sw_autologout',
		'1',
		home_url('/' . trim($matched_slug, '/') . '/')
	);

	wp_safe_redirect($redirect_back, 302);
	exit;

}, 0);


/** ------------------------------------------------
 * Média: možnost skrýt vybraná média z knihovny
 * ------------------------------------------------*/
function sw_hidden_media_library() {

	add_filter('attachment_fields_to_edit', static function ($form_fields, $post) {
		$value = get_post_meta($post->ID, '_sw_hidden_media', true);

		$form_fields['sw_hidden_media'] = [
			'label' => 'Skryté médium',
			'input' => 'html',
			'html'  => '<label style="display:flex;align-items:center;gap:8px;">'
				. '<input type="checkbox" name="attachments[' . (int) $post->ID . '][sw_hidden_media]" value="1" ' . checked($value, '1', false) . ' />'
				. '<span>Skrýt z knihovny médií</span>'
				. '</label>',
			'helps' => 'Skryté médium nebude viditelné v knihovně médií ani ve výběru médií pro běžné uživatele.',
		];

		return $form_fields;
	}, 10, 2);

	add_filter('attachment_fields_to_save', static function ($post, $attachment) {
		if (isset($attachment['sw_hidden_media']) && (int) $attachment['sw_hidden_media'] === 1) {
			update_post_meta($post['ID'], '_sw_hidden_media', '1');
		} else {
			delete_post_meta($post['ID'], '_sw_hidden_media');
		}
		return $post;
	}, 10, 2);

	$hidden_meta_query = static function () {
		return [
			'relation' => 'OR',
			[ 'key' => '_sw_hidden_media', 'compare' => 'NOT EXISTS' ],
			[ 'key' => '_sw_hidden_media', 'value' => '1', 'compare' => '!=' ],
		];
	};

	add_filter('ajax_query_attachments_args', static function ($query) use ($hidden_meta_query) {
		if (sw_current_user_is_superadmin()) {
			return $query;
		}

		$meta_query   = isset($query['meta_query']) && is_array($query['meta_query']) ? $query['meta_query'] : [];
		$meta_query[] = $hidden_meta_query();
		$query['meta_query'] = $meta_query;

		return $query;
	});

	add_action('pre_get_posts', static function ($query) use ($hidden_meta_query) {
		if (!is_admin() || !$query->is_main_query()) {
			return;
		}

		global $pagenow;
		if ($pagenow !== 'upload.php' || sw_current_user_is_superadmin()) {
			return;
		}

		$meta_query   = $query->get('meta_query');
		$meta_query   = is_array($meta_query) ? $meta_query : [];
		$meta_query[] = $hidden_meta_query();

		$query->set('meta_query', $meta_query);
	});
}
sw_hidden_media_library();


/** ------------------------------------------------
 * Admin notice: dle domény (se správou vs. jen webhosting)
 * - jen na Nástěnce a jen když jsou dostupné aktualizace
 * ------------------------------------------------*/
add_action('admin_notices', function () {
	if (!is_admin()) return;

	$screen = function_exists('get_current_screen') ? get_current_screen() : null;
	if (!$screen || $screen->base !== 'dashboard') return;

	$updates = get_site_transient('update_plugins');
	if (empty($updates) || empty($updates->response) || !is_array($updates->response)) return;

	$ignore_plugins = [
		'webtoffee-gdpr-cookie-consent/webtoffee-gdpr-cookie-consent.php',
		'webtoffee-gdpr-cookie-consent/webtoffee-cookie-consent.php',
		'webtoffee-cookie-consent/webtoffee-cookie-consent.php',
		'wordpress-seo-premium/wp-seo-premium.php',
		'ultimate-elementor/ultimate-elementor.php',
		'bdthemes-element-pack/bdthemes-element-pack.php',
		'modern-events-calendar/mec.php',
		'polylang-pro/polylang.php',
		'responsive-menu-pro/responsive-menu-pro.php',
		'js_composer/js_composer.php',
		'ts-visual-composer-extend/ts-visual-composer-extend.php',
	];

	$updates_needed = false;
	foreach ($updates->response as $plugin_file => $plugin_data) {
		if (!in_array($plugin_file, $ignore_plugins, true)) {
			$updates_needed = true;
			break;
		}
	}
	if (!$updates_needed) return;

	if (!sw_domain_is_managed($_SERVER['HTTP_HOST'] ?? '')) {
		?>
		<div class="notice notice-warning" style="border-left-color:#AF2279;padding:16px 20px;">
			<h2 style="margin:0 0 12px;font-size:18px;line-height:1.4;">Váš web čekají důležité aktualizace</h2>
			<p style="margin:0 0 12px;">
				<strong>Pravidelné aktualizace</strong> zvyšují <strong>bezpečnost</strong>, udržují <strong>kompatibilitu</strong> s pluginy a šablonami
				a často zlepšují <strong>rychlost</strong> i přinášejí <strong>nové funkce</strong>.
			</p>

			<ul style="margin:0 0 16px 18px;list-style:disc;">
				<li><strong>Chcete to bez starostí?</strong><br>
					Objednejte si naši službu <strong>Správa webu</strong> a my se postaráme o <strong>aktualizace, zálohy i dohled</strong> za vás.
					<br><em>K Vašemu stávajícímu webhostingu navíc získáte Memcached nebo Redis, zálohy až 50 dní zpětně,
					pravidelné aktualizace WordPressu, prioritní technickou podporu a AntiBot Global Firewall.</em>
				</li>
				<li><strong>Uděláte si to sami?</strong><br>
					Aktualizace můžete spustit jako administrátor buď v <em>Pluginy → Aktualizace</em> (pro konkrétní pluginy),
					nebo přes <em>Nástěnka → Aktualizace</em> (kompletní seznam dostupných aktualizací pro <strong>WordPress</strong>, pluginy i šablony).
					<br>Po dokončení vždy doporučujeme <strong>důkladně otestovat funkčnost webu</strong>.
				</li>
			</ul>

			<p style="margin:0 0 8px;">
				<a href="https://form.simpleshop.cz/5Q3g8/buy/" target="_blank" rel="noopener" class="button button-primary" style="background:#AF2279;border-color:#AF2279;">
					Objednat Správu webu
				</a>
				&nbsp;&nbsp;
				<a href="https://smart-websites.cz/kontakt/" target="_blank" rel="noopener" class="button">
					Potřebujete poradit?
				</a>
			</p>
		</div>
		<?php
	} else {
		?>
		<div class="notice notice-info" style="padding:16px 20px;">
			<h2 style="margin:0 0 12px;font-size:18px;line-height:1.4;">Aktualizace webu jsou pod kontrolou</h2>
			<p style="margin:0 0 12px;">
				Máte aktivní službu <strong>Správa webu</strong>. O dostupných aktualizacích víme a provedeme je v rámci našich procesů
				(záloha, aktualizace, kontrola funkčnosti). Není potřeba žádná akce z vaší strany.
			</p>
			<p style="margin:0;">
				Pokud je na webu něco <strong>urgentního</strong> nebo plánujete větší změny,
				<a href="https://smart-websites.cz/kontakt/" target="_blank" rel="noopener">dejte nám vědět</a>.
			</p>
		</div>
		<?php
	}
});


/** ------------------------------------------------
 * Admin notice: stará rodičovská šablona
 * ------------------------------------------------*/
add_action('all_admin_notices', function () {
	if (!function_exists('pico_get_parent_theme_version')) return;

	$version = (float) pico_get_parent_theme_version();
	if ($version >= 3.0) return;

	?>
	<div class="notice notice-error" style="padding:16px 20px;">
		<strong>Rodičovská šablona je zastaralá.</strong><br>
		Pro bezpečnost a kompatibilitu ji prosím aktualizujte. Pokud si nejste jisti postupem,
		<a href="https://smart-websites.cz/kontakt/" target="_blank" rel="noopener">kontaktujte správce webu</a>.
	</div>
	<?php
});


/** ------------------------------------------------
 * Ochrana vybraných pluginů před deaktivací a smazáním
 * ------------------------------------------------
 * Dřív se odkazy jen skrývaly JavaScriptem, což šlo obejít vypnutím JS
 * nebo zavoláním deaktivační URL přímo. Teď se blokuje na serveru.
 * ------------------------------------------------*/

/** Přeloží názvy chráněných pluginů na jejich soubory (branda-pro/branda.php apod.). */
function sw_get_protected_plugin_files() {

	if (!function_exists('get_plugins')) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	$protected = [];
	$names     = sw_get_protected_plugin_names();

	foreach (get_plugins() as $file => $data) {
		if (in_array($data['Name'] ?? '', $names, true)) {
			$protected[] = $file;
		}
	}

	return $protected;
}

/** 1) Odebrání odkazů ze seznamu pluginů – server-side, ne JS. */
add_filter('plugin_action_links', function ($actions, $plugin_file) {

	if (sw_current_user_is_superadmin()) {
		return $actions;
	}

	if (in_array($plugin_file, sw_get_protected_plugin_files(), true)) {
		unset($actions['deactivate'], $actions['activate'], $actions['delete']);
		$actions['sw-protected'] = '<span style="color:#777;">Spravuje správce webu</span>';
	}

	return $actions;

}, 10, 2);

/** 2) Blokace samotné akce, i kdyby ji někdo zavolal přímo URL. */
add_action('load-plugins.php', function () {

	if (sw_current_user_is_superadmin()) {
		return;
	}

	$action = $_REQUEST['action'] ?? '';
	$watched = [
		'deactivate', 'deactivate-selected',
		'activate', 'activate-selected',
		'delete-selected',
	];

	if (!in_array($action, $watched, true)) {
		return;
	}

	$targets = [];
	if (!empty($_REQUEST['plugin'])) {
		$targets[] = wp_unslash($_REQUEST['plugin']);
	}
	if (!empty($_REQUEST['checked']) && is_array($_REQUEST['checked'])) {
		$targets = array_merge($targets, array_map('wp_unslash', $_REQUEST['checked']));
	}

	$protected = sw_get_protected_plugin_files();

	foreach ($targets as $target) {
		if (in_array($target, $protected, true)) {
			wp_die(
				'<h1>Akce není povolena</h1>'
				. '<p>Tento plugin je součástí správy webu a nelze ho deaktivovat ani smazat. '
				. 'Pokud to potřebujete, <a href="https://smart-websites.cz/kontakt/" target="_blank" rel="noopener">ozvěte se nám</a>.</p>',
				'Akce není povolena',
				['response' => 403, 'back_link' => true]
			);
		}
	}
});


/** ------------------------------------------------
 * Ochrana účtu správce
 * ------------------------------------------------*/
add_filter('user_has_cap', function ($allcaps, $caps, $args) {

	if (!isset($args[0], $args[2])) return $allcaps;

	$blocked_caps = ['delete_users', 'remove_users', 'edit_users'];
	if (!in_array($args[0], $blocked_caps, true)) {
		return $allcaps;
	}

	foreach (sw_get_superadmin_logins() as $login) {
		$target = get_user_by('login', $login);
		if ($target && (int) $args[2] === (int) $target->ID) {
			$allcaps[$args[0]] = false;
			break;
		}
	}

	return $allcaps;

}, 10, 3);

add_action('pre_user_query', function ($query) {

	if (!is_admin()) return;

	global $pagenow;
	if ($pagenow !== 'users.php' || sw_current_user_is_superadmin()) {
		return;
	}

	global $wpdb;
	foreach (sw_get_superadmin_logins() as $login) {
		$query->query_where .= $wpdb->prepare(' AND user_login != %s', $login);
	}
});


/** ------------------------------------------------
 * Skrytí položek admin menu pro běžné uživatele
 * ------------------------------------------------*/
add_action('admin_menu', function () {

	if (!is_admin()) return;

	$current = wp_get_current_user();
	if (!$current || !$current->exists()) return;

	if (!in_array($current->user_login, sw_get_admin_menu_whitelist(), true)) {
		remove_menu_page('branding');    // Branda Pro
		remove_menu_page('wp-defender'); // Defender Pro
	}

}, 999);


/** ------------------------------------------------
 * Zoho Desk ASAP – jen na doménách se Správou webu
 * ------------------------------------------------*/
add_action('admin_footer', function () {

	if (!is_admin()) return;
	if (!sw_domain_is_managed($_SERVER['HTTP_HOST'] ?? '')) return;

	?>
	<script type="text/javascript" id="zohodeskasap">
		(function () {
			var d = document;
			var s = d.createElement("script");
			s.type = "text/javascript";
			s.id = "zohodeskasapscript";
			s.defer = true;

			<?php if (defined('SW_ZOHO_NONCE') && SW_ZOHO_NONCE) : ?>
			s.nonce = "<?php echo esc_js(SW_ZOHO_NONCE); ?>";
			<?php endif; ?>

			s.src = "https://desk.zoho.eu/portal/api/web/asapApp/197085000000339427?orgId=20105462640";
			var t = d.getElementsByTagName("script")[0];
			t.parentNode.insertBefore(s, t);

			window.ZohoDeskAsapReady = function (callback) {
				var queue = window.ZohoDeskAsap__asyncalls = window.ZohoDeskAsap__asyncalls || [];
				if (window.ZohoDeskAsapReadyStatus) {
					if (callback) queue.push(callback);
					queue.forEach(function (fn) { if (fn) fn(); });
					window.ZohoDeskAsap__asyncalls = null;
				} else if (callback) {
					queue.push(callback);
				}
			};
		})();
	</script>
	<?php
});


/** ------------------------------------------------
 * Verze a stav ve zdrojovém kódu – jen pro přihlášeného správce
 * ------------------------------------------------
 * Návštěvník ani nepřihlášený crawler tenhle komentář nikdy neuvidí.
 * Slouží k rychlé kontrole, jaká verze na webu běží a jestli se
 * kanonické adresy vůbec generují.
 *
 * Patičku administrace (update_footer) přepisuje Branda Pro,
 * proto je verze tady a ne tam.
 * ------------------------------------------------*/
add_action('wp_head', function () {

	if (!current_user_can('manage_options')) {
		return;
	}

	$meta = get_option('swsfl_meta', []);
	$hash = is_array($meta) ? substr((string) ($meta['active_hash'] ?? ''), 0, 8) : '';

	printf(
		"\n<!-- SW shared %s | hash %s | canonical %s -->\n",
		esc_html(SW_SHARED_VERSION),
		esc_html($hash !== '' ? $hash : '?'),
		sw_canonical_is_enabled() ? 'on' : 'off'
	);

}, 1000);
