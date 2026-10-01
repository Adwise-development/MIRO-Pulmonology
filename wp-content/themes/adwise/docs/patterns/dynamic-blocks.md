# Pattern: Dynamic Block (WP_Query)

Blok pobierający dane z bazy (grid postów, CPT, archiwa). Podgląd w edytorze przez `ServerSideRender` lub `useSelect`.

---

## Edytor: ServerSideRender vs useSelect

| | ServerSideRender | useSelect (getEntityRecords) |
|--|------------------|------------------------------|
| Kiedy | Prosty grid, render.php = źródło prawdy | Gdy render.php zawiera header/CTA których nie chcesz duplikować |
| Plus | Zero duplikacji logiki | Pełna kontrola React, brak double-render |
| Minus | Ładuje cały render.php (też header/CTA) | Duplikujesz markup karty w edit.js |

**Gotcha:** jeśli render.php renderuje nagłówek + CTA + grid, `ServerSideRender` pokaże je 2× (raz React heading, raz SSR). Wtedy: heading/CTA jako RichText w edit.js, a w render.php ukryj je w kontekście edytora lub użyj `useSelect` tylko do gridu.

```php
$is_editor = defined( 'REST_REQUEST' ) && REST_REQUEST;
```

---

## CPT z meta przez REST → `custom-fields` w supports (KRYTYCZNE)
> Gdy meta CPT jest **seedowana lub edytowana przez REST** (`register_post_meta` z `show_in_rest => true`): to NIE wystarcza. CPT MUSI mieć `'custom-fields'` w `supports`, inaczej schema `meta` w REST jest pusty → POST/PATCH meta **cicho ignorowany** (`featured_media` zapisuje się, meta nie). Objaw: `meta = null` w REST mimo poprawnej rejestracji.
```php
register_post_type( '{cpt}', [
	// ...
	'show_in_rest' => true,
	'supports'     => [ 'title', 'editor', 'thumbnail', 'custom-fields' ], // ← 'custom-fields' wymagane dla meta przez REST
] );
register_post_meta( '{cpt}', 'price', [ 'type' => 'number', 'single' => true, 'show_in_rest' => true ] );
```

---

## edit.js
```js
import ServerSideRender from '@wordpress/server-side-render';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl, RangeControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';

const postTypes = useSelect( ( select ) => {
	const types = select( coreStore ).getPostTypes( { per_page: -1 } );
	if ( ! types ) return [];
	return types
		.filter( ( t ) => t.viewable && t.slug !== 'attachment' )
		.map( ( t ) => ( { label: t.name, value: t.slug } ) );
}, [] );

<InspectorControls>
	<PanelBody title="Źródło">
		<SelectControl label="Typ treści" value={ postType } options={ postTypes }
			onChange={ ( v ) => setAttributes( { postType: v } ) } />
		<RangeControl label="Ilość" min={ 1 } max={ 12 } value={ perPage }
			onChange={ ( v ) => setAttributes( { perPage: v } ) } />
	</PanelBody>
</InspectorControls>

<ServerSideRender block="{namespace}/{BLOCK_NAME}" attributes={ attributes } httpMethod="POST" />
```
Typ treści, ilość, kolumny → **sidebar** (konfiguracja). Heading/subheading → RichText inline.

---

## render.php
```php
<?php
$post_type = sanitize_text_field( $attributes['postType'] ?? 'post' );
$per_page  = min( absint( $attributes['perPage'] ?? 6 ), 12 );

if ( ! post_type_exists( $post_type ) ) return;

$query = new WP_Query( [
	'post_type'      => $post_type,
	'posts_per_page' => $per_page,
	'paged'          => 1,
	'post_status'    => 'publish',
] );
$wrapper = get_block_wrapper_attributes( [ 'class' => 'wp-block-{namespace}-{BLOCK_NAME}' ] );
?>
<section <?php echo $wrapper; ?>>
	<div class="{PREFIX}__grid"
		data-post-type="<?php echo esc_attr( $post_type ); ?>"
		data-per-page="<?php echo esc_attr( $per_page ); ?>"
		data-paged="1"
		data-max-pages="<?php echo esc_attr( $query->max_num_pages ); ?>">
		<?php while ( $query->have_posts() ) : $query->the_post(); ?>
			<a class="{PREFIX}__card" href="<?php the_permalink(); ?>">
				<?php // featured image przez {prefix}_responsive_image( ... ) ?>
				<h3 class="{PREFIX}__title"><?php the_title(); ?></h3>
				<p class="{PREFIX}__excerpt"><?php echo esc_html( get_the_excerpt() ); ?></p>
			</a>
		<?php endwhile; wp_reset_postdata(); ?>
	</div>
</section>
```
`data-*` na gridzie → view.js czyta do AJAX load more.

---

## REST endpoint (functions.php) — load more
```php
register_rest_route( '{namespace}/v1', '/{BLOCK_NAME}', [
	'methods'             => 'GET',
	'callback'            => '{prefix}_{BLOCK_NAME}_callback',
	'permission_callback' => '__return_true',
	'args'                => [
		'post_type' => [ 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
		'per_page'  => [ 'required' => true, 'sanitize_callback' => 'absint' ],
		'paged'     => [ 'required' => true, 'sanitize_callback' => 'absint' ],
	],
] );
```
Zawsze: `sanitize_text_field` na stringach, `absint` na intach, `post_type_exists()` przed query, `min($per_page, 12)` limit.

---

## view.js (load more)
- Fetch `wp-json/{namespace}/v1/{BLOCK_NAME}` z parametrami z `data-*`.
- Buduj HTML kart z JSON, appenduj do gridu.
- `is-loading` class + `aria-busy` na gridzie podczas fetch.
- Ukryj button gdy `paged >= max-pages`.
- **SEO fallback:** „Załaduj więcej" w render.php = `<a href="/{archive}/page/2/">` (pretty pagination), NIE goły `<button>`; view.js przechwytuje click (`preventDefault`) i robi fetch. Bez JS crawler dostaje normalny link do paginacji.
