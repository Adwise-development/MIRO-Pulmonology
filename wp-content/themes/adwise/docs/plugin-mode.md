# Plugin Mode — bloki jako plugin do istniejącego site'u

Gdy NIE budujemy standalone block theme, tylko **podpinamy bloki/landing do istniejącej witryny** (cudzy motyw, legacy site, WooCommerce itp.). Cały flow Figma→blok (patterns/, css-conventions, figma-to-block) zostaje **bez zmian** — różni się tylko **opakowanie**: bootstrap, ładowanie tokenów, scope CSS, template. Tryb **ad-hoc**.

---

## Theme vs Plugin — kiedy co
| | Block Theme (default) | Plugin (ten plik) |
|--|----------------------|-------------------|
| Kiedy | Nowa strona od zera, my kontrolujemy całość | Dokładamy do istniejącego site'u/motywu |
| Header/footer | Nasze (`parts/*.html`) | Z motywu hosta (`get_header`/`get_footer`) |
| Tokeny CSS (`--wp--preset--*`) | WP emituje sam z `theme.json` | **Musisz wyemitować sam** (theme.json nie działa) |
| Szablon strony | FSE templates | Custom page template + `template_include` |
| Można wyłączyć bez psucia strony | nie (to motyw) | tak (deaktywacja pluginu) |
| Ryzyko kolizji CSS | brak | duże → scope do wrappera `.lp-page` |

Reszta (bloki, render.php, edit.js, SCSS bloków, Figma flow) — identyczna.

---

## 1. Bootstrap — header + require-loop

`{plugin}.php`:
```php
<?php
/**
 * Plugin Name: {Brand} — Landing Pages
 * Description: Bloki Gutenberg jako landing page (per-page template).
 * Version: 1.0.0
 * Requires PHP: 7.4
 * Text Domain: {namespace}
 */
defined( 'ABSPATH' ) || exit;

define( '{NS}_DIR', plugin_dir_path( __FILE__ ) );
define( '{NS}_URL', plugin_dir_url( __FILE__ ) );
define( '{NS}_VERSION', '1.0.0' );

foreach ( [ 'helpers', 'tokens', 'blocks', 'assets', 'template-loader', 'editor-toggle', 'uploads', 'patterns', 'setup', 'integrations', 'login' ] as $m ) {
	require_once {NS}_DIR . "inc/{$m}.php";
}
```
Require-loop > glob: **jawna kolejność ładowania** + czytelność.

---

## 2. Modularny `inc/` split

Zamiast monolitycznego functions.php (działa też w theme!):
| Plik | Rola |
|------|------|
| `setup.php` | `register_nav_menus`, theme support, (opcj.) kreator stron 1-klik |
| `tokens.php` | **Emisja CSS vars + @font-face z theme.json** (patrz §3) |
| `blocks.php` | glob rejestracja `build/blocks/*` + filter anchor-injection |
| `assets.php` | enqueue (scoped, warunkowy) + inline tokeny |
| `helpers.php` | helpery (`{prefix}_responsive_image`, inline SVG) |
| `template-loader.php` | custom page template + `template_include` (§4) |
| `editor-toggle.php` | wymuś Gutenberg + contentSize w edytorze (§5) |
| `uploads.php` | SVG mime + sanityzacja (patterns/media-images.md) |
| `integrations.php` | opt-in GTM/GA/kod w head/body |
| `login.php` | branded login (recipes/login-page/) |

Anchor-injection filter (bloki SSR nie dostają `id` z anchora):
```php
add_filter( 'render_block', function ( $content, $block ) {
	$id = $block['attrs']['anchor'] ?? '';
	if ( ! $id || preg_match( '/^\s*<\w+[^>]*\sid=/', $content ) ) return $content;
	return preg_replace( '/(<\w+)/', '$1 id="' . esc_attr( $id ) . '"', $content, 1 );
}, 10, 2 );
```

---

## 3. Tokeny — emituj sam (theme.json NIE działa w pluginie)

W theme WP automatycznie generuje `--wp--preset--color--*` itd. z `theme.json`. **W pluginie musisz to zrobić ręcznie** — inaczej `var(--wp--preset--…)` w SCSS bloków będą puste.

`tokens.php`:
```php
function {prefix}_vars() {
	$json = json_decode( file_get_contents( {NS}_DIR . 'theme.json' ), true );
	$s = $json['settings'] ?? [];
	$out = [];
	foreach ( $s['color']['palette'] ?? []          as $c )  $out[] = "--wp--preset--color--{$c['slug']}:{$c['color']};";
	foreach ( $s['spacing']['spacingSizes'] ?? []    as $x )  $out[] = "--wp--preset--spacing--{$x['slug']}:{$x['size']};";
	foreach ( $s['typography']['fontSizes'] ?? []    as $f )  $out[] = "--wp--preset--font-size--{$f['slug']}:{$f['size']};";
	foreach ( $s['typography']['fontFamilies'] ?? [] as $ff ) $out[] = "--wp--preset--font-family--{$ff['slug']}:{$ff['fontFamily']};";
	return implode( '', $out );
}

function {prefix}_fontface() {
	$json = json_decode( file_get_contents( {NS}_DIR . 'theme.json' ), true );
	$out = '';
	foreach ( $json['settings']['typography']['fontFamilies'] ?? [] as $ff ) {
		foreach ( $ff['fontFace'] ?? [] as $face ) {
			$raw = is_array( $face['src'] ) ? $face['src'][0] : $face['src'];
			$src = str_replace( 'file:./', {NS}_URL, $raw ); // file:./assets/... → pełny URL pluginu
			$out .= sprintf(
				"@font-face{font-family:%s;font-weight:%s;font-style:%s;font-display:swap;src:url('%s') format('woff2');}",
				$face['fontFamily'], $face['fontWeight'] ?? '400', $face['fontStyle'] ?? 'normal', esc_url( $src )
			);
		}
	}
	return $out;
}
```

Emisja na froncie i w edytorze (`assets.php`) — tokeny w `:root` lub na wrapperze `.lp-page`:
```php
add_action( 'wp_enqueue_scripts', function () {
	wp_register_style( '{ns}-tokens', false, [], {NS}_VERSION );
	wp_enqueue_style( '{ns}-tokens' );
	wp_add_inline_style( '{ns}-tokens', {prefix}_fontface() . '.lp-page{' . {prefix}_vars() . '}' );
} );
add_action( 'enqueue_block_editor_assets', function () {
	wp_register_style( '{ns}-tokens-editor', false, [], {NS}_VERSION );
	wp_enqueue_style( '{ns}-tokens-editor' );
	wp_add_inline_style( '{ns}-tokens-editor', {prefix}_fontface() . '.editor-styles-wrapper{' . {prefix}_vars() . '}' );
} );
```

---

## 4. CSS scope + resety — nie zatruwaj motywu hosta

Style pluginu i motywu hosta mogą się gryźć w obie strony. Rozwiązanie: **scope wszystkiego do wrappera `.lp-page`** + resety o zerowej specyficzności (`:where()` = 0,0,0 — nie nadpisuje stylów bloków):
```php
function {prefix}_resets( $scope ) {
	return ":where({$scope} a){color:inherit;text-decoration:none;}"
	     . ":where({$scope} button){background:none;border:0;color:inherit;font:inherit;cursor:pointer;}"
	     . ":where({$scope} img){max-width:100%;height:auto;display:block;}"
	     . ":where({$scope} *){box-sizing:border-box;}";
}
```
Wszystkie selektory bloków też pod `.lp-page` (np. przez wrapper w template, §4). Tła sekcji full-bleed wewnątrz `.lp-page` rób przez `margin-inline: calc(50% - 50vw)` jeśli host ogranicza szerokość.

---

## 5. Template loader — render w motywie hosta

`template-loader.php`:
```php
define( '{NS}_PAGE_TEMPLATE', 'landing.php' );

add_filter( 'theme_page_templates', function ( $t, $theme, $post ) {
	if ( $post && $post->post_type === 'page' ) $t[ {NS}_PAGE_TEMPLATE ] = 'Landing Page';
	return $t;
}, 10, 3 );

add_filter( 'template_include', function ( $template ) {
	if ( is_singular( 'page' ) && get_page_template_slug() === {NS}_PAGE_TEMPLATE ) {
		$lp = {NS}_DIR . 'templates/page-landing.php';
		if ( file_exists( $lp ) ) return $lp;
	}
	return $template;
}, 99 );

add_filter( 'body_class', function ( $c ) {
	if ( is_page_template( {NS}_PAGE_TEMPLATE ) ) $c[] = 'lp-page';
	return $c;
} );
```

`templates/page-landing.php` — render treści w header/footer hosta, opakowane w `.lp-page`:
```php
<?php get_header(); ?>
<main class="lp-page">
	<?php while ( have_posts() ) : the_post(); the_content(); endwhile; ?>
</main>
<?php get_footer();
```
Redaktor edytuje stronę jak zwykły Gutenberg, ale renderuje się wewnątrz motywu hosta.

---

## 6. Editor toggle — wymuś Gutenberg + szerokość

`editor-toggle.php` (gdy host ma Classic Editor / page builder, albo wąski canvas):
```php
// Wymuś block editor na stronach z naszym szablonem
add_filter( 'use_block_editor_for_post', function ( $use, $post ) {
	return ( $post && get_page_template_slug( $post ) === {NS}_PAGE_TEMPLATE ) ? true : $use;
}, 100, 2 );

// Full-width canvas (contentSize/wideSize) w edytorze dla naszego szablonu
add_filter( 'block_editor_settings_all', function ( $settings, $context ) {
	$post = $context->post ?? null;
	if ( $post && get_page_template_slug( $post ) === {NS}_PAGE_TEMPLATE ) {
		$settings['__experimentalFeatures']['layout'] = [ 'contentSize' => '1440px', 'wideSize' => '1440px' ];
	}
	return $settings;
}, 10, 2 );

// (opcj.) ukryj metaboxy konkurencyjnych builderów na naszym szablonie
```

---

## 7. Reszta bez zmian
- **Bloki** (block.json/edit.js/render.php/scss) — identyczne jak w theme. Namespace = nazwa pluginu.
- **Patterny** (navbar, forms, backgrounds, media, buttons, dynamic, slider, animations, editor-gotchas) — bez zmian.
- **Figma flow** (figma-to-block.md), **CSS conventions**, **block-template.md** — bez zmian.
- **Webpack** — auto-discovery `blocks/*` + dodatkowe entry `theme/reveal` dla globalnego JS (assets/js/reveal.js).
- **Assets warunkowo** — enqueue view.js/reveal/CF7 tylko gdy blok/klasa na stronie (patterns/animations.md, patterns/forms.md).
- **Login** → recipes/login-page/ (działa tak samo, require w `inc/login.php`).
