# Pattern: Editor Gotchas

Pułapki edytora Gutenberga, które najczęściej psują bloki. Czytaj gdy „coś nie działa w edytorze”.

---

## 1. Sidebar vs inline — anti-patterny
Konwencja (CLAUDE.md, jedno źródło prawdy): **widzisz efekt na karcie → inline; nie widzisz → sidebar.** Mapa typ pola → miejsce:

| Pole | Miejsce · komponent |
|------|--------------------|
| Tekst widoczny (tytuł, opis, cena) | **inline** · `RichText` |
| Obraz / ikona | **inline** · `MediaUpload` (trigger `<button>`) |
| URL | **inline** · popover + `LinkControl` |
| Wybór z podglądem na kanwie (menu, formularz CF7, post-type) | **inline** editor-only · `SelectControl` (picker gdy pusto, preview + ✕ gdy wybrane — navbar-menu.md, forms.md) |
| Konfiguracja bez podglądu (delay ms, liczba kolumn/slajdów, toggle loop) | **sidebar** · `RangeControl`/`ToggleControl` |
| Wariant bloku | **sidebar** · `variant` |

Najczęstsze błędy:
- **Tekst widoczny na karcie w sidebarze** (tytuł, cena, opis) → ŹLE. Redaktor ma edytować to, co widzi → RichText inline.
- **URL buttona w sidebarze** → ŹLE. Inline popover + LinkControl (patterns/buttons-links.md).
- **Wybór z sensownym podglądem (menu/formularz) w sidebarze** → ŹLE. Picker inline, preview na kanwie.
- **„+ Dodaj” w sidebarze** → ŹLE. Pod listą w podglądzie.
- **Odwrotnie:** czysta konfiguracja bez podglądu (delay, kolumny) wciśnięta inline „na siłę" → też ŹLE. To należy do sidebara.

## 2. Context-aware enqueue (CPT meta panel)
Panel meta w sidebarze ładuj TYLKO dla właściwego post_type, nie globalnie:
```php
add_action( 'enqueue_block_editor_assets', function () {
	$screen = get_current_screen();
	if ( $screen && $screen->post_type === 'oferta' ) {
		wp_enqueue_script( /* oferta-meta-panel.js */ );
	}
} );
```
Mniej JS w adminie globalnie, brak konfliktów.

---

## 3. Content Length Limit (3-warstwowy)
Nagłówki/opisy z limitem długości = `default.length × N` (np. ×3 dla heading, ×5 dla body). Globalny hook + 3 propsy na RichText:
```js
<RichText
	value={ heading }
	onChange={ limitedSet( 'heading', 'Nagłówek sekcji' ) }       // safety net — obcina
	onKeyDown={ keyLimit( 'heading', 'Nagłówek sekcji', heading ) } // blokuje wpisywanie
	onPaste={ pasteLimit( 'heading', 'Nagłówek sekcji', heading ) } // wkleja tylko brakujące
/>
```
- `onKeyDown` — blok klawiszy przy limicie
- `onPaste` — przytnij wklejane do brakującej ilości
- `onChange` — ostatnia linia obrony
- Snackbar z unikalnym `id` (`len-${key}`) — bez stackowania powiadomień
- **Druga funkcja przyjmuje aktualną wartość** (z React state) — żeby dokładnie zmierzyć długość.

Hook globalny (enqueue w `enqueue_block_editor_assets`, `window.{NS}.useLengthLimit`) — nie kopiuj logiki do każdego bloku.

---

## 4. Template parts — edytuj w pliku, NIE w Site Editor
`parts/*.html` = single source of truth (git). **NIE** edytuj w Wygląd → Edytor (Site Editor) — tworzy DB post `wp_template_part`, który nadpisuje plik i jest niewidoczny w git → zmiany gubią się na prod.

- Edycja atrybutów bloków (logo, menuId, buttonUrl) → bezpośrednio w `.html`.
- Reset DB override: `wp_delete_post( $id, true )` dla postów `post_type='wp_template_part'` + purge cache.
- Wyjątek (programmatic override) tylko gdy user wyraźnie prosi — odnotuj post ID gdzieś w docs projektu.

---

## 5. Inline SVG vs wp_kses_post
`wp_kses_post()` wycina `<svg>`/`<path>`. Dla zaufanego lokalnego SVG:
```php
echo file_get_contents( get_theme_file_path( 'assets/icons/...' ) ); // phpcs:ignore
```
SVG od usera (upload) → sanityzuj (patterns/media-images.md). Nigdy nie echuj surowego SVG z niezaufanego źródła.

## 6. Custom RichText format → try/catch
`getFormatType` nie jest publicznym eksportem — jeśli używasz, owiń w `try/catch`. Inaczej cały blok się NIE rejestruje w edytorze (cicha awaria).
```js
let exists;
try { exists = getFormatType( 'namespace/format' ); } catch ( e ) { exists = null; }
if ( ! exists ) registerFormatType( 'namespace/format', {...} );
```

---

## 7. ServerSideRender — double render
`ServerSideRender` renderuje CAŁY render.php. Jeśli render.php zawiera heading/CTA edytowane też przez RichText → pojawią się 2×. Rozwiązania:
- Heading/CTA jako RichText inline, w render.php ukryj w edytorze (`REST_REQUEST`), albo
- `useSelect` + getEntityRecords tylko do danych (grid), markup karty w edit.js (patterns/dynamic-blocks.md).
- Dla CF7: NIGDY SSR — statyczny mock (patterns/forms.md).

## 8. anchor + className gubione po save
Bloki SSR (`save → null`) NIE serializują `anchor`/`className` bez jawnej deklaracji w `attributes`. Zawsze:
```json
"anchor": { "type": "string" }, "className": { "type": "string" }
```

## 9. editor.scss = chrome edytora, NIE kopia style.scss
`style.scss` (w `block.json` jako `style`) WP ładuje **też w iframe edytora** (WP 6.3+) — więc layout, clamp i breakpointy działają w edytorze bez kopiowania. `editor.scss` (`editorStyle`) trzymaj TYLKO na chrome edytora: dashed bordery pickerów, placeholdery, przycisk ✕, korekty gdy edytor renderuje inaczej niż front. **Nie duplikuj clampów** — duplikat = konflikt specyficzności i podwójne utrzymanie.
Gdy blok naprawdę wygląda inaczej w edytorze, przyczyna zwykle NIE jest w editor.scss: constrained szerokość panelu (~500px — testuj wąski), brak `align` w podglądzie, albo inny markup edit.js vs render.php.

## 10. setState po unmount (apiFetch w edit.js)
Fetch w `useEffect` bez cleanup → `setState` po unmount (warning, wyciek). Zawsze flag `cancelled`:
```js
useEffect( () => {
	let cancelled = false;
	apiFetch( {...} ).then( ( d ) => { if ( ! cancelled ) setState( d ); } );
	return () => { cancelled = true; };
}, [ dep ] );
```
Dotyczy navbar (menu-items), forms (lista CF7), każdego dynamic picker.

## 11. Media przez klik w `<img>` = 2 kliki (picker się nie otwiera)
`<img onClick={open}>` jako trigger `MediaUpload` jest zawodny: 1. klik tylko zaznacza blok, picker nie startuje → user „nie widzi edycji". **Reguła:** trigger media = prawdziwy `<button>` (preview w środku, 1 klik), NIGDY goły `<img>`/`<div>` z `onClick`. Pełny wzorzec → `patterns/media-images.md`.

## 12. Prosty `"` w wartości block.json zrywa JSON → webpack build error
Polski opis bloku z ASCII `"` (np. `"description": "„Aktualne działki""`) zamyka string przedwcześnie → cały build pada. **Reguła:** w `block.json` (i każdym JSON) w wartościach NIE używaj prostego `"` — użyj `„…"` (cudzysłowy drukarskie) albo escapuj `\"`. Waliduj przed buildem:
```bash
python3 -c "import json,sys; json.load(open(sys.argv[1]))" blocks/{BLOCK_NAME}/block.json
```
