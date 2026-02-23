<?php
/**
 * Shared functions.php (GitHub) – Smart Websites
 * ------------------------------------------------
 * - Jedno místo pro správu seznamu spravovaných domén (viz sw_get_managed_domains)
 * - Dvě varianty admin noticů dle domény (managed vs. unmanaged)
 * - Vypnutí jazykového přepínače na loginu
 * - Zmenšení nahrávaných obrázků (max 2000 px, zachování poměru)
 * - Upozornění na zastaralou rodičovskou šablonu
 * - Ochrana účtu „paveltravnicek“ (nelze smazat/upravit; skrytí v seznamu)
 * - Skrytí akcí u vybraných pluginů (kromě pro uživatele „paveltravnicek“)
 * - Skrytí položek menu Branda/Defender pro běžné uživatele
 * - Zoho Desk ASAP skript jen na spravovaných doménách
 * - Auto-logout při návštěvě maskované login URL (Defender Pro) – fix 404 pro přihlášené
 */

defined('ABSPATH') || exit;

/** ------------------------------------------------
 * KONFIGURACE: spravované domény (suffixy)
 * Doplňuj/maž zde. Platí pro notifikace i Zoho skript.
 * ------------------------------------------------*/
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
		'zzstar.cz',
	];
}

/** True, pokud host je v seznamu spravovaných domén (bere v potaz i subdomény) */
function sw_domain_is_managed($host) {
	$host = strtolower(wp_unslash($host ?? ''));
	if ($host === '') return false;
	$managed = sw_get_managed_domains();
	foreach ($managed as $suffix) {
		if ($host === $suffix || str_ends_with($host, '.' . $suffix)) {
			return true;
		}
	}
	return false;
}

/** ------------------------------------------------
 * UX / drobnosti
 * ------------------------------------------------*/
add_filter('login_display_language_dropdown', '__return_false');

/** ------------------------------------------------
 * Defender Pro Mask Login – auto logout na maskované URL
 * - řeší 404 na /administrace/ (nebo /prihlaseni/) pokud je uživatel stále přihlášený
 * - chování: přijdu na masku -> jsem přihlášený -> WP logout -> zpět na masku -> zobrazí login
 * ------------------------------------------------*/
add_action('init', function () {

	// Slugy maskovaných login URL (bez lomítek)
	$mask_slugs = ['administrace', 'prihlaseni'];

	$request_uri = $_SERVER['REQUEST_URI'] ?? '';
	$path = parse_url($request_uri, PHP_URL_PATH);
	if (!$path) return;

	// Normalizace cesty: odstraní duplicitní lomítka a koncový lomítko
	$path_norm = rtrim(preg_replace('~/+~', '/', $path), '/');

	$matched_slug = null;
	foreach ($mask_slugs as $slug) {
		$mask_path_norm = '/' . trim($slug, '/');
		if ($path_norm === $mask_path_norm) {
			$matched_slug = $slug;
			break;
		}
	}
	if ($matched_slug === null) return;

	// Pokud už tu běží náš redirect (pojistka proti smyčce kvůli cache/proxy)
	if (isset($_GET['sw_autologout']) && $_GET['sw_autologout'] === '1') {
		return;
	}

	// Jen pokud je uživatel přihlášený
	if (is_user_logged_in()) {

		// Odhlásit přímo (bez wp-login.php?action=logout -> Defender loop)
		wp_logout();

		$redirect_back = home_url('/' . trim($matched_slug, '/') . '/');
		$redirect_back = add_query_arg('sw_autologout', '1', $redirect_back);

		wp_safe_redirect($redirect_back, 302);
		exit;
	}

}, 0);


/** ------------------------------------------------
 * Obrázky: automatické zmenšení velkých souborů
 * ------------------------------------------------*/
add_filter('wp_handle_upload', 'sw_resize_uploaded_image_if_needed');

function sw_resize_uploaded_image_if_needed($upload) {
	$image_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
	$file_type   = wp_check_filetype($upload['file']);

	if (empty($file_type['type']) || !in_array($file_type['type'], $image_types, true)) {
		return $upload;
	}

	$editor = wp_get_image_editor($upload['file']);
	if (is_wp_error($editor)) {
		return $upload;
	}

	$size          = $editor->get_size();
	$width         = isset($size['width']) ? (int) $size['width'] : 0;
	$height        = isset($size['height']) ? (int) $size['height'] : 0;
	$max_dimension = 2000;

	if ($width > $max_dimension || $height > $max_dimension) {
		$editor->resize($max_dimension, $max_dimension, false); // zachová poměr stran
		$editor->save($upload['file']);
	}

	return $upload;
}

/** ------------------------------------------------
 * Admin notice: dle domény (managed vs. unmanaged)
 * - zobrazí se jen na Nástěnce a jen když jsou dostupné aktualizace
 * ------------------------------------------------*/
add_action('admin_notices', function () {
	if (!is_admin()) return;

	$screen = function_exists('get_current_screen') ? get_current_screen() : null;
	if (!$screen || $screen->base !== 'dashboard') return;

	$updates = get_site_transient('update_plugins');
	if (empty($updates) || empty($updates->response) || !is_array($updates->response)) return;

	// Přeskočit vyjmenované pluginy
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

	$host       = $_SERVER['HTTP_HOST'] ?? '';
	$is_managed = sw_domain_is_managed($host);

	if (!$is_managed) {
		// NESPRAVOVANÁ DOMÉNA – upsell
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
		// SPRAVOVANÁ DOMÉNA – uklidnění
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
 * Skrytí řádku akcí u chráněných pluginů (jen pro běžné uživatele)
 * ------------------------------------------------*/
function sw_hide_actions_for_protected_plugins_js() {
	if (!is_admin()) return;

	$current_user = wp_get_current_user();
	if ($current_user && $current_user->user_login === 'paveltravnicek') {
		return;
	}

	$screen = function_exists('get_current_screen') ? get_current_screen() : null;
	if (!$screen || $screen->id !== 'plugins') return;
	?>
	<script>
		document.addEventListener("DOMContentLoaded", function () {
			const protectedNames = ["Branda Pro","Defender Pro"];
			document.querySelectorAll("#the-list tr").forEach(tr => {
				const name = tr.querySelector(".plugin-title strong")?.innerText.trim();
				if (name && protectedNames.includes(name)) {
					const rowActions = tr.querySelector(".row-actions");
					if (rowActions) rowActions.style.display = "none";
				}
			});
		});
	</script>
	<?php
}
add_action('admin_footer', 'sw_hide_actions_for_protected_plugins_js');

/** ------------------------------------------------
 * Ochrana účtu „paveltravnicek“
 * ------------------------------------------------*/
function sw_protect_paveltravnicek_caps($allcaps, $caps, $args) {
	if (!isset($args[0], $args[2])) return $allcaps;

	$target = get_user_by('login', 'paveltravnicek');
	if (!$target) return $allcaps;

	$blocked_caps = ['delete_users','remove_users','edit_users'];
	if ((int) $args[2] === (int) $target->ID && in_array($args[0], $blocked_caps, true)) {
		$allcaps[$args[0]] = false;
	}
	return $allcaps;
}
add_filter('user_has_cap', 'sw_protect_paveltravnicek_caps', 10, 3);

function sw_hide_paveltravnicek_from_users_list($query) {
	if (!is_admin()) return;

	global $pagenow;
	$current = wp_get_current_user();

	if ($pagenow === 'users.php' && $current && $current->user_login !== 'paveltravnicek') {
		$query->query_where .= " AND user_login != 'paveltravnicek'";
	}
}
add_action('pre_user_query', 'sw_hide_paveltravnicek_from_users_list');

/** ------------------------------------------------
 * Skrytí položek admin menu pro běžné uživatele
 * ------------------------------------------------*/
function sw_hide_specific_admin_menu_items() {
	if (!is_admin()) return;

	$current = wp_get_current_user();
	if (!$current) return;

	$whitelist = ['paveltravnicek','lukashulka'];
	if (!in_array($current->user_login, $whitelist, true)) {
		remove_menu_page('branding');    // Branda Pro
		remove_menu_page('wp-defender'); // Defender Pro
	}
}
add_action('admin_menu', 'sw_hide_specific_admin_menu_items', 999);

/** ------------------------------------------------
 * Zoho Desk ASAP – jen na spravovaných doménách
 * ------------------------------------------------*/
function sw_admin_footer_insert_zoho_asap() {
	if (!is_admin()) return;

	$host = $_SERVER['HTTP_HOST'] ?? '';
	if (!sw_domain_is_managed($host)) return;

	?>
	<script type="text/javascript" id="zohodeskasap">
		(function () {
			var d = document;
			var s = d.createElement("script");
			s.type = "text/javascript";
			s.id = "zohodeskasapscript";
			s.defer = true;

			// Volitelně: v wp-config.php definuj nonce: define('SW_ZOHO_NONCE','...');
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
}
add_action('admin_footer', 'sw_admin_footer_insert_zoho_asap');

/** ------------------------------------------------
 * Helper: str_ends_with pro starší PHP
 * ------------------------------------------------*/
if (!function_exists('str_ends_with')) {
	function str_ends_with($haystack, $needle) {
		if ($needle === '') return true;
		$len = strlen($needle);
		return substr($haystack, -$len) === $needle;
	}
}
