# Pattern: Backgrounds (tło sekcji)

Zdjęcie/grafika jako tło kontenera (hero, sekcja z overlay). Decyzja `<img>` vs `background-image` → patrz niżej + `css-conventions.md`.

---

## Kiedy background-image, kiedy `<img>`
- **background-image** — zdjęcie jest TŁEM, elementy (karty, tekst) siedzą NA nim. Na desktop overlay naturalny, na mobile zmienia się tylko layout. Nie przełączasz między `<img>` a `bg` na breakpoincie.
- **`<img>`** — zdjęcie to samodzielny element (galeria, karta, zdjęcie obok tekstu). Patrz `patterns/media-images.md`.

---

## 1. Proste tło (jedno zdjęcie)

**block.json:**
```json
"backgroundImage": { "type": "object", "default": {} }
```

**edit.js** — MediaUpload inline (przycisk absolute w rogu) + CSS var:
```js
const blockProps = useBlockProps( {
	className: 'wp-block-{namespace}-{BLOCK_NAME}',
	style: backgroundImage?.url ? { '--{PREFIX}-bg': `url(${ backgroundImage.url })` } : {},
} );

<MediaUploadCheck>
	<MediaUpload
		onSelect={ ( m ) => setAttributes( { backgroundImage: { id: m.id, url: m.url, alt: m.alt || '' } } ) }
		allowedTypes={ [ 'image' ] }
		value={ backgroundImage?.id }
		render={ ( { open } ) => (
			<button className="{PREFIX}__bg-upload" onClick={ open } type="button">
				{ backgroundImage?.url ? 'Zmień tło' : 'Dodaj tło' }
			</button>
		) }
	/>
</MediaUploadCheck>
{ backgroundImage?.url && (
	<button className="{PREFIX}__bg-remove" type="button"
		onClick={ () => setAttributes( { backgroundImage: {} } ) }>✕</button>
) }
```

**render.php** — inline CSS var (zawsze `esc_url`):
```php
<?php
$bg = $attributes['backgroundImage'] ?? [];
$style = ! empty( $bg['url'] ) ? '--{PREFIX}-bg:url(' . esc_url( $bg['url'] ) . ');' : '';
$wrapper = get_block_wrapper_attributes( [
	'class' => 'wp-block-{namespace}-{BLOCK_NAME}',
	'style' => $style,
] );
?>
<section <?php echo $wrapper; ?>>…</section>
```

**style.scss:**
```scss
.wp-block-{namespace}-{BLOCK_NAME} {
	position: relative; overflow: hidden;
	background-image: var(--{PREFIX}-bg);
	background-size: cover;
	background-position: center;
}
```

**editor.scss** (przycisk upload):
```scss
.{PREFIX}__bg-upload {
	position: absolute; top: 16px; right: 16px; z-index: 10;
	padding: 8px 16px; background: rgba(0,0,0,0.6); color: #fff; border: none; cursor: pointer;
}
```

---

## 2. Osobne tło desktop / mobile

Gdy design ma **różne grafiki tła** na desktop i mobile (typowe dla kształtnych/dekoracyjnych teł).

**block.json:**
```json
"bgDesktop": { "type": "object", "default": {} },
"bgMobile":  { "type": "object", "default": {} }
```

**render.php** — dwie CSS vary, mobile z fallbackiem do desktop:
```php
<?php
$parts = [];
if ( ! empty( $bgDesktop['url'] ) ) $parts[] = '--{PREFIX}-bg-desktop:url(' . esc_url( $bgDesktop['url'] ) . ')';
if ( ! empty( $bgMobile['url'] ) )  $parts[] = '--{PREFIX}-bg-mobile:url('  . esc_url( $bgMobile['url'] )  . ')';
$style = implode( ';', $parts );
?>
```

**style.scss:**
```scss
.{PREFIX}__bg {
	background-image: var(--{PREFIX}-bg-desktop);
	background-size: cover;
	background-position: center;
}
@media (max-width: 1024px) {
	.{PREFIX}__bg {
		background-image: var(--{PREFIX}-bg-mobile, var(--{PREFIX}-bg-desktop));
	}
}
```

**Inline w render.php, NIE w functions.php** — CSS vary muszą mieć scope per blok/instancję (kilka sekcji = różne tła).

---

## 3. Kształtne / dekoracyjne tła SVG (stretch)

Gdy tło to SVG-owy kształt pokrywający cały kontener (nie powtarzalny obraz):

```scss
.{PREFIX}__bg {
	background-image: var(--{PREFIX}-bg-desktop);
	background-size: 100% 100%;      // STRETCH, nie cover
	background-repeat: no-repeat;
}
```

**KRYTYCZNE:** SVG musi mieć `preserveAspectRatio="none"` w pliku — bez tego letterboxuje mimo `100% 100%` i „obcina krawędzie”. Otwórz pobrany SVG, dodaj/popraw atrybut na elemencie `<svg>`.

Dekoracje narożne (nie pełne tło) → `::before`/`::after` z SVG, `pointer-events: none`, desktop-only (`display: none` mobile).

---

## Reguły
- URL tła ZAWSZE z atrybutu + `esc_url()` — nigdy hardcoded.
- Tło na sekcji (full-bleed), padding na `__inner` (css-conventions.md).
- Sekcja z overlay na mobile: zwykle `padding-top` odsłania zdjęcie u góry — ten sam `background-image`, zmienia się tylko układ.
- Każdy bg MediaUpload ma przycisk usuwania ✕ (patterns/media-images.md).
