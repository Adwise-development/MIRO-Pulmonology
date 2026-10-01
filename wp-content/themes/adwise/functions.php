<?php
/**
 * Adwise — functions.php
 *
 * Blueprint block theme pod workflow Figma → Gutenberg.
 * Sekcje: stałe · theme setup · SVG upload · content-length helper ·
 * auto-rejestracja bloków · anchor injection · smooth scroll ·
 * login KV (inc/) · security hardening.
 */

defined( 'ABSPATH' ) || exit;

define( 'ADWISE_VERSION', '1.0.0' );
define( 'ADWISE_DIR', get_template_directory() );
define( 'ADWISE_URI', get_template_directory_uri() );

// Composer autoload — enshrined/svg-sanitize (sanityzacja SVG przy uploadzie).
if ( is_readable( ADWISE_DIR . '/vendor/autoload.php' ) ) {
	require_once ADWISE_DIR . '/vendor/autoload.php';
	// Subklasa domykająca gołe zewnętrzne href/xlink:href (poza url(...)).
	require_once ADWISE_DIR . '/inc/class-adwise-svg-sanitizer.php';
}

/**
 * Mnożniki max długości tekstu w RichText (block editor).
 * max = default_text.length × multiplier. Zmień tu → wpływa na wszystkie bloki.
 * HEADING — nagłówki (krótsze). TEXT — paragrafy/opisy (luźniej).
 */
define( 'ADWISE_CL_HEADING_MULTIPLIER', 3 );
define( 'ADWISE_CL_TEXT_MULTIPLIER',    5 );

/* -------------------------------------------------------------------------
 * Theme setup
 * ---------------------------------------------------------------------- */
add_action( 'after_setup_theme', function () {
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'menus' ); // włącza Wygląd → Menu w block theme (domyślnie ukryte w FSE)
	load_theme_textdomain( 'adwise', ADWISE_DIR . '/languages' );

	register_nav_menus( [
		'primary' => __( 'Menu główne', 'adwise' ),
		'footer'  => __( 'Menu stopka', 'adwise' ),
	] );

	// Style ładowane do iframe edytora (post + site editor)
	add_editor_style( 'assets/css/editor.css' );
} );

/* -------------------------------------------------------------------------
 * Content Length Limit helper — globalny dla wszystkich bloków (edytor)
 * ---------------------------------------------------------------------- */
add_action( 'enqueue_block_editor_assets', function () {
	wp_enqueue_script(
		'adwise-content-length',
		ADWISE_URI . '/assets/js/content-length-limit.js',
		[ 'wp-element', 'wp-data', 'wp-notices' ],
		ADWISE_VERSION,
		true
	);
	wp_add_inline_script(
		'adwise-content-length',
		sprintf(
			// themeUri → edit.js robi ten sam fallback do domyślnych assetów theme co render.php (edytor = front 1:1).
			'window.ADWISE=window.ADWISE||{};window.ADWISE.contentLength={heading:%d,text:%d};window.ADWISE.themeUri=%s;',
			(int) ADWISE_CL_HEADING_MULTIPLIER,
			(int) ADWISE_CL_TEXT_MULTIPLIER,
			wp_json_encode( ADWISE_URI )
		),
		'before'
	);
} );

/* -------------------------------------------------------------------------
 * SVG upload support (dla ikon w blokach)
 * Sanityzacja przez `enshrined/svg-sanitize` (vendor/, parser XML + allowlist — nie regex):
 * usuwa <script>, handlery on*, javascript:, zewn. referencje, blokuje XXE/encje.
 * Ta sama biblioteka, którą owija wtyczka safe-svg — bez wymogu instalacji pluginu.
 * Dozwolone TYLKO dla userów z capability `edit_posts` (admin/editor/author).
 * ---------------------------------------------------------------------- */
add_filter( 'upload_mimes', function ( $mimes ) {
	// Bez svgz: to gzip, więc sanitizer poniżej (pracuje na tekście) byłby na nim no-opem.
	if ( current_user_can( 'edit_posts' ) ) {
		$mimes['svg'] = 'image/svg+xml';
	}
	return $mimes;
} );

// WP 5.0.1+ sprawdza real MIME — trzeba nadpisać dla SVG.
// TEN SAM guard capability co upload_mimes — bez niego filtr legalizuje SVG
// w kontekstach bez zalogowanego usera (cron, wp_handle_sideload) = bypass bramki.
add_filter( 'wp_check_filetype_and_ext', function ( $data, $file, $filename ) {
	if ( ! empty( $data['ext'] ) && ! empty( $data['type'] ) ) {
		return $data;
	}
	if ( ! current_user_can( 'edit_posts' ) ) {
		return $data;
	}
	$ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
	if ( 'svg' === $ext ) {
		$data['ext']  = 'svg';
		$data['type'] = 'image/svg+xml';
	}
	return $data;
}, 10, 3 );

// Sanityzuj SVG przy uploadzie — enshrined/svg-sanitize (parser XML + allowlist).
add_filter( 'wp_handle_upload_prefilter', function ( $file ) {
	if ( ! isset( $file['type'] ) || 'image/svg+xml' !== $file['type'] ) {
		return $file;
	}
	if ( ! isset( $file['tmp_name'] ) || ! is_readable( $file['tmp_name'] ) ) {
		return $file;
	}
	// Brak biblioteki (klon bez `composer install`) → NIE przepuszczaj surowego SVG.
	if ( ! class_exists( 'Adwise_Svg_Sanitizer' ) ) {
		$file['error'] = __( 'Sanitizer SVG niedostępny — uruchom `composer install` w theme.', 'adwise' );
		return $file;
	}
	$content = file_get_contents( $file['tmp_name'] );
	if ( false === $content ) {
		return $file;
	}
	$sanitizer = new Adwise_Svg_Sanitizer();
	$sanitizer->removeRemoteReferences( true ); // tnij zewnętrzne referencje (SSRF/XXE)
	$clean = $sanitizer->sanitize( $content );
	if ( false === $clean || stripos( $clean, '<svg' ) === false ) {
		$file['error'] = __( 'Niepoprawny lub niebezpieczny SVG (odrzucony przez sanitizer).', 'adwise' );
		return $file;
	}
	if ( false === file_put_contents( $file['tmp_name'], $clean ) ) {
		$file['error'] = __( 'Nie udało się zapisać zsanityzowanego SVG.', 'adwise' );
	}
	return $file;
} );

// Preview SVG w media library grid (WP domyślnie nie pokazuje thumbnail SVG).
// Tylko na ekranach mediów/edycji — nie na każdym ekranie wp-admin.
add_action( 'admin_head', function () {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	$media_screens = [ 'upload', 'post', 'page' ];
	if ( ! $screen || ! in_array( $screen->base, $media_screens, true ) ) {
		return;
	}
	// [src*=".svg"] (nie [src$]) — URL miniatury bywa z query stringiem.
	echo '<style>
		.attachment-preview .thumbnail img[src*=".svg"],
		.media-icon img[src*=".svg"] {
			width: 100%;
			height: auto;
			max-width: 100%;
			object-fit: contain;
		}
	</style>';
} );

/* -------------------------------------------------------------------------
 * Auto-rejestracja bloków z "build/blocks/<dir>/block.json".
 * Webpack (CopyWebpackPlugin) kopiuje block.json + render.php do build/.
 * ---------------------------------------------------------------------- */
add_action( 'init', function () {
	$block_dirs = glob( ADWISE_DIR . '/build/blocks/*/block.json' );
	if ( ! $block_dirs ) {
		return;
	}
	foreach ( $block_dirs as $block_json ) {
		register_block_type( dirname( $block_json ) );
	}
} );

/* -------------------------------------------------------------------------
 * Anchor injection — wstrzyknij id z atrybutu `anchor` do pierwszego tagu bloku
 * (bloki SSR z `save → null` nie renderują id same z siebie)
 * ---------------------------------------------------------------------- */
add_filter( 'render_block', function ( $block_content, $block ) {
	if ( empty( $block['attrs']['anchor'] ) || '' === trim( (string) $block_content ) ) {
		return $block_content;
	}
	// WP_HTML_Tag_Processor (WP 6.2+): poprawnie znajduje pierwszy tag i escapuje
	// wartość atrybutu. Zastępuje regex, który psuł tagi samozamykające i przepuszczał
	// backreference injection (`$1`) z wartości anchor do stringu zastępującego preg_replace.
	$processor = new WP_HTML_Tag_Processor( $block_content );
	if ( $processor->next_tag() ) {
		if ( null === $processor->get_attribute( 'id' ) ) {
			$processor->set_attribute( 'id', $block['attrs']['anchor'] );
		}
		return $processor->get_updated_html();
	}
	return $block_content;
}, 10, 2 );

/* -------------------------------------------------------------------------
 * Smooth scroll + scroll-margin dla kotwic.
 * UWAGA: po dodaniu sticky navbara ustaw --nav-h na realną wysokość
 * i dodaj body{padding-top:var(--nav-h)} (patrz docs/patterns/navbar-menu.md).
 * ---------------------------------------------------------------------- */
add_action( 'wp_head', function () {
	// scroll-behavior tylko gdy user nie prosił o ograniczenie ruchu (WCAG 2.3.3).
	echo '<style>
		:root{--nav-h:0px}
		[id]{scroll-margin-top:calc(var(--nav-h) + 16px)}
		@media (prefers-reduced-motion: no-preference){html{scroll-behavior:smooth}}
	</style>';
} );

/* -------------------------------------------------------------------------
 * Custom login KV (split-screen branded login) — inc/adwise-login.php
 * ---------------------------------------------------------------------- */
require_once __DIR__ . '/inc/adwise-login.php';

/* -------------------------------------------------------------------------
 * WP MCP runtime abilities (Abilities API → mcp-adapter) — inc/abilities.php
 * Akcje runtime (postaw stronę / seed CPT / CF7 / front page) jako typed
 * abilities. No-op gdy brak Abilities API (WP < 6.9). Patrz docs/wp-mcp.md.
 * ---------------------------------------------------------------------- */
require_once __DIR__ . '/inc/abilities.php';

/* -------------------------------------------------------------------------
 * Security hardening (idempotent — marker ADWISE_SECURITY_HARDENING).
 * Slug logowania (WPS Hide Login) + brute-force (Limit Login Attempts) =
 * pluginy per-deploy, NIE w repo. Patrz docs/recipes/login-page/workflow.md.
 * ---------------------------------------------------------------------- */
if ( ! defined( 'ADWISE_SECURITY_HARDENING' ) ) {
	define( 'ADWISE_SECURITY_HARDENING', true );

	// XML-RPC off
	add_filter( 'xmlrpc_enabled', '__return_false' );
	add_filter( 'xmlrpc_methods', '__return_empty_array' );

	// REST: ukryj endpoint users przed ANONIMAMI (enumeracja). Zalogowanym zostawiamy —
	// edytor bloków wymaga /wp/v2/users do panelu autora (inaczej „nie mogę zmienić autora").
	add_filter( 'rest_endpoints', function ( $endpoints ) {
		if ( ! is_user_logged_in() ) {
			unset( $endpoints['/wp/v2/users'], $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
		}
		return $endpoints;
	} );

	// Blok enumeracji autorów (?author=N)
	add_action( 'template_redirect', function () {
		if ( is_author() || ( ! is_admin() && isset( $_GET['author'] ) ) ) {
			wp_safe_redirect( home_url(), 301 );
			exit;
		}
	} );

	// Ukryj wersję WP
	remove_action( 'wp_head', 'wp_generator' );
	add_filter( 'the_generator', '__return_empty_string' );

	// Usuń nagłówek X-Pingback
	add_filter( 'wp_headers', function ( $headers ) {
		unset( $headers['X-Pingback'] );
		return $headers;
	} );
}
