# Optymalizacja — performance & a11y

Reference (tryb **ad-hoc**): czytaj przy pracy nad wydajnością, Core Web Vitals, dostępnością, deployu na prod.

**Już pokryte w patternach (nie powtarzam):**
- Responsive srcset + LCP `eager`/`fetchpriority`/preload → `patterns/media-images.md`
- Warunkowe ładowanie assetów (CF7 `has_block`, reveal/GSAP lazy) → `patterns/forms.md`, `patterns/animations.md`
- `prefers-reduced-motion`, reveal sticky-safe → `patterns/animations.md`
- SVG sanitizer + mime → `patterns/media-images.md`
- clamp / box-sizing / editor.scss (chrome-only, layout ze style.scss w iframe) → `css-conventions.md`
- Emisja tokenów (plugin) → `plugin-mode.md`

Poniżej rzeczy, których w patternach nie ma.

---

## 1. Cache (LiteSpeed / LSCache)

Konfiguracja pluginu po deployu (typowe wartości, dostosuj do hostingu):
```
Page Cache:        ON, TTL 7–28 dni, Auto Purge na Publish/Update/Delete
Browser Cache:     ON
CSS Minify:        ON
CSS Combine:       ON   (łączy per-blokowe style-index.css → mniej requestów)
Critical CSS (CCSS): ON (inline above-fold → poprawia FCP/LCP)
JS Minify:         ON
JS Combine:        OFF  (gdy są lazy chunki, np. Swiper — combine je psuje)
JS Defer:          ON   (ALE przetestuj wysyłkę CF7 — może wymagać wykluczenia)
Font Display:      Swap
Image Lazy Load:   ON   (WYKLUCZ obraz hero/LCP w Media → Lazy Load Exclude — inaczej plugin sabotuje LCP)
Object cache (Redis/Memcached): ON jeśli host wspiera
```
`.htaccess` (jeśli bez pluginu cache): immutable dla assetów (1 rok), no-cache dla HTML.
```apache
<FilesMatch "\.(css|js|woff2|svg|jpg|jpeg|png|webp|avif)$">
  Header set Cache-Control "public, max-age=31536000, immutable"
</FilesMatch>
```
Po każdej zmianie na prod → **purge cache** (plugin + CDN). Patrz `migracja-prod.md`.

---

## 2. Obrazy — format i waga

**Optymalizacja obrazów = wtyczka, NIE kod w theme.** Filtry PHP w functions.php (`image_editor_output_format` → webp, `wp_editor_set_quality`) są ZABRONIONE: działają tylko dla nowych uploadów, gryzą się z wtyczkami konwertującymi i kończą jako zakomentowany martwy kod (lekcja z wdrożenia).

- **Właściciel formatu: LiteSpeed Cache → Image Optimization** (QUIC.cloud, plugin wymagany — first-run.md). Konwersja webp asynchronicznie po stronie QUIC (omija PHP timeout), serving przez WebP Replacement:
  ```
  Auto Request Cron:       ON
  Optimize Original Images: ON (trzyma backup)
  WebP Replacement:        ON
  WebP For Extra srcset:   ON
  ```
- **JEDEN właściciel webp** — nigdy druga wtyczka konwertująca równolegle. Hosting bez LiteSpeed → EWWW/ShortPixel ZAMIAST (nie obok), z LSCache Image Opt OFF.
- **`-scaled` gotcha:** WP tworzy `{plik}-scaled.{ext}` dla obrazów >2560px (`big_image_size_threshold`). Zmniejsz źródła <2560px przed uploadem → WP nie robi `-scaled`. Oryginał zawsze zostaje na dysku.
- Eksporty @2x z Figmy bywają ogromne — pojedynczy plik >1 MB przepuść przez Squoosh/`cwebp` (jakość 75–82) przed uploadem; resztę załatwia wtyczka.

---

## 3. Fonty (woff2)
- **Tylko woff2** (najlepsza kompresja, wsparcie 98%+). Lokalnie w `assets/fonts/`, deklaracja w `theme.json` → `fontFace`.
- **font-display** per font:
  - `swap` — domyślny (FOUT: system font od razu, swap po pobraniu). Dla treści.
  - `optional` — font użyty tylko jeśli pobierze się błyskawicznie (najlepszy CLS, font może się nie pojawić przy 1. wizycie).
  - `fallback` — krótki FOIT (~100ms) + fallback. Kompromis.
- **Preload** krytycznych fontów (te above-fold, np. nagłówek hero) w `wp_head`:
  ```php
  printf( '<link rel="preload" as="font" type="font/woff2" href="%s" crossorigin>', esc_url( $url ) );
  ```
  Nie preloaduj wszystkich wag — tylko 1–2 krytyczne.
- **Subsetting:** nie ładuj pełnego pliku gdy używasz tylko `latin` + `latin-ext`. `fonttools pyftsubset` tnie wagę nawet 3×.

---

## 4. JS — defer / async / critical CSS
- **`defer`** dla skryptów theme/bloków (wykonanie po parsowaniu, w kolejności). **`async`** dla niezależnych (analytics).
  ```php
  add_filter( 'script_loader_tag', function ( $tag, $handle ) {
  	if ( in_array( $handle, [ '{ns}-reveal', '{ns}-slider' ], true ) ) return str_replace( ' src', ' defer src', $tag );
  	if ( in_array( $handle, [ '{ns}-analytics' ], true ) )           return str_replace( ' src', ' async src', $tag );
  	return $tag;
  }, 10, 2 );
  ```
- **Core block assets osobno** (ładuj CSS tylko używanych bloków):
  ```php
  add_filter( 'should_load_separate_core_block_assets', '__return_true' );
  ```
- **Critical CSS:** above-fold inline w `<style>`, reszta async. Najprościej LSCache CCSS; ręcznie tylko gdy potrzeba pełnej kontroli.
- **Render-blocking:** żaden nieistotny `<script>` w `<head>` bez defer/async.

---

## 5. Core Web Vitals — pomiar
- **LCP** (≤2.5s): preload hero (media-images.md), priorytet fontu nagłówka, brak lazy na obrazie above-fold.
- **CLS** (≤0.1): `aspect-ratio`/wymiary na obrazach, rezerwacja miejsca na walidację formularzy (forms.md), `font-display` rozsądnie, brak wstrzykiwanego contentu nad foldem.
- **INP** (≤200ms): nie blokuj wątku — throttle scroll/drag przez `requestAnimationFrame`, dziel ciężki JS. INP ≠ TBT.
- **Lokalny audyt** (nie tylko PageSpeed prod):
  ```bash
  npx --yes lighthouse "http://localhost:PORT" \
    --only-categories=performance --form-factor=mobile \
    --throttling-method=simulate --output=html --output-path=./lh.html
  ```
- **CLS na żywo** (debug):
  ```js
  new PerformanceObserver( ( l ) => l.getEntries().forEach( ( e ) => {
  	if ( ! e.hadRecentInput ) console.log( 'CLS', e.value, e.sources );
  } ) ).observe( { type: 'layout-shift', buffered: true } );
  ```
- `preconnect`/`dns-prefetch` dla zewnętrznych origin (fonts CDN, analytics).

---

## 6. A11y — checklist
- **Kontrast** WCAG AA: 4.5:1 tekst, 3:1 duży tekst/UI. Sprawdź (DevTools / Stark / WebAIM). Dotyczy też tekstu na tłach-obrazach (overlay).
- **Skip link** przed navbarem:
  ```html
  <a href="#main" class="skip-link">Przejdź do treści</a>
  ```
  ```scss
  .skip-link { position: absolute; left: 0; top: -40px; z-index: 9999; padding: 8px 16px;
  	background: #000; color: #fff; }
  .skip-link:focus { top: 0; }
  ```
  `<main id="main">` na kontenerze treści.
- **`:focus-visible`** na klikalnych (nie usuwaj outline bez zamiennika):
  ```scss
  a, button, input, [tabindex] { &:focus-visible { outline: 2px solid var(--wp--preset--color--accent-blue); outline-offset: 2px; } }
  ```
- **Klawiatura:** wszystko klikalne osiągalne Tabem, Escape zamyka menu/modal, brak focus-trap (patterns/navbar-menu.md).
- **ARIA dla wzorców:** accordion/tabs → `role="tab"/"tabpanel"`, `aria-expanded`, `aria-controls`; ikony bez tekstu → `aria-label`; dekoracje → `aria-hidden="true"`.
- **`prefers-reduced-motion`** → wyłącz animacje (patterns/animations.md).
- **Alt** na obrazach treściowych; `alt=""` na dekoracyjnych.

---

## 7. Baza / WP_Query
- **`no_found_rows => true`** gdy nie potrzebujesz paginacji (pomija `SQL_CALC_FOUND_ROWS`/COUNT). Uwaga: wtedy `max_num_pages` = 0 → dla „load more" policz total osobno albo nie używaj tej flagi.
- **`update_post_meta_cache`/`update_post_term_cache => false`** gdy nie używasz meta/terms w pętli.
- **Cache wyniku** w transient gdy zapytanie ciężkie i rzadko się zmienia:
  ```php
  $key = '{ns}_grid_' . md5( serialize( $args ) );
  $ids = get_transient( $key );
  if ( false === $ids ) { $q = new WP_Query( $args ); $ids = wp_list_pluck( $q->posts, 'ID' ); set_transient( $key, $ids, HOUR_IN_SECONDS ); }
  ```
  Invaliduj na `save_post`. (Dla bloków load-more wzorzec REST → `patterns/dynamic-blocks.md`.)
- **Query Monitor** plugin (dev) — wykrywa wolne/duplikowane zapytania (N+1).
- Nie używaj `posts_per_page => -1` na dużych zbiorach.
