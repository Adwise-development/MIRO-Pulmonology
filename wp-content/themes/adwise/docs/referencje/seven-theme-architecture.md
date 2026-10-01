# WordPress Block Theme

## Project Overview

WordPress block theme. Nazwa i przeznaczenie — odczytaj z `style.css` (Theme Name). Custom React blocks with server-side rendering, built on `@wordpress/scripts` + Webpack 5. Polish language (pl_PL).

## Pliki instrukcji

`CLAUDE.md` jest w roocie projektu. Playbooki są w `docs/`. Auto-load (sekcja na końcu tego pliku, `@import` Claude Code) obejmuje pliki z nagłówkiem **`Auto-load: TAK`** — tu: `docs/project.md` i `docs/login-page.md`. Reszta = referencja ad-hoc (czytaj na żądanie, gdy dotykasz danego tematu).

| Plik | Zawartość | Tryb |
|------|-----------|------|
| **CLAUDE.md** (ten plik) | Zasady, konwencje, decyzje, workflow | Auto-load (root) |
| **docs/project.md** | Stan projektu, kontekst biznesowy, tokeny aktualne, bloki, decyzje per-projekt, rolling log | **Auto-load** (`@import`) |
| **docs/login-page.md** | Workflow custom login URL + branded KV + security hardening. Każdy projekt MUSI mieć | **Auto-load** (`@import`) |
| **docs/block-template.md** | Szablony kodu do scaffoldingu nowych bloków + patterny (Button, Image, Background, Dynamic, Media Remove, Slider, CF7, Content Length Limit, Sticky Navbar, Editable URL Chip, Menu Live Preview, Notched Button, Hamburger 5-line, Top Bar Decoration) | Ad-hoc — czytaj przy tworzeniu/modyfikacji bloku |
| **docs/figma-workflow.md** | Mapowanie tokenów Figma → theme.json, kolejność wywołań API, assety | Ad-hoc — czytaj przy pracy z Figmą |
| **docs/optymalizacja.md** | Reference: checklist performance, image opt, LCP, cache, a11y, FSE template parts | Ad-hoc — performance/deploy/a11y |
| **docs/migracja-prod.md** | Plan migracji na prod (szablon, do adaptacji domeny Seven) | Ad-hoc — przy deployu na prod |

**Na starcie sesji auto-load:** CLAUDE.md + docs/project.md + docs/login-page.md (`@import` poniżej). Stosuj bez przypominania.
**`docs/block-template.md` i `docs/figma-workflow.md` czytaj na żądanie** — gdy budujesz/modyfikujesz blok lub pracujesz z Figmą. (Baza bloków odziedziczona z motywu Grupa Weba — patrz `docs/project.md` sek. 2.)
**Przy tworzeniu/modyfikacji bloku:** plan → akceptacja → kod. NIE implementuj bez planu i zgody usera.

## Architecture

```
blocks/[block-name]/     # Source blocks
  block.json             # Metadata, attributes, supports
  index.js               # Registration (registerBlockType)
  edit.js                # Editor React component
  save.js                # Returns null (server-rendered)
  render.php             # Server-side HTML output
  style.scss             # Frontend styles
  editor.scss            # Editor-only styles
  view.js                # Frontend interactivity (optional)

build/                   # Compiled output (DO NOT EDIT)
assets/fonts/            # Local woff2 fonts
assets/icons/            # SVG ikony per block
assets/images/           # Obrazy per block
templates/               # FSE page templates
parts/                   # FSE template parts (header, footer)
patterns/                # Block patterns
styles/                  # Global style variations
```

## Build System

```bash
npm run start    # Dev mode with watch
npm run build    # Production build
```

Webpack auto-discovers `blocks/*/index.js` and `blocks/*/view.js` entry points. CopyWebpackPlugin copies `block.json` and `*.php` to `build/`.

## Block Conventions

### Supports (obowiązkowe w KAŻDYM bloku)
```json
"supports": {
  "html": false,
  "anchor": true,
  "customClassName": true,
  "align": ["wide", "full"],
  "color": false,
  "spacing": false
}
```
`anchor` i `customClassName` — ZAWSZE true. Bez wyjątków.

**WAŻNE:** Dla dynamic blocks (`save` returns `null`) `anchor` i `className` muszą być JAWNIE zadeklarowane w `attributes`:
```json
"attributes": {
  "anchor": { "type": "string" },
  "className": { "type": "string" },
  ...
}
```
Bez tego WP nie serializuje ich w post_content i wartości gubią się po save.

### Namespace
- Namespace bloków = nazwa folderu theme'u (underscores → hyphens). Odczytaj z filesystem, nie hardcoduj.
- Textdomain = to samo co namespace

### Attribute Types
- **Strings**: text fields with `"default": "..."` (Polish defaults)
- **Objects**: media items `{ id, url, alt }` with `"default": {}`
- **Arrays**: repeating content groups with `"default": [...]`
- **Integers**: numeric IDs

### Inline vs Sidebar — kiedy co:
| Typ pola | Gdzie | Komponent |
|----------|-------|-----------|
| Tekst widoczny (tytuł, cena, opis) | **Inline** | `RichText` |
| Obraz widoczny | **Inline** | `MediaUpload` |
| URL buttona | **Inline** | `Popover` + `LinkControl` |
| Toggle/boolean | **Sidebar** | `ToggleControl` |
| Wybór z listy | **Sidebar** | `SelectControl` |
| Liczba | **Sidebar** | `RangeControl` |

**Zasada:** widoczne na stronie → inline. Konfiguracja → sidebar.

### Repeatable Items
- Renderowane w `.map()` w podglądzie
- "✕" (usuń) widoczny na hover
- "+ Dodaj" pod listą (nie w sidebarze)
- Kod scaffolding → `block-template.md`

### Wybór wielu dynamicznych pozycji (wpisy, terminy, strony, produkty) — ZASADA GLOBALNA
**Jeśli zbiór do wyboru MOŻE przekroczyć ~10–15 pozycji → NIGDY lista `CheckboxControl`.** Przy 100 wpisach checkboxy = fatalna UX + edytor ładuje się wieczność. Zamiast tego:
- **`FormTokenField`** (domyślnie) — wpisujesz tytuł, podpowiedzi, dodajesz tokeny. Lekkie, szybkie, czytelne.
- albo `SelectControl`/combobox z wyszukiwaniem.
- **Fetch tylko `_fields=id,title`** (nie `_embed`, nie pełne obiekty) do listy wyboru — żeby nie ładować megabajtów.
- Mapuj tytuł↔id po stronie edytora; `postIds`/`termIds` trzymaj jako tablicę id.
Przewiduj to z góry przy KAŻDYM dynamicznym elemencie z wyborem (slider wpisów, powiązane produkty, wybór kategorii itd.) — skala potrafi urosnąć.

### Usuwanie mediów (ikony, zdjęcia, logo)
KAŻDY `MediaUpload` (ikona, zdjęcie, logo) MUSI mieć przycisk usuwania "✕".

**Styl przycisku (jednolity dla całego projektu):**
```scss
// 18x18, czerwone tło, okrągły, hover show
width: 18px; height: 18px;
background: rgba(220, 38, 38, 0.8);
color: #fff; border: none; border-radius: 50%;
font-size: 9px; display: none; // widoczny na hover wrappera
```

**Struktura:**
- Ikony: `{PREFIX}__icon-wrap` (relative) → `{PREFIX}__icon-remove` (absolute, top: -6px, right: -6px)
- Zdjęcia: `{PREFIX}__image-wrap` (relative) → `{PREFIX}__image-remove` (absolute, top: 8px, right: 8px)
- Hover na wrapperze → `display: flex` na buttonie
- Klik → `updateItem(i, 'icon', {})` lub `setAttributes({ image: {} })`
- Kod scaffolding → `block-template.md` (Pattern: Media Remove Button)

### Block Type Decision Guide
| Pytanie | Statyczny | Dynamiczny |
|---------|-----------|------------|
| Skąd dane? | Atrybuty block.json | WP_Query / REST API |
| Podgląd? | RichText, MediaUpload | `ServerSideRender` |
| render.php? | Wyświetla `$attributes` | Wykonuje `WP_Query` |
| view.js? | Tylko animacje/slider | Load more / AJAX |

### Warianty zamiast duplikacji
Jeśli nowy blok z Figmy różni się od istniejącego TYLKO kolorystyką/tłem — NIE twórz nowego bloku. Dodaj atrybut `variant` (string, SelectControl w sidebar) i klasę CSS modifier (np. `sh--dark`). Zero duplikacji.

### FSE Template Parts
Plik `parts/*.html` = single source of truth dla git workflow. **NIE** edytować w Site Editor (Wygląd → Edytor) — tworzy DB post `wp_template_part` niewidoczny w git. Edycja atrybutów bloków (logo, menuId, buttonUrl) → bezpośrednio w pliku.
**Wyjątek:** programatic override przez Novamira (`wp_insert_post` typu `wp_template_part`) jest dozwolony gdy user wyraźnie prosi. Wtedy odnotuj w `project.md` (sekcja "Header / Footer") post ID + content, żeby przyszłe sesje wiedziały o override. Reset → usuń post po ID lub zsynchronizuj content z plikiem `parts/`.
Szczegóły i reset DB override: `optymalizacja.md` sekcja 7.

### Kiedy który Pattern (kod w `block-template.md`):
- **Background Image** — zdjęcie jako tło kontenera (hero, overlay)
- **Image Element** — zdjęcie jako samodzielny element (karta, galeria)
- **Button z LinkControl** — klikalny button z URL-em + animowana strzałka hover (arrow-1/arrow-2 translateX). Dla buttonu z dekoracyjnymi krawędziami → użyj **Notched Button**.
- **Dynamic Block** — dane z WP_Query + AJAX load more
- **Media Remove Button** — przycisk usuwania na każdym MediaUpload
- **Slider (Swiper)** — karuzela zdjęć z thumbs (np. testimonials)
- **CF7 Form** — integracja z Contact Form 7 (preview w edytorze, NIE SSR)
- **Toggle Select Inline + Remove** — wybór wartości (taxonomy, post type) z ✕ remove, bez preview items. **Dla WP Menu z preview linków → użyj Menu Live Preview**.
- **Sidebar Settings** — InspectorControls z RangeControl/SelectControl dla konfiguracji (delay, slidesPerView itp.)
- **Photo Pair** — 2 zdjęcia full-width edge-to-edge w aspect-ratio
- **Sticky Navbar** — top bar static + main sticky (NIE pure `fixed`). Bez body padding-top.
- **Editable URL Chip** — RichText (tekst) + chip "URL" (Popover LinkControl) dla tel/mailto gdzie tekst niezależny od href
- **Menu Live Preview** — WP Menu z prawdziwymi linkami w edytorze (apiFetch menu-items)
- **Notched Button** — CTA z bocznymi wcięciami (białe SVG edges) + 2 statyczne strzałki flankujące tekst
- **Hamburger 5-line + Label** — 5 cienkich kresek + tekst "Menu" + toggle X gdy `is-open`
- **Top Bar Decoration** — `flex:1` linia pozioma + SVG "grzebień" na końcu

### Assety (ikony, obrazy) z Figmy
- **NIE pobieraj** ikon/obrazów do `assets/` jeśli user uploaduje je przez MediaUpload — trafiają do `wp-content/uploads/`, nie do theme'u
- **Pobieraj** do `assets/icons/` TYLKO ikony hardcodowane inline w render.php (np. stały SVG w szablonie bloku)
- Zasada: jeśli atrybut w block.json to `object { id, url, alt }` → MediaUpload → NIE pobieraj do assets

### Warianty kolorystyczne (jasny/ciemny)
- NIE polegaj na `color` na wrapperze i dziedziczeniu CSS
- Explicite ustaw kolory na KAŻDYM elemencie tekstowym w wariancie

## CSS Conventions

### BEM Naming
- Wrapper: `.wp-block-{namespace}-[block-name]` (auto WP)
- Elements: `.prefix__element`
- Modifiers: `.prefix__element--variant`
- State: `.is-scrolled`, `.is-open`

### Responsywność
**Figma dostarcza tylko widok desktop (1440px) i mobile.** Widok mobile stosujemy już od 1024px (tablet = mobile layout). Drugi breakpoint (768px) tylko gdy coś wymaga dodatkowej korekty.

```scss
@media (max-width: 1024px) { /* tablet+mobile — layout mobilny */ }
@media (max-width: 767px)  { /* tylko drobne korekty jeśli potrzeba */ }
```

### Fluid values (clamp)
Font-size, padding, gap — ZAWSZE `clamp()` zamiast stałych px. Wartość skaluje się płynnie między breakpointami.

```scss
// clamp(min, preferred, max)
// min = wartość z Figmy mobile (osiągana na 768px)
// max = wartość z Figmy desktop (osiągana na 1440px)
// preferred = obliczone vw między 768px a 1440px

// Wzór na preferred: min + (max - min) * ((100vw - 768px) / (1440 - 768))
// Skrót: clamp(MOBILEpx, calc(MOBILEpx + (DESKTOP - MOBILE) * ((100vw - 768px) / 672)), DESKTOPpx)

// Przykład: font-size desktop 40px, mobile 24px
font-size: clamp(24px, calc(24px + 16 * ((100vw - 768px) / 672)), 40px);

// Przykład: padding desktop 64px, mobile 24px
padding: clamp(24px, calc(24px + 40 * ((100vw - 768px) / 672)), 64px);

// Przykład: gap desktop 48px, mobile 24px
gap: clamp(24px, calc(24px + 24 * ((100vw - 768px) / 672)), 48px);
```

**Zasady:**
- Desktop max = wartość z Figmy desktop (widoczna na 1440px+)
- Mobile min = wartość z Figmy mobile (widoczna na 768px-)
- Między 768–1440px wartość skaluje się płynnie
- Breakpoint `@media (max-width: 1024px)` zmienia LAYOUT (flex-direction, grid-columns), NIE wartości liczbowe — za to odpowiada `clamp()`

### Layout: sekcja vs inner wrapper
Każdy blok MUSI mieć strukturę:
```
<section class="wp-block-...">   ← zero padding, tło edge-to-edge, WP kontroluje szerokość
  <div class="{PREFIX}__inner">  ← max-width: 1440px, margin: 0 auto, padding: horizontal + vertical
    treść...
  </div>
</section>
```
**NIGDY** nie dawaj padding na `<section>`. Padding TYLKO na `__inner`. Tło (kolor, obraz) na sekcji — rozciąga się na pełną szerokość.

### Full-width bloki (bez max-width)
Bloki, których content sięga krawędzi ekranu (mapy, slidery, sekcje ze zdjęciami edge-to-edge) NIE mają `max-width: 1440px` na `__inner`. Inner ma tylko padding (lub zero padding).

Przy analizie Figmy: jeśli element dotyka krawędzi w widoku 1440px → pytaj usera o full-width.

### Prefiksy CSS
Każdy blok ma unikalny 2-5 literowy prefix CSS. Odczytaj z istniejących plików SCSS bloku, nie wymyślaj nowych jeśli blok już istnieje.

### Editor SCSS = Frontend SCSS
`editor.scss` MUSI zachowywać się identycznie jak `style.scss` pod względem responsywności. Wszystkie `clamp()`, breakpointy `@media`, `box-sizing` — kopiuj do editor.scss. Edytor WP zmienia szerokość panelu — blok musi reagować tak samo jak na froncie.

### Box-sizing
Każdy element z `padding` + `width: 100%` (lub constraint od rodzica) MUSI mieć `box-sizing: border-box`. Bez tego padding dodaje się do szerokości i element overflow'uje poza rodzica. Dotyczy szczególnie: kontenerów wewnętrznych (`__left`, `__right`, `__box`), CTA wrapperów, kart.

### Anchory i scroll
- Globalny `scroll-behavior: smooth` + `scroll-margin-top` na `[id]` — dodane w `functions.php` (inline style). Nie trzeba dodawać per blok.
- Navbar używa `position: sticky` (top bar static + main sticky) — `body padding-top` NIE jest potrzebne. Patrz `block-template.md` Pattern: Sticky Navbar.
- Filter `render_block` w `functions.php` wstrzykuje `id` z anchora do pierwszego tagu HTML — `get_block_wrapper_attributes()` NIE robi tego automatycznie w WP.
- **UWAGA:** filter sprawdza `id=` TYLKO w pierwszym tagu. Bloki z zagnieżdżonym `id` (np. CF7 `id="wpcf7-..."`) nie kolidują z anchorem sekcji.

### Stała width + overflow
Elementy ze stałą `width` (np. button 336px) MUSZĄ mieć `max-width: 100%` + `box-sizing: border-box`. Bez tego na wąskim ekranie element wykracza poza rodzica.

### Background image zamiast `<img>` dla sekcji z overlay
Gdy zdjęcie jest tłem sekcji a inne elementy (karty, tekst) siedzą NA nim — użyj `background-image` (inline style z render.php) zamiast osobnego `<img>` w `<div>`. Dzięki temu:
- Na desktop: elementy overlay'ują zdjęcie naturalnie
- Na mobile: ten sam background, zmienia się tylko układ elementów (np. `padding-top` odsłania zdjęcie u góry)
- Nie trzeba przełączać między `<img>` a `background-image` na breakpoincie

```php
// render.php — inline style z CSS variable lub bezpośrednio
<div class="{PREFIX}__left" style="background-image: url(<?php echo esc_url( $image['url'] ); ?>)">
```

### Animacja strzałki na buttonach
Buttony z hover arrow animation mają dwa spany: `arrow-1` (widoczny) + `arrow-2` (ukryty). Na hover `arrow-1` wyjeżdża, `arrow-2` wjeżdża. Na mobile (@1024px) `arrow-2` jest `display: none` + hover transform na `arrow-1` wyłączony — brak animacji, strzałka widoczna na stałe.

```scss
.{PREFIX}__btn-arrow { position: relative; overflow: hidden; width: 20px; }
.{PREFIX}__btn-arrow-2 { position: absolute; transform: translateX(-22px); }
.{PREFIX}__btn:hover .{PREFIX}__btn-arrow-1 { transform: translateX(22px); }
.{PREFIX}__btn:hover .{PREFIX}__btn-arrow-2 { transform: translateX(0); }
@media (max-width: 1024px) {
  .{PREFIX}__btn-arrow-2 { display: none; }
  .{PREFIX}__btn:hover .{PREFIX}__btn-arrow-1 { transform: none; }
}
```

### iOS / mobile — interaktywne elementy
- Buttony i elementy klikalne MUSZĄ mieć jawny `color` + `-webkit-tap-highlight-color: transparent`. iOS Safari domyślnie koloruje buttony na niebiesko (accent color) i dodaje niebieski flash przy tapnięciu.
- SVG z `stroke="currentColor"` dziedziczy kolor — bez jawnego `color` na rodzicu będzie niebieski na iOS.

### Hover tylko na desktop
Hover efekty na **linkach i CTA na froncie** (render.php/style.scss), które zmieniają kolor lub transform, opakowuj w `@media (hover: hover)`. Na urządzeniach dotykowych `:hover` "przykleja się" po tapnięciu — element zmienia stan i nie wraca.
NIE dotyczy: edytorowych UI (placeholdery, remove buttony, sidebar) — te działają tylko na desktop.

```scss
@media (hover: hover) {
  &:hover { color: var(--wp--preset--color--accent-blue); }
}
```

### Linki tel: / mailto:
Numery telefonów i adresy email renderuj jako `<a href="tel:">` / `<a href="mailto:">` z jawnym `color` i `text-decoration: none`. NIE używaj meta tagów `format-detection` — link jest lepszy (klikalny + kontrolowany kolor).

### Kontenery — bez sztywnego height
NIE ustawiaj `height` na kontenerach flex (navbar inner, sekcje). Niech content + padding determinują wysokość naturalnie. Sztywny `height` powoduje overflow gdy content rośnie (np. walidacja formularza, dłuższy tekst).

### Formularze — walidacja bez layout shift
Komunikaty błędów (CF7 `.wpcf7-not-valid-tip`) renderuj jako `position: absolute` pod inputem. Pole formularza (`__field`) dostaje `position: relative` + `padding-bottom` na rezerwację przestrzeni. Dzięki temu pojawienie/zniknięcie błędu NIE przesuwa layoutu sekcji.

```scss
.{PREFIX}-cf7__field { position: relative; padding-bottom: 16px; }
.{PREFIX}__form .wpcf7-not-valid-tip { position: absolute; bottom: -20px; left: 0; }
```

## Design Tokens

**Flow:** Figma → `theme.json` → `project.md` (aktualne mapowania) + `figma-workflow.md` (reguły)

1. Tokeny (kolory, fonty, spacing) pobieramy z Figmy (`get_design_context`)
2. Zapisujemy wartości w `theme.json` (single source of truth)
3. **Reguły mapowania** (jak nazywać slugi, anti-patterny) → `figma-workflow.md`
4. **Aktualne mapowania** (slug → wartość → rola Figma w tym projekcie) → `project.md` sekcja 3
5. Po dodaniu/zmianie tokena → zaktualizuj `project.md` sekcja 3 (`figma-workflow.md` NIE przechowuje wartości)

## PHP Conventions

### Naming
- Prefix PHP = skrót nazwy theme'u. Odczytaj z `functions.php`, nie hardcoduj.
- Textdomain = namespace bloków

### Block Registration
Auto-discovery via `glob()` in `functions.php` — blocks register from `build/blocks/*/block.json`.

## Dependencies

- `@wordpress/scripts` ^30.0.0 - build tooling
- `gsap` ^3.14.2 - scroll/reveal animations
- `swiper` ^12.1.3 - carousels/sliders

## Token-Optimized Workflow

### Nie ładuj skilli automatycznie
- NIE używaj skilli `brainstorming`, `figma-implement-design`, `using-superpowers`
- Jedyny skill Figma: `figma:figma-use` przed wywołaniem `use_figma`
- Audyt plików MD: używaj `claude-md-management:claude-md-improver` — NIE poprawiaj ręcznie bez zgody usera

### Figma — zasady oszczędności tokenów
Szczegóły w `figma-workflow.md`. Skrót:
1. ZAWSZE `get_screenshot` najpierw
2. NIGDY `get_design_context` na całej stronie
3. `get_design_context` TYLKO na 1 elemencie
4. Assety: pobieraj natychmiast, nie wymyślaj zamienników

### Nowy blok — wymagane pytania (WSZYSTKIE naraz)
- Typ danych? (statyczne / dynamiczne / external API)
- Jeśli dynamiczne: skąd? (CPT, taxonomy, endpoint)
- Interaktywność? (brak / load more / slider / accordion / tabs)
- Responsywność: kolumny desktop → tablet → mobile?
- Pola na karcie? (image, title, excerpt, link, custom fields)
- Klikalność? Co jest linkiem?
- Content full-width (edge-to-edge) czy opakowany (max-width 1440px)?

### Workflow: design → plan → akceptacja → kod
1. `get_screenshot` → analiza wizualna
2. `get_design_context` na 1 elemencie → wartości CSS (UWAGA: mobile design context może zwracać odziedziczone desktop wartości — weryfikuj ze screenshotem)
3. Pytania do użytkownika (wszystkie naraz)
4. **Plan** — tabela wartości clamp (desktop→mobile), lista zmian layout @1024px, atrybuty, struktura. NIE implementuj bez planu.
5. **Czekaj na akceptację** — NIE implementuj bez zgody
6. Po akceptacji → sprawdź checklist z `block-template.md` i `figma-workflow.md` → implementacja
7. Po implementacji → build → test edytor (desktop + wąski panel) → test frontend (desktop + mobile)

### Zasady BEZWZGLĘDNE (nie trzeba przypominać)
- Plan przed kodem — ZAWSZE
- `editor.scss` = `style.scss` — ZAWSZE synchronizuj
- `box-sizing: border-box` — ZAWSZE na elementach z padding + width
- `max-width: 100%` — ZAWSZE na elementach ze stałą width
- `anchor` + `className` — ZAWSZE jawnie w attributes
- Sekcje z overlay → `background-image` zamiast `<img>`
- Arrow animation → `arrow-2 display: none` + `arrow-1 transform: none` @1024px
- Clamp na wartościach, breakpoint TYLKO na layout
- Buttony/klikalne → jawny `color` + `-webkit-tap-highlight-color: transparent`
- Hover efekty na froncie → `@media (hover: hover)` (nie gołe `:hover`)
- Tel/email → `<a href="tel:/mailto:">` z jawnym kolorem, nie meta tagi
- Kontenery → bez sztywnego `height`, niech content rozpycha
- Walidacja formularzy → `position: absolute`, bez layout shift
- Sticky navbar → top bar static + main `position: sticky; top:0` (NIE pure `fixed`, NIE body padding-top)
- Inline SVG z Figmy → zamień `fill="var(--fill-0, ...)"` na `fill="currentColor"` (kontrola koloru przez CSS rodzica)

### Nie eksploruj projektu
Struktura bloków identyczna (patrz Architecture). Nie trzeba sprawdzać agentem.

## Bootstrap nowego projektu (theme od zera)

Minimalny block theme z Webpackiem — **7 plików**. Tokeny dodajemy po analizie Figmy.

| Plik | Co zawiera |
|------|------------|
| `style.css` | Nagłówek theme — zero CSS |
| `theme.json` | Pusty szkielet `version: 3` — tokeny po Figmie |
| `functions.php` | Stałe + setup + glob auto-discovery |
| `package.json` | devDeps: `@wordpress/scripts`, `copy-webpack-plugin` |
| `webpack.config.js` | Auto-discovery entry points + CopyWebpackPlugin |
| `templates/index.html` | header → main → footer |
| `.gitignore` | `node_modules/`, `build/`, `.DS_Store` |

### Kolejność po bootstrapie
1. `npm install` → `npm run build` (weryfikacja)
2. Aktywacja theme
3. Figma → `theme.json` tokeny
4. Fonty → `assets/fonts/`
5. Bloki wg `block-template.md`

## Important Notes

- Never edit files in `build/`
- GSAP/Swiper only in blocks that need them (view.js)

## Auto-load docs

@docs/project.md
@docs/login-page.md
