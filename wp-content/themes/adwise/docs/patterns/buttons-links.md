# Pattern: Buttons & Links

Każdy klikalny button/link w bloku. Edycja URL **inline** (popover z LinkControl), nigdy w sidebarze.

---

## Zasada
Klik w button → edytuję tekst inline (RichText) + widoczny popover z URL jednocześnie. Popover znika gdy kliknę poza button. Zero pól URL w sidebarze.

**Atrybuty:**
```json
"buttonText":  { "type": "string",  "default": "Tekst przycisku" },
"buttonUrl":   { "type": "string",  "default": "#" },
"buttonNewTab":{ "type": "boolean", "default": false }
```
`buttonUrl` default `"#"` — button zawsze ma href.

**Importy:**
```js
import { RichText, __experimentalLinkControl as LinkControl } from '@wordpress/block-editor';
import { Popover } from '@wordpress/components';
import { useState, useRef, useEffect } from '@wordpress/element';
```

---

## Jeden button — stan i click-outside
```js
const [ showPopover, setShowPopover ] = useState( false );
const btnWrapRef = useRef( null );

useEffect( () => {
	if ( ! showPopover ) return;
	const handle = ( e ) => {
		if ( btnWrapRef.current && ! btnWrapRef.current.contains( e.target ) ) {
			if ( ! e.target.closest( '.components-popover' ) ) setShowPopover( false );
		}
	};
	document.addEventListener( 'mousedown', handle );
	return () => document.removeEventListener( 'mousedown', handle );
}, [ showPopover ] );
```

**JSX:**
```js
<div className="{PREFIX}__btn-wrap" ref={ btnWrapRef } onClick={ () => setShowPopover( true ) }>
	<RichText tagName="span" className="{PREFIX}__btn"
		value={ buttonText }
		onChange={ ( val ) => setAttributes( { buttonText: val } ) }
		placeholder="Tekst przycisku…" allowedFormats={ [] } />
	<span className="{PREFIX}__btn-arrow" aria-hidden="true">
		<span className="{PREFIX}__btn-arrow-1">→</span>
		<span className="{PREFIX}__btn-arrow-2">→</span>
	</span>
	{ showPopover && (
		<Popover position="bottom center">
			<LinkControl
				value={ { url: buttonUrl, opensInNewTab: buttonNewTab } }
				onChange={ ( { url, opensInNewTab } ) => setAttributes( {
					buttonUrl: url || '#',
					buttonNewTab: !! opensInNewTab,
				} ) }
				settings={ [ { id: 'opensInNewTab', title: 'Otwórz w nowej karcie' } ] } />
		</Popover>
	) }
</div>
```

## Wiele buttonów (repeatable) — stan po index
```js
const [ activeBtnPopover, setActiveBtnPopover ] = useState( null );
const btnWrapRefs = useRef( {} );

useEffect( () => {
	if ( activeBtnPopover === null ) return;
	const handle = ( e ) => {
		const wrapEl = btnWrapRefs.current[ activeBtnPopover ];
		if ( wrapEl && ! wrapEl.contains( e.target ) ) {
			if ( ! e.target.closest( '.components-popover' ) ) setActiveBtnPopover( null );
		}
	};
	document.addEventListener( 'mousedown', handle );
	return () => document.removeEventListener( 'mousedown', handle );
}, [ activeBtnPopover ] );

// JSX: ref={ ( el ) => { btnWrapRefs.current[ i ] = el; } } onClick={ () => setActiveBtnPopover( i ) }
// warunek popovera: activeBtnPopover === i
```

---

## render.php
```php
<?php
$btn_text = $attributes['buttonText'] ?? '';
$btn_url  = $attributes['buttonUrl'] ?? '#';
$new_tab  = ! empty( $attributes['buttonNewTab'] );
$target   = $new_tab ? ' target="_blank" rel="noopener noreferrer"' : '';
?>
<a class="{PREFIX}__btn" href="<?php echo esc_url( $btn_url ); ?>"<?php echo $target; ?>>
	<span class="{PREFIX}__btn-text"><?php echo esc_html( $btn_text ); ?></span>
	<span class="{PREFIX}__btn-arrow" aria-hidden="true">
		<span class="{PREFIX}__btn-arrow-1">→</span>
		<span class="{PREFIX}__btn-arrow-2">→</span>
	</span>
</a>
```

---

## CSS — wymagane
- `max-width: 100%` + `box-sizing: border-box` na buttonie ze stałą width.
- Jawny `color` + `-webkit-tap-highlight-color: transparent` (iOS).
- Hover w `@media (hover: hover)`.
- Button z `display: flex` NIGDY nie dostaje `width: 100%` — flex rozciąga go automatycznie (width:100% przy flex → overflow).
- Arrow animation (arrow-1/arrow-2, mobile disable) → `css-conventions.md`.

```scss
.{PREFIX}__btn {
	display: inline-flex; align-items: center; gap: 8px;
	max-width: 100%; box-sizing: border-box;
	color: #fff; -webkit-tap-highlight-color: transparent;
	text-decoration: none;
}
@media (hover: hover) {
	.{PREFIX}__btn:hover { /* zmiana koloru/tła */ }
}
```

---

## Notched button (CTA z wcięciami)
CTA z dekoracyjnymi bocznymi wcięciami z designu → `clip-path: polygon(...)` lub nakładane SVG edges (`position: absolute`) + 2 statyczne strzałki flankujące tekst. Wszystkie wymiary wcięć w `clamp()` (mobile→desktop).

## Editable URL chip (tel/mailto gdzie tekst ≠ href)
Numer telefonu wyświetlany inaczej niż w `href` (np. „Zadzwoń” → `tel:+48…`): RichText na tekst + osobny chip „URL” z Popover LinkControl. Linki WEWNĄTRZ tekstu (nr w adresie) → RichText z `allowedFormats={ [ 'core/link' ] }` + `wp_kses_post()` w render.php.

Tel/email jako `<a href="tel:/mailto:">` z jawnym kolorem → `css-conventions.md`.
