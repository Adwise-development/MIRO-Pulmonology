# Block Template — bare scaffolding

Szkielet nowego bloku. To jest **bare minimum** — patterny (button, media, tło, slider, dynamic, formularz, navbar) są w `patterns/`.

**Placeholdery:**
- `{namespace}` — nazwa folderu theme'u (underscores → hyphens)
- `{BLOCK_NAME}` — kebab-case (np. `team-cards`)
- `{BLOCK_TITLE}` — wyświetlana nazwa
- `{PREFIX}` — 2–5 literowy skrót CSS (np. `tc`, `hero`)

---

## blocks/{BLOCK_NAME}/block.json
```json
{
  "$schema": "https://schemas.wp.org/trunk/block.json",
  "apiVersion": 3,
  "name": "{namespace}/{BLOCK_NAME}",
  "version": "1.0.0",
  "title": "{BLOCK_TITLE}",
  "category": "theme",
  "description": "Opis bloku po polsku.",
  "textdomain": "{namespace}",
  "editorScript": "file:./index.js",
  "editorStyle": "file:./index.css",
  "style": "file:./style-index.css",
  "render": "file:./render.php",
  "attributes": {
    "anchor": { "type": "string" },
    "className": { "type": "string" },
    "heading": { "type": "string", "default": "Nagłówek sekcji" },
    "items": {
      "type": "array",
      "default": [
        { "title": "Element 1", "description": "Opis elementu.", "image": {} }
      ]
    }
  },
  "supports": {
    "html": false,
    "anchor": true,
    "customClassName": true,
    "align": ["wide", "full"],
    "color": false,
    "spacing": false
  }
}
```
Dodaj `"viewScript": "file:./view.js"` tylko dla bloków interaktywnych.

> ⚠️ **JSON:** w wartościach (np. polski `description`) NIE wstawiaj prostego `"` — zamyka string, webpack build pada. Użyj `„…"` albo escapuj `\"`. Waliduj: `python3 -c "import json,sys; json.load(open(sys.argv[1]))" block.json`.

---

## blocks/{BLOCK_NAME}/index.js
```js
import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';
import save from './save';
import './style.scss';
import './editor.scss';

registerBlockType( metadata.name, { edit: Edit, save } );
```

## blocks/{BLOCK_NAME}/save.js
```js
export default function save() {
	return null;
}
```

---

## blocks/{BLOCK_NAME}/edit.js
Szkielet: nagłówek inline + powtarzalne itemy z obrazem. Media-remove, button, tło → dorzuć z `patterns/`.

```js
import { useBlockProps, RichText, MediaUpload, MediaUploadCheck } from '@wordpress/block-editor';
import { Button } from '@wordpress/components';

export default function Edit( { attributes, setAttributes } ) {
	const { heading, items } = attributes;
	const blockProps = useBlockProps( { className: 'wp-block-{namespace}-{BLOCK_NAME}' } );

	const updateItem = ( i, key, value ) => {
		const updated = [ ...items ];
		updated[ i ] = { ...updated[ i ], [ key ]: value };
		setAttributes( { items: updated } );
	};
	const addItem = () =>
		setAttributes( { items: [ ...items, { title: 'Nowy element', description: 'Opis.', image: {} } ] } );
	const removeItem = ( i ) =>
		setAttributes( { items: items.filter( ( _, idx ) => idx !== i ) } );

	return (
		<section { ...blockProps }>
			<div className="{PREFIX}__inner">
				<RichText tagName="h2" className="{PREFIX}__heading"
					value={ heading }
					onChange={ ( val ) => setAttributes( { heading: val } ) }
					placeholder="Nagłówek…" allowedFormats={ [] } />

				<div className="{PREFIX}__grid">
					{ items.map( ( item, i ) => (
						<div className="{PREFIX}__card" key={ i }>
							{ items.length > 1 && (
								<button className="{PREFIX}__remove" type="button"
									onClick={ () => removeItem( i ) }>✕</button>
							) }
							{/* Obraz: patterns/media-images.md (MediaUpload + ✕ remove + srcset) */}
							<RichText tagName="h3" className="{PREFIX}__title"
								value={ item.title }
								onChange={ ( val ) => updateItem( i, 'title', val ) }
								allowedFormats={ [] } />
							<RichText tagName="p" className="{PREFIX}__desc"
								value={ item.description }
								onChange={ ( val ) => updateItem( i, 'description', val ) }
								allowedFormats={ [] } />
						</div>
					) ) }
				</div>
				<Button variant="secondary" onClick={ addItem }>+ Dodaj element</Button>
			</div>
		</section>
	);
}
```

**InspectorControls** (sidebar) tylko dla niewidocznej konfiguracji (toggle, select, range) — patrz `patterns/editor-gotchas.md`.

---

## blocks/{BLOCK_NAME}/render.php
```php
<?php
$heading = $attributes['heading'] ?? '';
$items   = $attributes['items'] ?? [];

$wrapper = get_block_wrapper_attributes( [ 'class' => 'wp-block-{namespace}-{BLOCK_NAME}' ] );
?>
<section <?php echo $wrapper; ?>>
	<div class="{PREFIX}__inner">
		<?php if ( $heading ) : ?>
			<h2 class="{PREFIX}__heading"><?php echo wp_kses_post( $heading ); ?></h2>
		<?php endif; ?>

		<?php if ( ! empty( $items ) ) : ?>
			<div class="{PREFIX}__grid">
				<?php foreach ( $items as $item ) : ?>
					<div class="{PREFIX}__card">
						<?php // Obraz z srcset → patterns/media-images.md ?>
						<?php if ( ! empty( $item['title'] ) ) : ?>
							<h3 class="{PREFIX}__title"><?php echo wp_kses_post( $item['title'] ); ?></h3>
						<?php endif; ?>
						<?php if ( ! empty( $item['description'] ) ) : ?>
							<p class="{PREFIX}__desc"><?php echo wp_kses_post( $item['description'] ); ?></p>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</section>
```
**Escaping wg KONTEKSTU wyjścia, nie źródła danych:** pole RichText → `wp_kses_post()` · zwykły tekst → `esc_html()` · atrybut HTML → `esc_attr()` · `href`/`src` → `esc_url()` (NIGDY `esc_attr` — przepuszcza `javascript:`) · dane do `<script>`/`data-*` → `wp_json_encode()`. `get_block_wrapper_attributes()` zwraca już escapowany string — echo raw. `wp_kses_post()` NIE jest uniwersalnym escapem — na zwykłym tekście luzuje zamiast zacieśniać.

---

## blocks/{BLOCK_NAME}/style.scss
```scss
.wp-block-{namespace}-{BLOCK_NAME} {
	position: relative;
	/* ZERO padding — tło edge-to-edge */
}
.{PREFIX}__inner {
	max-width: 1440px;
	margin: 0 auto;
	box-sizing: border-box;
	padding: clamp(24px, calc(24px + 40 * ((100vw - 768px) / 672)), 64px)
	         clamp(16px, calc(16px + 24 * ((100vw - 768px) / 672)), 40px);
}
.{PREFIX}__grid {
	display: grid;
	grid-template-columns: repeat(3, 1fr);
	gap: clamp(24px, calc(24px + 24 * ((100vw - 768px) / 672)), 48px);
}
@media (max-width: 1024px) {
	.{PREFIX}__grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 767px) {
	.{PREFIX}__grid { grid-template-columns: 1fr; }
}
```
Reguły clamp/breakpoint/box-sizing → `css-conventions.md`.

## blocks/{BLOCK_NAME}/editor.scss
Synchronizuj clamp/breakpointy z `style.scss`. Dodatkowo edytorowe UI (placeholdery, remove buttony):
```scss
.wp-block-{namespace}-{BLOCK_NAME} {
	.{PREFIX}__remove {
		position: absolute; top: 8px; right: 8px;
		width: 28px; height: 28px;
		background: rgba(0, 0, 0, 0.6); color: #fff; border: none;
		cursor: pointer; display: none;
	}
	.{PREFIX}__card { position: relative; }
	.{PREFIX}__card:hover .{PREFIX}__remove { display: flex; }
}
```

## blocks/{BLOCK_NAME}/view.js (opcjonalny)
```js
( function () {
	const block = document.querySelector( '.wp-block-{namespace}-{BLOCK_NAME}' );
	if ( ! block ) return;
	// interaktywność (toggle, IntersectionObserver, Swiper…)
} )();
```

---

## Checklist nowego bloku
1. [ ] `block.json` — namespace, `anchor`+`className` jawne, supports
2. [ ] `index.js` — rejestracja
3. [ ] `edit.js` — inline editing (RichText, MediaUpload, LinkControl)
4. [ ] `save.js` — `return null`
5. [ ] `render.php` — SSR z escapowaniem
6. [ ] `style.scss` — clamp na wartościach, @1024px na layout, box-sizing
7. [ ] `editor.scss` — TYLKO chrome edytora (dashed bordery, placeholdery, ✕); layout dziedziczy ze style.scss (ładowany w iframe — NIE kopiuj clamp)
8. [ ] Każdy `MediaUpload` ma przycisk "✕" + trigger = `<button>` (NIE `<img onClick>`, patterns/media-images.md)
9. [ ] Obrazy → `object{id,url,alt}` + responsive srcset (patterns/media-images.md)
9b.[ ] Domyślny asset theme → edit.js fallback `window.ADWISE.themeUri` IDENTYCZNY jak render.php (edytor=front)
10. [ ] Buttony: `max-width: 100%` + `box-sizing` + jawny `color` + tap-highlight
11. [ ] Arrow animation (jeśli jest): `arrow-2 display:none` + `arrow-1 transform:none` @1024px
12. [ ] Hover na froncie → `@media (hover: hover)`
13. [ ] Tel/email → `<a href="tel:/mailto:">` z jawnym kolorem
14. [ ] Sekcja z overlay → `background-image`, nie `<img>` (patterns/backgrounds.md)
15. [ ] `view.js` — (opcjonalnie) interaktywność
15b.[ ] `block.json` waliduje się jako JSON (polski opis bez prostego `"`)
16. [ ] `npm run build` — zero błędów
17. [ ] Test edytor (desktop + wąski panel)
18. [ ] Test frontend (desktop + mobile)
