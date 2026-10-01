# Pattern: Forms (Contact Form 7)

Drugie częste źródło bugów. Formularz renderowany na froncie shortcodem CF7, w edytorze **statyczny preview** (NIE `ServerSideRender`).

---

## 1. block.json
```json
"attributes": {
  "anchor":    { "type": "string" },
  "className": { "type": "string" },
  "heading":   { "type": "string",  "default": "Napisz do nas" },
  "cf7FormId": { "type": "integer", "default": 0 }
}
```

---

## 2. edit.js — lista formularzy + statyczny preview

**KLUCZOWE:** NIE używaj `ServerSideRender` dla CF7 — renderuje cały blok w bloku, psuje layout, ładuje skrypty CF7 w edytorze. Zamiast tego: `SelectControl` (wybór formularza, **inline w podglądzie bloku** — editor-only) + statyczny mock pól. Sidebar TYLKO dla wariantów bloku.

```js
import { useBlockProps, RichText } from '@wordpress/block-editor';
import { SelectControl, Spinner } from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

export default function Edit( { attributes, setAttributes } ) {
	const { heading, cf7FormId } = attributes;
	const blockProps = useBlockProps( { className: 'wp-block-{namespace}-contact' } );

	const [ forms, setForms ] = useState( [] );
	useEffect( () => {
		let cancelled = false;
		apiFetch( { path: '/contact-form-7/v1/contact-forms?per_page=100' } )
			.then( ( data ) => {
				if ( cancelled ) return;
				const list = data.items || data || [];
				setForms( list.map( ( f ) => ( { value: f.id, label: f.title || f.slug } ) ) );
			} )
			.catch( () => {} );
		return () => { cancelled = true; };
	}, [] );

	return (
		<section { ...blockProps }>
			<div className="{PREFIX}__inner">
				<RichText tagName="h2" className="{PREFIX}__heading"
					value={ heading }
					onChange={ ( val ) => setAttributes( { heading: val } ) }
					allowedFormats={ [] } />

				{/* Wybór/usunięcie formularza — inline, editor-only (opcja 0 = brak) */}
				<div className="{PREFIX}__form-picker">
					<SelectControl
						label="Formularz CF7"
						value={ cf7FormId }
						options={ [ { value: 0, label: '— brak formularza —' }, ...forms ] }
						onChange={ ( val ) => setAttributes( { cf7FormId: parseInt( val, 10 ) } ) }
						__nextHasNoMarginBottom
					/>
				</div>

				{/* Statyczny mock — imituje pola, NIE renderuje CF7 */}
				<div className="{PREFIX}__form-mock" aria-hidden="true">
					<div className="{PREFIX}__field-mock" />
					<div className="{PREFIX}__field-mock" />
					<div className="{PREFIX}__field-mock {PREFIX}__field-mock--area" />
					<div className="{PREFIX}__submit-mock">{ heading ? 'Wyślij' : '' }</div>
				</div>
				{ ! cf7FormId && <p className="{PREFIX}__hint">Brak formularza — front pokaże tylko nagłówek.</p> }
			</div>
		</section>
	);
}
```

`cf7FormId` wybierany **inline w podglądzie bloku** (kontrolka editor-only w karcie, ostyluj w `editor.scss` np. dashed border). Sidebar TYLKO dla wariantów bloku — NIE dla konfiguracji formularza. Opcja `0` („— brak formularza —") = usunięcie formularza z sekcji.

**Endpoint:** `/contact-form-7/v1/contact-forms` to oficjalny REST CF7 (zawsze dostępny gdy CF7 aktywny). Alternatywa `/wp/v2/contact-forms` działa tylko jeśli CPT formularzy ma `show_in_rest` — nie polegaj na niej. Stąd `.catch()` + guard `data.items || data`.

---

## 3. render.php
```php
<?php
$heading = $attributes['heading'] ?? '';
$form_id = absint( $attributes['cf7FormId'] ?? 0 );
$wrapper = get_block_wrapper_attributes( [ 'class' => 'wp-block-{namespace}-contact' ] );
?>
<section <?php echo $wrapper; ?>>
	<div class="{PREFIX}__inner">
		<?php if ( $heading ) : ?>
			<h2 class="{PREFIX}__heading"><?php echo wp_kses_post( $heading ); ?></h2>
		<?php endif; ?>
		<?php if ( $form_id ) : ?>
			<div class="{PREFIX}__form">
				<?php echo do_shortcode( '[contact-form-7 id="' . $form_id . '"]' ); ?>
			</div>
		<?php endif; ?>
	</div>
</section>
```
- **Shortcode id:** CF7 5.8+ generuje w shortcode hash, ale numeryczne `id` (post ID, które trzymamy w `cf7FormId`) nadal działa — `do_shortcode` z post ID jest OK.
- **Anchor:** CF7 wstawia własne `id="wpcf7-..."` w zagnieżdżonym tagu — nie koliduje z anchorem sekcji (filter sprawdza tylko pierwszy tag).

---

## 4. CSS — obowiązkowe reguły CF7

**Skąd klasy wrapperów (`.{PREFIX}-cf7__field`):** markup pól CF7 definiujesz w **szablonie formularza w panelu CF7** (Kontakt → Formularze), nie generuje się sam. Żeby selektory niżej działały, owiń pola w szablonie CF7:
```
<p class="{PREFIX}-cf7__field">[text* your-name placeholder "Imię"]</p>
<p class="{PREFIX}-cf7__field">[email* your-email placeholder "E-mail"]</p>
```

CF7 wstawia `<br>` i `<p>` — trzeba nadpisać, inaczej layout się rozjeżdża.

```scss
.{PREFIX}__form br { display: none; }
.{PREFIX}__form .wpcf7-form p { margin: 0; }

// Pola — flex column (CF7 owija label+input)
.{PREFIX}-cf7__field > p { display: flex; flex-direction: column; gap: 4px; }

// Inputy
.{PREFIX}__form input,
.{PREFIX}__form textarea {
	width: 100%;
	box-sizing: border-box; // bez tego padding → overflow
}
```

### Walidacja bez layout shift (KRYTYCZNE)
Błąd CF7 (`.wpcf7-not-valid-tip`) jako `position: absolute`, pole rezerwuje miejsce `padding-bottom`. Bez tego pojawienie błędu przesuwa cały layout sekcji (CLS).

```scss
.{PREFIX}-cf7__field { position: relative; padding-bottom: 16px; }
.{PREFIX}__form .wpcf7-not-valid-tip {
	position: absolute; bottom: -20px; left: 0;
	font-size: 11px; color: var(--wp--preset--color--error);
}
```

Kontener formularza — **bez sztywnego `height`** (walidacja rozpycha pola).

---

## 5. Custom submit button (opcjonalnie)

CF7 renderuje `<input type="submit">` — żeby ostylować jak CTA z designu, owiń go przez filter:

```php
add_filter( 'wpcf7_form_elements', function ( $html ) {
	// np. dodaj klasę / owiń w span dla strzałki/krawędzi
	$html = str_replace(
		'<input type="submit"',
		'<input type="submit" class="{PREFIX}__submit"',
		$html
	);
	return $html;
} );
```
Dla buttona z animowaną strzałką/notch → patterns/buttons-links.md (CF7 nie pozwala na dowolny markup wewnątrz submita, ostyluj `<input>` lub owiń w span).

---

## 6. Stan sukcesu (opcjonalnie)
CF7 dodaje `.wpcf7-response-output` z komunikatem. Ostyluj jawnie zamiast domyślnego CF7:
```scss
.wpcf7-response-output { /* własny styl */ }
.wpcf7 form.sent .wpcf7-response-output { /* sukces */ }
.wpcf7 form.invalid .wpcf7-response-output { /* błąd globalny */ }
```
Animowany checkmark sukcesu → patterns/animations.md.

---

## 7. Wydajność — ładuj CSS/JS CF7 tylko gdzie trzeba

CF7 domyślnie wczytuje swój CSS/JS na KAŻDEJ stronie. Ogranicz do stron z blokiem formularza (`functions.php`):
```php
add_filter( 'wpcf7_load_css', function ( $load ) {
	return is_singular() && has_block( '{namespace}/contact', get_queried_object() );
} );
add_filter( 'wpcf7_load_js', function ( $load ) {
	return is_singular() && has_block( '{namespace}/contact', get_queried_object() );
} );
```

---

## 8. CF7 nie odwzorowuje custom checkboxa / reveal 1:1
Design często ma „shadcn" checkbox (custom box ze svg) + pole chowane za checkboxem (JS reveal). CF7 renderuje **natywny** checkbox + statyczne pola — nie ma JS reveal ani dowolnego markupu.

**Decyzja (default):** styluj CF7 natywnie (`accent-color` brandu), pola zawsze widoczne (textarea nie chowana). Jeśli klient wymaga 1:1:
- custom JS na froncie nadpisujący markup CF7 (`wpcf7_form_elements` filter + view.js), **lub**
- natywny formularz + własny REST submit zamiast CF7 (pełna kontrola markupu/UX, tracisz panel CF7).

Odnotuj uproszczenie w `project.md` (np. „checkbox natywny, nie shadcn — ograniczenie CF7").

---

## Inne biblioteki formularzy
Gdy projekt używa innego pluginu (WPForms, Gravity) — zasada ta sama: **statyczny preview w edytorze (NIE SSR), walidacja position:absolute bez layout shift, nadpisz natywne `<br>`/`<p>`/marginesy**. Wybór formularza zawsze inline w podglądzie (editor-only); sidebar tylko warianty bloku.
