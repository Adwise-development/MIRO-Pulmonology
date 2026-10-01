# Pattern: Media & Images

Jak przechowujemy i renderujemy obrazy. Domyślnie: **`object {id,url,alt}` + responsive srcset** (helper PHP z `id`).

---

## Zasada przechowywania
- **User-upload** (zdjęcia, ikony, logo edytowalne) → atrybut `object {id,url,alt}` przez `MediaUpload`. Trafia do `wp-content/uploads/`. **NIE** do `assets/`.
- **Hardcoded** (stały SVG w szablonie, nieedytowalny) → `assets/icons/[block]/` lub `assets/images/[block]/`, użyty inline w render.php/CSS.
- **Reguła:** atrybut block.json = `object {id,url,alt}` → MediaUpload → uploads. Jeśli element jest stały (zawsze ten sam) → assets/.

```json
"image":  { "type": "object", "default": {} },
"images": { "type": "array",  "default": [ { "id": null, "url": "", "alt": "" } ] }
```
Zapis z MediaUpload: `{ id: m.id, url: m.url, alt: m.alt || '' }` — **zawsze trzymaj `id`** (potrzebne do srcset).

---

## Responsive srcset — helper PHP (standard render)

Render obrazów przez helper generujący `srcset` + `sizes` z `id`. Helper w `functions.php` (prefix PHP wg projektu):

```php
/**
 * Responsive <img> z srcset/sizes na podstawie atrybutu media {id,url,alt}.
 *
 * @param array  $media  { id, url, alt }
 * @param string $size   rozmiar bazowy (np. 'large', 'full', custom)
 * @param array  $args   [ 'class' => '', 'sizes' => '', 'loading' => 'lazy', 'class_attr' => '' ]
 */
function {prefix}_responsive_image( $media, $size = 'large', $args = [] ) {
	$id  = absint( $media['id'] ?? 0 );
	$alt = esc_attr( $media['alt'] ?? '' );
	$class   = esc_attr( $args['class'] ?? '' );
	$sizes   = $args['sizes'] ?? '100vw';
	$loading = $args['loading'] ?? 'lazy'; // 'eager' dla LCP

	if ( $id ) {
		// id → natywny srcset WP (wszystkie wygenerowane rozmiary)
		echo wp_get_attachment_image( $id, $size, false, [
			'class'   => $class,
			'alt'     => $alt,
			'sizes'   => $sizes,
			'loading' => $loading,
		] );
		return;
	}
	// Fallback: tylko url (np. stary atrybut bez id)
	if ( ! empty( $media['url'] ) ) {
		printf(
			'<img src="%s" alt="%s" class="%s" loading="%s" />',
			esc_url( $media['url'] ), $alt, $class, esc_attr( $loading )
		);
	}
}
```

**Użycie w render.php:**
```php
<?php {prefix}_responsive_image(
	$item['image'],
	'large',
	[ 'class' => '{PREFIX}__image', 'sizes' => '(max-width: 1024px) 100vw, 33vw' ]
); ?>
```

`wp_get_attachment_image` z `id` daje `srcset`/`sizes` za darmo (wszystkie rozmiary z media library). `sizes` dopasuj do layoutu (ile szerokości obraz zajmuje na desktop/mobile).

### LCP — obraz nad foldem
Główny obraz hero (above the fold) NIE lazy:
```php
{prefix}_responsive_image( $bg, 'full', [ 'loading' => 'eager', 'sizes' => '100vw' ] );
```
Dodaj `fetchpriority="high"` dla LCP (przez `wp_get_attachment_image` attr `'fetchpriority' => 'high'`). Reszta obrazów `loading="lazy"`.

**Preload LCP (jeszcze szybciej):** sparsuj `post_content`, wyciągnij URL tła hero i preloaduj w `wp_head`:
```php
add_action( 'wp_head', function () {
	$post = get_queried_object();
	if ( ! $post instanceof WP_Post ) return;
	foreach ( parse_blocks( $post->post_content ) as $b ) {
		if ( ( $b['blockName'] ?? '' ) !== '{namespace}/hero' ) continue;
		$url = $b['attrs']['backgroundImage']['url'] ?? '';
		if ( $url ) printf( '<link rel="preload" as="image" href="%s" fetchpriority="high">' . "\n", esc_url( $url ) );
		break;
	}
} );
```

---

## Image Element — slot z object-fit

**Wariant A — jedno zdjęcie** (tekst + zdjęcie obok): atrybut `image`.
**Wariant B — galeria/grid**: atrybut `images` (array slotów).

```scss
.{PREFIX}__image-slot {
	position: relative;
	aspect-ratio: 16 / 9; // wg Figmy
}
.{PREFIX}__image {
	position: absolute; inset: 0;
	width: 100%; height: 100%;
	object-fit: cover;
}
```
Mobile: zachowaj `aspect-ratio` z desktopu (zdjęcia różnych wysokości — nie wyrównujemy na siłę).

---

## Media trigger = `<button>`, NIGDY klikalny `<img onClick>` — KAŻDY MediaUpload

> ⚠️ **Klik w `<img onClick={open}>` jest ZAWODNY w Gutenbergu.** Pierwszy klik w obrazek tylko **zaznacza blok** — picker się nie otwiera → user „nie ma edycji". Sprawdzone wielokrotnie. **Reguła:** trigger media to zawsze prawdziwy `<button>` (preview ZAWSZE wewnątrz buttona, 1 klik) — nigdy goły `<img>` z `onClick`.

Każdy `MediaUpload` (ikona, zdjęcie, logo, tło) MUSI mieć: trigger-`<button>` + preview + przycisk „✕". Styl jednolity w projekcie.

**edit.js — wzorzec (ikona w repeatable item):**
```js
<div className="{PREFIX}__icon-wrap">
	<MediaUploadCheck>
		<MediaUpload
			onSelect={ ( m ) => updateItem( i, 'icon', { id: m.id, url: m.url, alt: m.alt || '' } ) }
			allowedTypes={ [ 'image' ] }
			value={ item.icon?.id }
			render={ ( { open } ) => (
				// ZAWSZE <button> (1 klik), preview w środku — NIE <img onClick>
				<button type="button" className="{PREFIX}__icon-btn" onClick={ open }>
					{ ( item.icon?.url || iconFallback )
						? <img src={ item.icon?.url || iconFallback } alt={ item.icon?.alt || '' } className="{PREFIX}__icon" />
						: <span className="{PREFIX}__icon-placeholder">+</span> }
				</button>
			) }
		/>
	</MediaUploadCheck>
	{ item.icon?.url && (
		<button className="{PREFIX}__icon-remove" type="button"
			onClick={ () => updateItem( i, 'icon', {} ) }>✕</button>
	) }
</div>
```
Dla atrybutu bloku (nie repeatable): `onClick={ () => setAttributes( { image: {} } ) }`.

**`.{PREFIX}__icon-btn` (editor.scss) = button-reset:** `background:none; border:none; padding:0; cursor:pointer; display:block;` (+ jawny `color` jeśli SVG `currentColor`).

### Domyślny asset theme → edytor = front 1:1 (fallback przez `themeUri`)
Jeśli render.php ma fallback do domyślnego assetu theme (`ADWISE_URI . '/assets/...'`), edit.js MUSI mieć **identyczny** fallback — inaczej edytor pokazuje placeholder, front obrazek (łamie zasadę edytor=front). Baseline lokalizuje ścieżkę theme w `functions.php` (`window.ADWISE.themeUri`):
```js
const iconFallback = item.icon?.url || `${ window.ADWISE?.themeUri ?? '' }/assets/icons/{block}/type-default.svg`;
```
Wtedy `✕` **przywraca domyślny asset** (nie czyści do pustego) — `onClick={ () => updateItem( i, 'icon', {} ) }` + render/edit pokazują fallback. Dla pól bez domyślnego assetu `✕` czyści normalnie do `{}`.

**editor.scss — jednolity styl ✕:**
```scss
.{PREFIX}__icon-wrap, .{PREFIX}__image-wrap { position: relative; }
.{PREFIX}__icon-wrap:hover  .{PREFIX}__icon-remove,
.{PREFIX}__image-wrap:hover .{PREFIX}__image-remove { display: flex; }

.{PREFIX}__icon-remove, .{PREFIX}__image-remove {
	display: none; position: absolute;
	width: 18px; height: 18px;
	background: rgba(220, 38, 38, 0.8); color: #fff;
	border: none; border-radius: 50%;
	font-size: 9px; line-height: 1; cursor: pointer;
	align-items: center; justify-content: center;
	&:hover { background: rgba(220, 38, 38, 1); }
}
.{PREFIX}__icon-remove  { top: -6px; right: -6px; }  // ikony (małe)
.{PREFIX}__image-remove { top: 8px;  right: 8px;  }  // zdjęcia (duże)
```

**Placeholder gdy brak zdjęcia:** dashed border + ikona „+”.

---

## SVG upload (jeśli user uploaduje ikony SVG)

**Blueprint już to ma — NIE reimplementuj.** WP domyślnie blokuje SVG; baseline włącza go z twardą sanityzacją i guardem capability:

- **Sanityzacja:** `enshrined/svg-sanitize` (parser XML + allowlist, **nie** regex). `vendor/` jest commitowany → świeży klon działa od razu. Kod: `functions.php` §SVG upload + `inc/class-adwise-svg-sanitizer.php` (subklasa domyka gołe zewnętrzne `href`/`xlink:href` poza `url(...)` — biblioteka sama łapie tylko `url(...)`).
- **Guard capability:** upload tylko dla `edit_posts` (admin/editor/author); TEN SAM guard na `upload_mimes` i `wp_check_filetype_and_ext` (inaczej bypass w cron/sideload). Bez `svgz` (gzip → sanitizer tekstowy byłby no-opem).
- **Preview** w bibliotece: CSS w `admin_head` (`functions.php`).
- Klon bez `vendor/` (pominięty `composer install`) → filtr **odrzuca** SVG komunikatem, nie przepuszcza surowego.

Ścinane: `<script>`, handlery `on*`, `javascript:`, zewn. referencje (`url(http://…)`, gołe `//host` / `http(s):`), XXE/encje. Zachowane: wewn. `#id`, `data:`, względne ścieżki.

Inline SVG z Figmy (hardcoded) → `fill="currentColor"`.

---

## Pas obrazu (`adwise-img-band`) — utility, nie blok

Szeroki kadr edge-to-edge między sekcjami (Figma: np. 1440×540) robimy **natywnym `core/image`**
z klasą w „Dodatkowe klasy CSS", nie osobnym blokiem. CSS + parallax siedzą globalnie:
inline `<style>` w `wp_head` (`functions.php`) + `assets/js/img-band.js`.
**Implementacja referencyjna: projekt burdzig** (`functions.php` + `assets/js/img-band.js`) —
skopiuj przy pierwszym użyciu; blueprint baseline jej nie zawiera.

| Klasa | Efekt |
|-------|-------|
| `adwise-img-band` | `height: 540px` desktop / **`37.5vw` ≤1024** (proporcja z Figmy, np. 2,67:1), `object-fit: cover`, `overflow:hidden` na `figure` |
| `adwise-img-band no-parallax` | statyczny cover — dla kadrów komponowanych 1:1 (kolaże), gdzie overscan ucina boki |

Działa też na `core/post-featured-image` (wzorce singli CPT).

**Gotchas:**
- Specyficzność selektora musi być **0,2,2** (`figure.wp-block-image.adwise-img-band img`) — core WP ma `height:auto` przy 0,1,1 i wygrywa kolejnością (objaw: wysokość = `width/aspect` zamiast zadanej).
- Parallax = JS dodaje klasę `.has-parallax` (obraz wyższy o TRAVEL px + `translateY` na scrollu). **Wartość TRAVEL w JS musi zgadzać się z `height: calc(100% + Npx)` w CSS.**
- **≤1024 parallax wyłączony** (guard `matchMedia` w `img-band.js` + reset `has-parallax img{height:100%}`) — przy niskim pasie overscan zjadałby cały kadr.
- Skrypt enqueue'owany warunkowo: tylko gdy `post_content` zawiera `adwise-img-band`.

---

## Inline SVG w render.php — gotcha
`echo` inline SVG bez przejścia przez `wp_kses_post()` (które wycina `<svg>`/`<path>`):
```php
// SVG hardcoded z assets/ — zaufane źródło
echo file_get_contents( get_theme_file_path( 'assets/icons/{block}/arrow.svg' ) ); // phpcs:ignore
```
Komentarz `phpcs:ignore` bo to zaufany lokalny plik. Dla SVG od usera → sanityzuj (wyżej).
