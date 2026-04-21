<?php
/**
 * Shared functions.php (GitHub) – Smart Websites
 * ------------------------------------------------
 * - Jedno místo pro správu seznamu spravovaných domén (viz sw_get_managed_domains)
 * - Dvě varianty admin noticů dle domény (managed vs. unmanaged)
 * - Vypnutí jazykového přepínače na loginu
 * - Skrytí vybraných médií z knihovny médií
 * - Upozornění na zastaralou rodičovskou šablonu
 * - Ochrana účtu „paveltravnicek“ (nelze smazat/upravit; skrytí v seznamu)
 * - Skrytí akcí u vybraných pluginů (kromě pro uživatele „paveltravnicek“)
 * - Skrytí položek menu Branda/Defender pro běžné uživatele
 * - Zoho Desk ASAP skript jen na spravovaných doménách
 * - Auto-logout při návštěvě maskované login URL (Defender Pro) – fix 404 pro přihlášené
 * - Přidání informace o původu odchozího e-mailu do wp_mail()
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
		'zamecnikvpraze.cz',
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
 * Přidání informace o původu odchozího e-mailu
 * ------------------------------------------------*/
/**
 * Přidá do všech odchozích e-mailů informaci, ze kterého webu byly odeslány.
 * Funguje pro většinu mailů posílaných přes wp_mail().
 */
add_filter('wp_mail', 'sw_add_origin_site_to_emails', 20);

function sw_add_origin_site_to_emails($args) {

	$site_name = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
	$site_url  = home_url('/');
	$host      = wp_parse_url($site_url, PHP_URL_HOST);

	if (!$host) {
		$host = $_SERVER['HTTP_HOST'] ?? '';
	}

	$label_plain = "\n\n---\nOdesláno z webu: {$site_name}";
	if (!empty($host)) {
		$label_plain .= " ({$host})";
	}

	$label_html  = '<div style="text-align:center;color:#ffffff;background:#222222;padding:10px 0;font-size:12px;">';
	$label_html .= 'Odesláno z webu: <strong>' . esc_html($site_name) . '</strong>';

	if (!empty($host)) {
		$label_html .= ' (' . esc_html($host) . ')';
	}

	$label_html .= '</div>';

	// Přidání vlastní technické hlavičky
	if (empty($args['headers'])) {
		$args['headers'] = [];
	}

	if (is_string($args['headers'])) {
		$args['headers'] = preg_split("/\r\n|\r|\n/", $args['headers']);
	}

	if (is_array($args['headers'])) {
		$args['headers'][] = 'X-Origin-Site: ' . $site_name . (!empty($host) ? ' | ' . $host : '');
	}

	// Zjistíme, jestli je e-mail HTML
	$is_html = false;

	if (!empty($args['headers']) && is_array($args['headers'])) {
		foreach ($args['headers'] as $header) {
			if (stripos($header, 'Content-Type: text/html') !== false) {
				$is_html = true;
				break;
			}
		}
	}

	// Když není explicitně HTML hlavička, zkusíme odhadnout podle obsahu
	if (!$is_html && isset($args['message']) && $args['message'] !== wp_strip_all_tags($args['message'])) {
		$is_html = true;
	}

	// Aby se text nepřidal víckrát
	if (strpos($args['message'], 'X-Origin-Site-Marker') !== false) {
		return $args;
	}

	if ($is_html) {
		$args['message'] .= "\n<!-- X-Origin-Site-Marker -->\n" . $label_html;
	} else {
		$args['message'] .= $label_plain;
	}

	return $args;
}

/** ------------------------------------------------
 * Defender Pro Mask Login – auto logout na maskované URL
 * + kompatibilita s pluginem User Switching
 *
 * - maska: /administrace/ nebo /prihlaseni/
 * - běžně: jsem přihlášený -> wp_logout() -> zpět na masku -> zobrazí login
 * - pokud probíhá user switching request -> NIC nedělat (jinak se rozbije switch)
 * - pokud jsem "switched" -> NEodhlašovat, jen přesměrovat do /wp-admin/
 * ------------------------------------------------*/
add_action('init', function () {

	// 1) Pokud probíhá přepínání uživatelů (User Switching), tak NIKDY neshazovat session.
	$us_action = $_REQUEST['action'] ?? '';
	if (in_array($us_action, ['switch_to_user', 'switch_to_olduser', 'switch_off'], true)) {
		return;
	}

	// Slugy maskovaných login URL (bez lomítek)
	$mask_slugs = ['administrace', 'prihlaseni'];

	$request_uri = $_SERVER['REQUEST_URI'] ?? '';
	$path = parse_url($request_uri, PHP_URL_PATH);
	if (!$path) {
		return;
	}

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
	if ($matched_slug === null) {
		return;
	}

	// Pojistka proti smyčce
	if (isset($_GET['sw_autologout']) && $_GET['sw_autologout'] === '1') {
		return;
	}

	// Jen pokud je uživatel přihlášený
	if (!is_user_logged_in()) {
		return;
	}

	// 2) Pokud je uživatel "switched", neodhlašovat.
	$is_switched = false;
	if (function_exists('current_user_switched')) {
		$is_switched = (bool) current_user_switched();
	}

	if ($is_switched) {
		wp_safe_redirect(admin_url(), 302);
		exit;
	}

	// Běžný režim: odhlásit přímo (bez wp-login.php?action=logout -> Defender loop)
	wp_logout();

	$redirect_back = home_url('/' . trim($matched_slug, '/') . '/');
	$redirect_back = add_query_arg('sw_autologout', '1', $redirect_back);

	wp_safe_redirect($redirect_back, 302);
	exit;

}, 0);

/** ------------------------------------------------
 * Média: možnost skrýt vybraná média z knihovny
 * - přidá checkbox "Skrýt z knihovny médií"
 * - skrytá média neuvidí běžní uživatelé ani ve výběru médií
 * - uživatel "paveltravnicek" je vidí normálně
 * ------------------------------------------------*/
function sw_hidden_media_library() {

	$sw_can_see_hidden = static function () {
		$user = wp_get_current_user();

		if (!$user || !$user->exists()) {
			return false;
		}

		if ($user->user_login === 'paveltravnicek') {
			return true;
		}

		return false;
	};

	add_filter(
		'attachment_fields_to_edit',
		static function ($form_fields, $post) {
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
		},
		10,
		2
	);

	add_filter(
		'attachment_fields_to_save',
		static function ($post, $attachment) {
			if (isset($attachment['sw_hidden_media']) && (int) $attachment['sw_hidden_media'] === 1) {
				update_post_meta($post['ID'], '_sw_hidden_media', '1');
			} else {
				delete_post_meta($post['ID'], '_sw_hidden_media');
			}

			return $post;
		},
		10,
		2
	);

	add_filter(
		'ajax_query_attachments_args',
		static function ($query) use ($sw_can_see_hidden) {
			if ($sw_can_see_hidden()) {
				return $query;
			}

			$meta_query   = isset($query['meta_query']) && is_array($query['meta_query']) ? $query['meta_query'] : [];
			$meta_query[] = [
				'relation' => 'OR',
				[
					'key'     => '_sw_hidden_media',
					'compare' => 'NOT EXISTS',
				],
				[
					'key'     => '_sw_hidden_media',
					'value'   => '1',
					'compare' => '!=',
				],
			];

			$query['meta_query'] = $meta_query;

			return $query;
		}
	);

	add_action(
		'pre_get_posts',
		static function ($query) use ($sw_can_see_hidden) {
			if (!is_admin() || !$query->is_main_query()) {
				return;
			}

			global $pagenow;

			if ($pagenow !== 'upload.php') {
				return;
			}

			if ($sw_can_see_hidden()) {
				return;
			}

			$meta_query   = $query->get('meta_query');
			$meta_query   = is_array($meta_query) ? $meta_query : [];
			$meta_query[] = [
				'relation' => 'OR',
				[
					'key'     => '_sw_hidden_media',
					'compare' => 'NOT EXISTS',
				],
				[
					'key'     => '_sw_hidden_media',
					'value'   => '1',
					'compare' => '!=',
				],
			];

			$query->set('meta_query', $meta_query);
		}
	);
}
sw_hidden_media_library();

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
			const protectedNames = ["Branda Pro", "Defender Pro"];
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

	$blocked_caps = ['delete_users', 'remove_users', 'edit_users'];
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

	$whitelist = ['paveltravnicek', 'lukashulka'];
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
