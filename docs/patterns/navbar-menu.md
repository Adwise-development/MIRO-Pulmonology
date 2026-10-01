# Pattern: Navbar + Menu (live preview)

Najczęstsze źródło bugów. Podejście kanoniczne: **custom blok navbar + live preview WP menu w edytorze**.

**Zasada:** treść menu żyje w **Wygląd → Menu** (jedno źródło prawdy). Blok tylko **wybiera które menu** (`menuId`) i renderuje je z designem z Figmy. W edytorze pokazujemy prawdziwe linki (live preview), nie atrapę.

**Tworzenie menu:** klasycznego menu WP nie da się utworzyć inline w bloku — robi się to w Wygląd → Menu. Gdy na site'cie nie ma żadnego menu, blok pokazuje **empty-state z linkiem** do `nav-menus.php` (patrz edit.js §2). Jeśli chcesz menu w pełni edytowalne wewnątrz bloku (bez WP Menu) → wariant na końcu pliku.

Wymaga w `functions.php`:
```php
register_nav_menus( [ 'primary' => 'Menu główne', 'footer' => 'Menu stopki' ] );
add_theme_support( 'menus' );
```

---

## 1. block.json (singleton w parts/header.html)
```json
"attributes": {
  "anchor":   { "type": "string" },
  "className":{ "type": "string" },
  "menuId":   { "type": "integer", "default": 0 },
  "logo":     { "type": "object",  "default": {} },
  "ctaText":  { "type": "string",  "default": "Kontakt" },
  "ctaUrl":   { "type": "string",  "default": "#" }
}
```
`"viewScript": "file:./view.js"` — navbar zawsze potrzebuje JS (mobile, sticky state).

---

## 2. edit.js — live preview menu

Picker (SelectControl) gdy `menuId === 0`; preview prawdziwych linków gdy wybrany; ✕ resetuje.

```js
import { useBlockProps, MediaUpload, MediaUploadCheck, RichText } from '@wordpress/block-editor';
import { SelectControl, Spinner } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

export default function Edit( { attributes, setAttributes } ) {
	const { menuId, logo, ctaText, ctaUrl } = attributes;
	const blockProps = useBlockProps( { className: 'wp-block-{namespace}-navbar' } );

	// Lista menu do SelectControl
	const menus = useSelect(
		( select ) => select( coreStore ).getEntityRecords( 'root', 'menu', { per_page: -1 } ),
		[]
	);

	// Pozycje wybranego menu (apiFetch + cleanup przeciw setState po unmount)
	const [ items, setItems ] = useState( [] );
	const [ loading, setLoading ] = useState( false );
	useEffect( () => {
		if ( ! menuId ) { setItems( [] ); return; }
		let cancelled = false;
		setLoading( true );
		apiFetch( { path: `/wp/v2/menu-items?menus=${ menuId }&per_page=100` } )
			.then( ( data ) => {
				if ( cancelled ) return;
				const sorted = [ ...data ].sort( ( a, b ) => a.menu_order - b.menu_order );
				setItems( sorted );
			} )
			.catch( () => { if ( ! cancelled ) setItems( [] ); } )
			.finally( () => { if ( ! cancelled ) setLoading( false ); } );
		return () => { cancelled = true; };
	}, [ menuId ] );

	const menuOptions = [
		{ value: 0, label: '— wybierz menu —' },
		...( menus || [] ).map( ( m ) => ( { value: m.id, label: m.name } ) ),
	];

	return (
		<nav { ...blockProps }>
			<div className="nav__inner">
				{/* Logo — MediaUpload + remove → patterns/media-images.md */}

				{ ! menuId ? (
					( menus && menus.length === 0 ) ? (
						// Empty-state: brak menu na site'cie — klasycznego menu nie tworzy się w bloku
						<div className="nav__menu-empty">
							<p>Brak menu. Utwórz je w <strong>Wygląd → Menu</strong>, dodaj pozycje, zapisz, wróć tu i odśwież.</p>
							<a href="nav-menus.php" target="_blank" rel="noopener noreferrer">Otwórz Wygląd → Menu ↗</a>
						</div>
					) : (
						<SelectControl
							label="Menu nawigacji"
							value={ menuId }
							options={ menuOptions }
							onChange={ ( val ) => setAttributes( { menuId: parseInt( val, 10 ) } ) }
						/>
					)
				) : (
					<div className="nav__menu-preview">
						{ loading && <Spinner /> }
						<ul className="nav__menu">
							{ items.map( ( it ) => (
								<li key={ it.id }>
									{ /* klik w edytorze NIE nawiguje */ }
									<a href={ it.url } onClick={ ( e ) => e.preventDefault() }>
										{ it.title?.rendered || it.title }
									</a>
								</li>
							) ) }
						</ul>
						<button type="button" className="nav__menu-reset"
							onClick={ () => setAttributes( { menuId: 0 } ) }>✕ zmień menu</button>
					</div>
				) }

				{/* CTA — RichText + LinkControl popover → patterns/buttons-links.md */}
			</div>
		</nav>
	);
}
```

**Gotchas edytora (przyczyny bugów):**
- **`e.preventDefault()` na linkach** — bez tego klik w link w edytorze nawiguje i wyrzuca z edytora.
- **`cancelled` flag w cleanup** — bez tego `setItems` po unmount → warning + wyciek.
- **Sort po `menu_order`** — REST zwraca nieposortowane.
- `getEntityRecords('root','menu')` zwraca `null` zanim się załaduje — guard `|| []`.

---

## 3. render.php — natywne menu WP

```php
<?php
$menu_id = absint( $attributes['menuId'] ?? 0 );
$logo    = $attributes['logo'] ?? [];
$wrapper = get_block_wrapper_attributes( [ 'class' => 'wp-block-{namespace}-navbar' ] );
?>
<nav <?php echo $wrapper; ?> data-navbar>
	<div class="nav__inner">
		<?php if ( ! empty( $logo['url'] ) ) : ?>
			<a class="nav__logo" href="<?php echo esc_url( home_url( '/' ) ); ?>">
				<img src="<?php echo esc_url( $logo['url'] ); ?>"
					alt="<?php echo esc_attr( $logo['alt'] ?? get_bloginfo( 'name' ) ); ?>" />
			</a>
		<?php endif; ?>

		<button class="nav__burger" type="button" aria-expanded="false" aria-controls="nav-panel" aria-label="Menu">
			<span></span><span></span><span></span>
		</button>

		<div class="nav__panel" id="nav-panel">
			<?php
			if ( $menu_id ) {
				wp_nav_menu( [
					'menu'        => $menu_id,
					'container'   => false,
					'menu_class'  => 'nav__menu',
					'fallback_cb' => false,
					'depth'       => 2,
				] );
			}
			?>
		</div>
	</div>
</nav>
```

`wp_nav_menu` z `'menu' => $menu_id` renderuje dokładnie to menu (te same linki co preview).

**Gotcha — custom `Walker_Nav_Menu` musi emitować `<li>` + `start_lvl`.** Domyślny `wp_nav_menu` (wyżej) jest bezpieczny — stylujesz po klasach `.sub-menu` / `.menu-item-has-children`. Jeśli **musisz** mieć custom markup (własny walker), subklasa MUSI nadpisać komplet, inaczej dropdowny się nie chowają (wszystko na wierzchu):
- `start_el` → emituj `<li class="{$item->classes}">` + `<a>` (nie sam `<a>` — CSS/JS nie ma za co złapać `menu-item-has-children`).
- `end_el` → `</li>`.
- `start_lvl`/`end_lvl` → własna klasa podmenu `<ul class="nav__sub">` (bez nadpisania WP daje domyślny `sub-menu`).

Preferuj domyślny `wp_nav_menu` + stylowanie po klasach — mniej pułapek.

---

## 4. CSS — sticky navbar (NIE pure fixed)

Top bar (jeśli jest) static + main bar sticky. **Bez `body padding-top`** — sticky nie wyjmuje z flow.

```scss
:root { --nav-h: 66px; }
@media (max-width: 1024px) { :root { --nav-h: 58px; } }

// Navbar = jeden pasek: sticky na <nav> (wrapper bloku, render.php)
.wp-block-{namespace}-navbar {
	padding: 0;                 // full-bleed
	position: sticky; top: 0; z-index: 100;
	background: var(--wp--preset--color--background);
}
// Wariant z osobnym top barem (2 paski): top bar w flow, main sticky.
// Wtedy render.php ma .nav__top + .nav__main, a sticky daj na .nav__main:
// .nav__top  { position: static; }
// .nav__main { position: sticky; top: 0; z-index: 100; }

.nav__inner {
	max-width: 1440px; margin: 0 auto; box-sizing: border-box;
	display: flex; align-items: center; justify-content: space-between;
	// BEZ sztywnego height — padding determinuje wysokość
}
[id] { scroll-margin-top: calc(var(--nav-h) + 16px); }

// Burger + panel: desktop vs mobile
.nav__burger { display: none; }
@media (max-width: 1024px) {
	.nav__burger { display: inline-flex; }
	.wp-block-{namespace}-navbar:not(.is-open) .nav__panel { display: none; } // zamknięte → poza tab order
	.wp-block-{namespace}-navbar.is-open .nav__panel { display: block; /* dropdown */ }
}
```

**Gotcha — sticky w template part:** `position: sticky` nie zadziała, gdy przodek przycina blok (`overflow: hidden`) lub tworzy własny kontekst. WP owija blok w `<header class="wp-block-template-part">`. Jeśli sticky nie łapie — daj `display: contents` na wrapperze header part, żeby kontenerem sticky stał się `.wp-site-blocks`:
```scss
.wp-site-blocks > header.wp-block-template-part { display: contents; }
```

**Gotcha — a11y zamkniętego menu:** chowaj zamknięte mobilne menu przez `display: none` (linki wypadają z tab order — `inert` zbędny). Jeśli ANIMUJESZ panel (transform/opacity, panel zostaje w DOM) → zamiast `display:none` użyj `inert` + `aria-hidden` (view.js niżej już to toggluje).

**Burger breakpoint — nie ustalaj na ślepo.** Policz: liczba pozycji menu × średnia szerokość. Menu 7–8 pozycji często nie mieści się poniżej ~1080px. Testuj pasmo **1000–1120px** czy nie ma poziomego scrolla; dopiero wtedy ustal próg hamburgera (często wyżej niż 1024px).

---

## 5. view.js — mobile + sticky state

```js
( function () {
	const nav = document.querySelector( '[data-navbar]' );
	if ( ! nav ) return;
	const burger = nav.querySelector( '.nav__burger' );
	const panel  = nav.querySelector( '.nav__panel' );

	const setOpen = ( open ) => {
		nav.classList.toggle( 'is-open', open );
		burger.setAttribute( 'aria-expanded', String( open ) );
		panel.toggleAttribute( 'inert', ! open );        // niedostępny dla focus gdy zamknięty
		document.body.style.overflow = open ? 'hidden' : ''; // blokuj scroll tła
	};

	burger.addEventListener( 'click', () => setOpen( ! nav.classList.contains( 'is-open' ) ) );
	document.addEventListener( 'keydown', ( e ) => { if ( e.key === 'Escape' ) setOpen( false ); } );
	panel.querySelectorAll( 'a' ).forEach( ( a ) => a.addEventListener( 'click', () => setOpen( false ) ) );
	document.addEventListener( 'click', ( e ) => {
		if ( nav.classList.contains( 'is-open' ) && ! nav.contains( e.target ) ) setOpen( false );
	} );

	// sticky state (np. zmiana tła po scrollu)
	const onScroll = () => nav.classList.toggle( 'is-scrolled', window.scrollY > 24 );
	onScroll();
	window.addEventListener( 'scroll', onScroll, { passive: true } );
} )();
```

Mobile menu zamyka się na: X / link / Escape / klik poza. `aria-expanded` na burgerze + `body overflow:hidden` przy otwartym. `toggleAttribute('inert')` ma znaczenie tylko gdy panel zostaje w DOM (wariant animowany); przy chowaniu `display:none` (default z CSS wyżej) jest nieszkodliwy nadmiar.

---

## Wariant: edytowalne linki w bloku (bez WP Menu)
Tylko gdy user świadomie chce linki jako atrybuty bloku (np. krótki footer) — wtedy array `links: [{ text, url }]` z RichText + LinkControl per item (patterns/buttons-links.md). **Dla głównej nawigacji preferuj WP Menu** (live preview) — łatwiej zarządzać, jedno źródło prawdy.
