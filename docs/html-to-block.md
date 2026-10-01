# HTML-to-Block Workflow

Proces konwersji statycznego HTML/CSS/JS na bloki WordPress block theme. Plik **ad-hoc** — ładowany tylko gdy user daje HTML do konwersji. Plik **uniwersalny** — nie hardcoduje tokenów, slugów ani patternów konkretnego projektu. Wartości zawsze odczytuj z `theme.json` / `CLAUDE.md` / `block-template.md` projektu.

Obsługuje dwa tryby:

- **Tryb A — Single component**: fragment HTML (jedna sekcja) → 1 blok
- **Tryb B — Whole page**: cały HTML (landing, podstrona) → wiele bloków + `wp_insert_post`

---

## Kiedy ten flow

**Trigger:**
- User daje plik `.html` (lokalny lub repo)
- User wkleja fragment HTML w wiadomości
- User daje URL statycznej strony lub template'u
- User pisze: "skonwertuj ten HTML", "zrób bloki z tego templatu", "ten landing przepisz na bloki", "z tego mockupu zrób stronę"

**NIE używaj** gdy:
- Source to Figma → użyj ścieżki Figma (`figma-to-block.md`)
- User chce modyfikację istniejącego bloku → standardowy workflow z `CLAUDE.md`
- User pyta o pojedynczą poradę CSS/JS bez intencji konwersji

---

## Założenia projektowe (źródła prawdy)

Plik nie zna konkretnego projektu. Przed implementacją odczytaj:

| Co | Skąd |
|---|---|
| Namespace bloków, prefix PHP, textdomain | `style.css` Theme Name + `functions.php` (lub `CLAUDE.md`) |
| Tokeny (kolory, fonty, font-sizes, spacing) | `theme.json` projektu (NIE z tego pliku) |
| Konwencje CSS (BEM, prefix, breakpointy, clamp wzór) | `CLAUDE.md` projektu |
| Patterny scaffoldingu (Button, Slider, CF7, Modal, Navbar, ...) | `block-template.md` projektu |
| Reguły responsywności (layout breakpoint, max-width) | `CLAUDE.md` projektu |
| Workflow per-blok (plan → akceptacja → kod) | `CLAUDE.md` projektu |

Jeśli pliku/sekcji brak → zapytaj usera (NIE wymyślaj).

---

## Placeholdery

- `{namespace}` — namespace theme (z filesystem)
- `{NAMESPACE_UPPER}` — UPPERCASE prefix PHP const
- `{BLOCK_NAME}` — kebab-case nazwa nowego bloku
- `{PREFIX}` — krótki skrót CSS bloku (z konwencji projektu)
- `{page-slug}` — URL slug strony (Tryb B)
- `{Page Title}` — tytuł strony (Tryb B)
- `{LAYOUT_BREAKPOINT}` — breakpoint zmiany layoutu (z CLAUDE.md projektu, np. 1024px)
- `{CONTENT_MAX}` — max szerokość wrapperów (z CLAUDE.md projektu, np. 1440px)
- `{FLUID_MIN}` — początek skalowania fluid wartości (typowo 768px)
- `{FLUID_MAX}` — koniec skalowania fluid wartości (typowo 1440px)

---

## 1. Source intake

### Akceptowane formaty
| Format | Tool |
|---|---|
| Plik lokalny `.html` | `Read` (absolute path) |
| Fragment wklejony w wiadomości | bezpośrednio z kontekstu |
| URL `https://...` | `WebFetch` z prompt-em filtrującym |
| Repo / folder z `index.html` + assets | `Read` per plik, `Bash ls` na strukturze |

### Towarzyszące pliki
- **CSS**: `<link rel="stylesheet">`, inline `<style>`, atrybut `style=""`
- **JS**: `<script src="">`, inline `<script>`
- **Obrazy**: `<img src>`, `background-image: url()`, `srcset`/`<picture>`
- **Fonty**: `@font-face`, `<link>` Google Fonts, lokalne `woff2`
- **Ikony**: inline SVG, font-icon (FontAwesome/Material), CSS sprite

### OSZCZĘDNOŚĆ TOKENÓW
1. **Czytaj `<body>` selektywnie** — pomiń `<head>` poza `<title>`, meta viewport, font links
2. **CSS wczytuj per sekcja** — przy mapowaniu konkretnego bloku grep po class names używanych w sekcji
3. **JS wczytuj per pattern** — gdy widzisz `Swiper`/`Slick`/`accordion`, czytaj fragmenty inicjalizujące, nie całą bibliotekę
4. **WebFetch z prompt-em** — gdy user daje URL, poproś w prompt-cie o wyciąg sekcji + CSS, nie 1:1

---

## 2. Rozpoznanie struktury HTML (tanie)

### Kolejność
1. **Read pliku / WebFetch URL** → cały `<body>`
2. **Identyfikacja sekcji top-level** — szukaj:
   - `<header>`, `<footer>`, `<main>`
   - `<section>` z `id`/`class`
   - `<div class="section|hero|container">`
   - Komentarze HTML `<!-- Hero -->`, `<!-- Features -->`
3. **Lista sekcji** — od góry do dołu, z nazwą roboczą

### Tabela mapowania

| # | Sekcja (class/id) | Status | Blok docelowy | Notatki |
|---|---|---|---|---|
| 1 | `.hero-banner` | istnieje | `{namespace}/hero` | użyć jak jest |
| 2 | `#features` | wariant | `{namespace}/features` (variant) | inny kolor tła |
| 3 | `.testimonials-slider` | nowy | `{namespace}/testimonials` | slider, 3 itemy |
| 4 | `<footer>` | pomijaj | — | template part |

### Wskazówki
- Wiele stron (`index.html` + `about.html`) → każda osobno (każda = Tryb B)
- HTML ma `<header>`/`<footer>` z navbarem/stopką → **pomijaj** (są w `parts/header.html`, `parts/footer.html`)
- Sekcja z elementem w wielu wariantach obok siebie (3 karty, 5 itemów) → atrybut `array`, nie 5 bloków

---

## 3. Klasyfikacja sekcji — istniejący / wariant / nowy

Dla każdej sekcji **przed planem** zdecyduj kategorię:

### Istniejący blok
- Layout/struktura content **identyczna** z istniejącym blokiem w `blocks/*`
- Akcja: użyj `{namespace}/{istniejący-blok}`, user uzupełni atrybuty w edytorze
- **NIE twórz nowego bloku**

### Wariant istniejącego
- Layout identyczny ale różni się **tylko**: kolor tła/tekstu, ikona/dekoracja, wielkość kontenera
- Akcja: dodaj atrybut `variant` (SelectControl sidebar) + klasa CSS modifier
- Patrz `block-template.md` (sekcja warianty kolorystyczne, jeśli istnieje)
- **Zapytaj usera**: "Sekcja [X] wygląda jak wariant [Y]. Wariant czy nowy blok?"

### Nowy blok
- Layout/struktura unikalna (brak odpowiednika w `blocks/*`)
- Akcja: scaffolding nowego bloku z `block-template.md` + plan

---

## 4. Mapowanie CSS → theme.json

**Tokeny ZAWSZE odczytuj z `theme.json` projektu.** NIE używaj nazw slugów z tego pliku — projektowe slugi są inne (np. `primary-soft`, `surface-soft`, `body-base`, `display-lg`).

### Procedura
1. Odczytaj `theme.json` (`settings.color.palette`, `settings.typography.fontSizes`, `settings.typography.fontFamilies`, `settings.spacing.spacingSizes`)
2. Dla każdej wartości CSS w HTML znajdź **semantycznie najbliższy** slug
3. Jeśli brak odpowiednika (różnica > 10% / brak roli) → zapytaj usera czy dodać nowy slug
4. **NIE hardcoduj** wartości w SCSS bloków — używaj `var(--wp--preset--color--{slug})` itp.

### Semantyczne mapowanie (orientacyjne)
| CSS w HTML | Szukaj slug-a oznaczającego |
|---|---|
| ciemny tekst (#000, #111, #212529) | główny tekst (np. `primary`, `text`, `foreground`) |
| białe tło (#fff) | tło bazowe (np. `base`, `background`, `white`) |
| jasne tło (#f5f5f5, #fafafa) | surface/soft variant (np. `surface-soft`, `tertiary`) |
| szary tekst (#666, #888) | tekst drugorzędny (np. `muted`, `secondary`) |
| obramowanie (#dee2e6, #e0e0e0) | border (np. `border-soft`, `border`) |
| kolor brandu (accent) | accent (np. `accent-*`, `brand-*`) |
| czerwony błąd | error/danger (jeśli zdefiniowany) |

**Jeśli paleta projektu nie ma roli (np. brak `error`)** → zapytaj usera czy dodać slug zgodny z konwencją nazewniczą projektu (sprawdź wzór nazw w istniejących slugach przed propozycją).

### Typografia (font-sizes)

Mapuj `font-size` CSS → najbliższy slug `theme.json`. **Nazwy slugów per projekt** (np. `body-base`, `display-lg` lub `small`, `medium`, `large`). Sprawdź `theme.json` przed mapowaniem.

### Fonty (`font-family`)

Odczytaj z `theme.json` → `settings.typography.fontFamilies`. HTML używa nowego fontu → zapytaj usera:
1. **Lokalnie (preferowane, GDPR safe)**: pobierz `.woff2` (np. `google-webfonts-helper`) → `assets/fonts/{family}/` + `@font-face` w `theme.json` lub `styles/font-face.css` + slug w `settings.typography.fontFamilies`
2. **Google Fonts CDN**: enqueue w `functions.php` (szybsze, ale tracking + brak GDPR)

```css
@font-face {
  font-family: 'Inter';
  src: url('assets/fonts/inter/inter-400.woff2') format('woff2');
  font-weight: 400;
  font-display: swap;
}
```
> ⚠️ **`@font-face` przez theme.json `fontFace[]` → DODAJ `"fontDisplay": "swap"`** w każdym wpisie. WP domyślnie generuje `font-display: fallback` → user widzi system-ui („zły font") przy wolnym ładowaniu. Variable font → blokom dawaj `font-variation-settings: 'wght' N` (dokładna grubość z `styles.css`), nie sam `font-weight`. Szczegóły → `css-conventions.md` §Fonty.

### Spacing

Mapuj `padding`/`margin`/`gap` → najbliższy slug `theme.json` (`settings.spacing.spacingSizes`). **Nazwy slugów per projekt.** Jeśli projekt używa `clamp()` w slugach spacing → zostaw token. Jeśli wartość liczbowa → użyj `clamp()` per sekcja 6.

---

## 5. Mapowanie HTML element → block attributes

Decyzja per pole — referuj tabelę "Inline vs Sidebar" z `CLAUDE.md` projektu (lub równoważną):

| Element HTML | Atrybut bloku | Komponent edit.js | Default |
|---|---|---|---|
| `<h1>`, `<h2>` text | `heading` (string) | `RichText` inline | tekst z HTML |
| `<p>` opis widoczny | `description` (string) | `RichText` inline | tekst z HTML |
| `<span>` decoracja zmienna | `eyebrow`/`tag` (string) | `RichText` inline | tekst z HTML |
| `<img src alt>` | `image` (object `{id,url,alt}`) | `MediaUpload` inline | `{}` (user uploaduje) |
| `<a href class="btn">` | `ctaText` + `ctaUrl` + `ctaOpensInNewTab` | `RichText` + `Popover` + `LinkControl` | tekst + `#` + `false` |
| `<div class="card">` powtarzalne | `items` (array) | `.map()` z + Add / ✕ remove | jeden item placeholder |
| Inline SVG ikona **stała** | brak — hardcode w `render.php` | brak | — |
| Inline SVG ikona **zmienna** per item | `icon` (object) | `MediaUpload` (SVG enabled) | `{}` |
| `<input type="checkbox">` toggle JS | atrybut boolean | `ToggleControl` sidebar | wartość z HTML |
| `<select>` dropdown JS | atrybut string + opcje | `SelectControl` sidebar | pierwsza opcja |
| Liczba (`data-count="5"`) | atrybut integer | `RangeControl` sidebar | wartość z HTML |

### Reguła: treść z HTML = default, NIE hardcode
- Tekst z HTML → `default` atrybutu w `block.json`
- Render: `RichText` w edytorze, `<?php echo esc_html($attributes['heading']) ?>` w `render.php`
- **Wyjątek:** treść identyczna na każdej instancji (separator, label "Strona główna" w breadcrumbs) → hardcode w `render.php`

### Reguła: minimal defaults
Dekoracja w HTML (badge "NEW!", suffix "tylko teraz") którą user prawdopodobnie usunie → **NIE dodawaj atrybutu**. Lepszy mniejszy blok.

| Złe | Lepsze |
|---|---|
| `pricePrefix: "od"` + `priceValue: "99 zł"` | `price: "od 99 zł"` (RichText, user wpisuje całość) |

---

## 6. Responsywność: CSS media queries → `clamp()`

HTML typowo ma wiele breakpointów (576, 768, 992, 1200, 1440). Wzór `clamp()` i breakpoint layoutowy **odczytaj z `CLAUDE.md` projektu** (sekcja responsywność / fluid values). Fallback gdy CLAUDE.md nie definiuje:

- Layout breakpoint: `@media (max-width: {LAYOUT_BREAKPOINT})` (np. 1024px)
- Fluid range: `{FLUID_MIN}`–`{FLUID_MAX}` (typowo 768–1440 = mobile cutoff → desktop target)
- Wzór clamp: `clamp(MOBILE, calc(MOBILE + (DESKTOP - MOBILE) * ((100vw - {FLUID_MIN}) / ({FLUID_MAX} - {FLUID_MIN}))), DESKTOP)`

**Wartości `{FLUID_MIN}`/`{FLUID_MAX}` per projekt** — odczytaj z istniejącego `clamp()` w `theme.json`/SCSS bloków lub z CLAUDE.md. Przykłady poniżej używają 768/672 jako fallback typowy.

### Krok 1 — wyciągnij wartości desktop + mobile
```css
.hero__heading { font-size: 24px; }
@media (min-width: 768px)  { .hero__heading { font-size: 32px; } }
@media (min-width: 1200px) { .hero__heading { font-size: 48px; } }
```
→ desktop = 48px (największy breakpoint), mobile = 24px (default mobile-first)

### Krok 2 — clamp
```scss
font-size: clamp(24px, calc(24px + 24 * ((100vw - 768px) / 672)), 48px);
```

### Krok 3 — layout breakpoint
- `flex-direction`, `grid-template-columns`, `display: none` → `@media (max-width: {LAYOUT_BREAKPOINT})` z konwencji projektu
- HTML mobile-first (`min-width`) → konwertuj na desktop-first (`max-width`)

### Mobile-first → desktop-first
```css
/* HTML mobile-first */
.grid { grid-template-columns: 1fr; }
@media (min-width: 1024px) { .grid { grid-template-columns: repeat(3, 1fr); } }
```

```scss
/* Theme desktop-first */
.{PREFIX}__grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: clamp(16px, calc(16px + 16 * ((100vw - 768px) / 672)), 32px);
}
@media (max-width: {LAYOUT_BREAKPOINT}) { .{PREFIX}__grid { grid-template-columns: 1fr; } }
```

### Wartości w HTML jako `vw` / `%` / `rem` / `em`
- `vw` — zachowaj jeśli pasuje do clamp
- `rem` — przelicz przez 16px (`1.5rem` = 24px)
- `%` — zostaw jeśli layout (`width: 50%`)
- `em` — przelicz względem rodzica

---

## 7. Assety

### Obrazy
| Source w HTML | Akcja |
|---|---|
| `<img src="hero.jpg">` widoczny w widoku | `image` (object). **NIE pobieraj** — user uploaduje. URL z HTML w planie jako referencję, default `{}` |
| `<img src="logo.svg">` w navbarze (stały) | Pobierz do `assets/icons/navbar/logo.svg`, hardcode w `render.php` |
| `background-image: url('bg.jpg')` sekcji | `backgroundImage` (object). **NIE pobieraj** |
| `background-image: url('decoration.svg')` dekoracja stała | Pobierz do `assets/images/{block-name}/`, hardcode w SCSS |
| `<img srcset>` / `<picture>` | `image` — WP generuje srcset/picture przez `wp_get_attachment_image()` |
| `loading="lazy"` | `wp_get_attachment_image($id, 'full', false, ['loading' => 'lazy'])` |

### Pobieranie batch
```bash
DEST="assets/images/{block-name}"; mkdir -p "$DEST"
while IFS= read -r url; do
  [ -z "$url" ] && continue
  # Usuń query string (?w=800) PRZED basename, inaczej nazwa pliku będzie błędna:
  fname="$(basename "${url%%\?*}")"
  curl -fsSL -o "$DEST/$fname" "$url"
done <<'EOF'
https://source.example.com/image1.jpg?w=800
https://source.example.com/image2.jpg
EOF
```

### SVG inline
| Wariant | Akcja |
|---|---|
| SVG inline w HTML, używany **raz**, stały | Wklej bezpośrednio do `render.php` |
| SVG inline w HTML, używany **wielokrotnie** w bloku | `assets/icons/{block-name}/icon-name.svg` + `<?php echo file_get_contents(...) ?>` |
| SVG inline w HTML, **uploadowany** przez usera | `icon` (object), włącz SVG support w `functions.php` (sprawdź czy CLAUDE.md / projekt już ma helper / `mime_types` filter; jeśli nie — zapytaj usera) |

### Ikony font (FontAwesome, Material Icons, Bootstrap Icons)
**Preferuj zamianę na inline SVG** (lżej, brak external CSS):

```html
<i class="fas fa-arrow-right"></i>
```

Konwersja:
1. Skopiuj SVG z biblioteki (FontAwesome github, lucide.dev, heroicons.com)
2. `assets/icons/{block-name}/arrow-right.svg`
3. `<?php echo file_get_contents(get_theme_file_path('assets/icons/{block-name}/arrow-right.svg')) ?>`

**Jeśli user chce zachować font-icon** (dużo ikon, kompozycja):
```php
wp_enqueue_style( 'fontawesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css' );
```
**Ostrzeż usera:** external CSS = wolniej + tracking (Cloudflare CDN).

### Fonty lokalne
```css
@font-face { font-family: 'CustomFont'; src: url('fonts/CustomFont.woff2') format('woff2'); }
```
→ pobierz `.woff2` do `assets/fonts/custom-font/`, dodaj `@font-face` w `theme.json` lub `styles/font-face.css`.

---

## 8. JS interaktywność

Wykryj typ z HTML/JS i dobierz pattern. **Konkretne patterny scaffoldingu** (Slider, CF7, Modal, Navbar, Accordion) odczytaj z `block-template.md` projektu. Jeśli pattern brak → użyj generycznej implementacji + zaproponuj userowi dodanie do `block-template.md`.

| Wzorzec HTML/JS | Implementacja |
|---|---|
| Swiper / Slick / Glide slider | `view.js` z `swiper` (patrz `block-template.md` Pattern: Slider, jeśli istnieje) |
| Accordion (custom JS, jQuery toggle) | `view.js` z `addEventListener('click')` LUB native `<details><summary>` (preferowane — zero JS, a11y free) |
| Tabs (click → show/hide) | `view.js` z toggleClass `is-active` + ARIA |
| Modal / Lightbox | `view.js` + a11y focus trap + ESC close + overlay click |
| Form submit + AJAX | CF7 (patrz `block-template.md` Pattern: CF7, jeśli istnieje) — przepisz `<form>` HTML na shortcode `[contact-form-7]` |
| GSAP scroll reveal | klasa reveal projektu (np. `is-reveal`) — sprawdź `CLAUDE.md` lub `functions.php` czy theme ma globalny reveal |
| AOS / WOW.js | zamień na globalny reveal projektu (jak wyżej) |
| Smooth scroll do anchor | sprawdź czy projekt ma global `scroll-behavior: smooth` (`functions.php`); jeśli nie — dodaj |
| Sticky header on scroll | navbar `position: fixed` + `view.js` toggle `.is-scrolled` |
| Counter / number animation | `view.js` z IntersectionObserver + rAF (patterns/animations.md); GSAP TYLKO jeśli już w `package.json` |
| Parallax | CSS `background-attachment: fixed` lub IO + rAF; GSAP ScrollTrigger TYLKO jeśli już w `package.json` |
| Hamburger menu | sprawdź `block-template.md` Pattern: Navbar (jeśli istnieje); inaczej standardowy toggle |

### Reguła: NIE kopiuj custom JS 1:1
HTML często ma JS niezgodny z theme conventions (jQuery, brak a11y, inline event handlers). **Przepisz** na pattern z `block-template.md` / vanilla JS. Wyjątek: bardzo specyficzna logika biznesowa (kalkulator, konfigurator) — zachowaj logic, przepisz na vanilla bez jQuery.

### Reguła: jQuery
- HTML często używa jQuery (`$(...)`, `.fadeIn()`, `.slideToggle()`)
- **NIE enqueue jQuery** w theme — przepisz na vanilla
- WP dostarcza jQuery globalnie, ale block themes preferują vanilla (lżej, brak dependency)

### Reguła: external libs
- Swiper / GSAP — sprawdź `package.json` projektu, użyj jeśli już jest
- Inne biblioteki (AOS, GLightbox, Tippy) — zapytaj usera czy dodać do `package.json` czy zastąpić existing patternem

---

## 9. Frameworki CSS — Bootstrap, Tailwind, MUI, Bulma

HTML często używa frameworków CSS. **NIE kopiuj klas frameworków** do wynikowego SCSS bloku. Wyciągnij wartości, zapomnij klasy framework, przepisz na BEM + tokeny `theme.json` projektu.

### Tailwind CSS

```html
<div class="flex flex-col md:flex-row gap-4 p-8 bg-gray-100">
  <h2 class="text-3xl font-bold text-gray-900">Tytuł</h2>
</div>
```

Konwersja:
1. Wyciągnij wartości utility classes (parse Tailwind config jeśli custom):
   - `flex flex-col md:flex-row` → `display: flex; flex-direction: column;` + `@media (max-width: {LAYOUT_BREAKPOINT})` odwrotnie
   - `gap-4` → `gap: 16px` (Tailwind default scale: 1=4px, 4=16px, 8=32px)
   - `p-8` → `padding: 32px`
   - `bg-gray-100` → mapuj na slug surface/tertiary z theme.json
   - `text-3xl` → `font-size: 30px` → mapuj na slug z theme.json
   - `font-bold` → `font-weight: 700`
   - `text-gray-900` → mapuj na slug primary/text z theme.json
2. Przepisz na BEM (slugi per projekt):
```scss
.{PREFIX}__container {
  display: flex;
  flex-direction: row;
  gap: clamp(12px, ..., 16px);
  padding: clamp(16px, ..., 32px);
  background-color: var(--wp--preset--color--{slug});

  @media (max-width: {LAYOUT_BREAKPOINT}) { flex-direction: column; }
}
```

### Bootstrap

```html
<div class="container">
  <div class="row">
    <div class="col-md-6">...</div>
    <div class="col-md-6">...</div>
  </div>
</div>
```

Konwersja:
- `.container` → wrapper `__inner` z `max-width: {CONTENT_MAX}; margin: 0 auto` (wartość z CLAUDE.md projektu)
- `.row` + `.col-md-6` → CSS Grid `grid-template-columns: repeat(2, 1fr)` + `@media (max-width: {LAYOUT_BREAKPOINT}) { grid-template-columns: 1fr }`
- `.btn`, `.btn-primary` → klasa BEM `__btn` + tokeny `theme.json`
- `.d-flex`, `.justify-content-center` → vanilla flexbox
- **NIE enqueue Bootstrap CSS**

### Material UI / MUI

- Komponenty React (`<Button>`, `<Card>`) — przepisz na HTML + BEM SCSS
- Theme MUI (palette) → mapuj na `theme.json` colors
- **NIE używaj** `@mui/material` w block theme (React lib, kolizja z Gutenberg React)

### Bulma / Foundation / inne
Ta sama zasada: **wyciągnij wartości, zapomnij klasy framework**, przepisz na BEM + theme.json tokens.

### Reguła kompletna
| Sytuacja | Akcja |
|---|---|
| HTML używa < 5 klas Tailwind | Przepisz inline na SCSS, nie enqueue |
| HTML używa Tailwind szeroko (50+ klas, custom config) | Zapytaj: włączać Tailwind w theme (PostCSS) czy zreplikować w SCSS? Default: zreplikuj |
| HTML używa Bootstrap utility classes | Zreplikuj wartości w SCSS, NIE enqueue |
| HTML używa Bootstrap component classes (`.modal`, `.carousel`) | Zreplikuj behavior — patterny z `block-template.md` jeśli istnieją (Modal, Slider), inaczej generic |
| HTML używa MUI / Material Design | Przepisz na HTML + BEM, NIE używaj MUI |

---

## 10. Tryb A — Single component (1 blok)

Flow analogiczny do per-blok workflow z `CLAUDE.md` projektu:

### Faza 1 — Source
1. Wczytaj fragment HTML (Read / paste)
2. Identyfikuj sekcję — jeden top-level wrapper

### Faza 2 — CSS scope
1. Grep CSS po używanych class names (`Bash grep "class-name" styles.css`)
2. Wczytaj TYLKO reguły dotyczące tej sekcji
3. Wyciągnij desktop + mobile wartości

### Faza 3 — Klasyfikacja
- Istniejący / wariant / nowy (sekcja 3)
- Jeśli istniejący → użyj, koniec
- Jeśli wariant → propose wariant atrybut, akceptacja
- Jeśli nowy → kontynuuj

### Faza 4 — Pytania (wszystkie naraz)
1. Atrybuty edytowalne vs hardcoded?
2. Layout full-width (edge-to-edge) czy max-width `{CONTENT_MAX}`?
3. Powtarzalne itemy (array) czy stała struktura?
4. Interaktywność (jeśli HTML ma JS)?
5. Treść z HTML jako default (sample) czy puste defaulty?
6. Assety: hardcoded (logo) czy uploadowalne?

### Faza 5 — Plan kompaktowy (~30 linii)
- Atrybuty (tabela klucz / typ / default)
- Skeleton HTML (klasy BEM, bez CSS)
- CSS: tokeny kolorów/fontów (z `theme.json` projektu), wartości clamp desktop↔mobile
- Decyzje specyficzne (slider config, accordion behavior, empty state)

### Faza 6 — Akceptacja → kod → build → test
1. Czekaj na akceptację planu
2. Pre-implementation checklist (patterny z `block-template.md`, zasady z `CLAUDE.md`)
3. Scaffolding bloku z `block-template.md`
4. `npm run build`
5. Test: edytor (desktop + wąski panel) + frontend (desktop + mobile)

---

## 11. Tryb B — Whole page (wiele bloków)

Fazy (cała strona → wiele bloków):

### Faza 1 — Rozpoznanie HTML (tanie)
1. Read pliku HTML (całość)
2. Identyfikacja sekcji top-level (lista 1-N)
3. Tabela mapowania (sekcja 2)

### Faza 2 — Klasyfikacja per sekcja
- Każda sekcja: istniejący / wariant / nowy
- Tabela mapowania uzupełniona statusem

### ⛔ ZAKAZ — pełne CSS przed Fazą 6
**TWARDA REGUŁA:** w Fazie 1-5 NIE wczytuj całego CSS (może być 500k+ tokenów dla rozbudowanej strony). Używaj WYŁĄCZNIE:
- Read HTML body (struktura sekcji)
- Inline styles wewnątrz HTML
- CSS z `<style>` w `<head>` TYLKO sekcje pasujące do top-level (`section`, `header`, `body`, root variables)

NIE czytaj `styles.css` na dysku w Fazie 1-5. Robisz to per-blok w Fazie 6.

### Faza 3 — Pytania globalne (wszystkie naraz)
Patrz sekcja 12.

### Faza 4 — Plan per-blok (seryjnie)
Dla każdego **nowego** bloku (istniejące pomijasz):
1. Plan kompaktowy (~30 linii)
2. Pytania decyzyjne do bloku (3-6)
3. **Czekaj na akceptację** przed kolejnym

**Nie implementuj** w tej fazie.

### Faza 5 — Theme.json setup (raz)
Jeśli plany wymagają nowych slugów → edytuj `theme.json`. NIE resetuj istniejących.

### Faza 6 — Implementacja per-blok
Dla każdego bloku:
1. Bash time-tracker start `{block-name}` (jeśli używasz)
2. Wczytaj **tylko** CSS dotyczący tej sekcji (grep po class names)
3. Pobierz **tylko** assety hardcoded dla sekcji
4. Scaffolding bloku z `block-template.md`
5. Bash time-tracker end

**Wskazówki:**
- CSS wczytuj selektywnie (grep), nie cały plik
- Assety pobieraj batch na końcu fazy 6 (jeden `xargs curl`)
- Pliki pisz od razu, bez dodatkowej weryfikacji

### Faza 7 — Build (raz, na końcu)
```bash
npm run build
```
Jeden build pokrywa wszystkie nowe bloki. Incremental marnują czas.

### Faza 8 — Tworzenie strony przez `wp_insert_post`

**Preferowany path:** WP MCP Adapter — ability `{ns}/create-page` (patrz `wp-mcp.md`).

**Fallback gdy WP MCP niedostępny:**
- WP-CLI: zapisz PHP do `/tmp/insert-page.php`, uruchom `wp eval-file /tmp/insert-page.php` (jeśli user ma WP-CLI)
- Ręczne: pokaż userowi snippet, niech wklei do WP admin → Tools → Site Health → Info → kopia z `wp shell` lub uruchomi przez `wp-cli` lokalnie

**Snippet PHP (identyczny dla wszystkich path):**

```php
$existing = get_page_by_path( '{page-slug}', OBJECT, 'page' );
if ( $existing ) {
    return [ 'status' => 'exists', 'id' => $existing->ID, 'url' => get_permalink( $existing->ID ) ];
}

$content = implode( "\n\n", [
    '<!-- wp:{namespace}/{block-1} /-->',
    '<!-- wp:{namespace}/{block-2} /-->',
    '<!-- wp:{namespace}/{block-3} /-->',
] );

$post_id = wp_insert_post( [
    'post_title'   => '{Page Title}',
    'post_name'    => '{page-slug}',
    'post_status'  => 'publish',
    'post_type'    => 'page',
    'post_content' => $content,
] );

return [
    'status' => 'created',
    'id'     => $post_id,
    'url'    => get_permalink( $post_id ),
    'edit'   => admin_url( 'post.php?post=' . $post_id . '&action=edit' ),
];
```

**Reguły:**
- Bloki self-closing `<!-- wp:{namespace}/{block-name} /-->` używają defaultów z `block.json`
- Pomijaj navbar + footer (template parts, ładują się automatycznie)
- Duplicate check: `get_page_by_path` PRZED `wp_insert_post`
- Jeśli WP MCP niedostępny i brak WP-CLI → user wykonuje krok ręcznie, zwróć mu PHP snippet + instrukcję

---

## 12. Pytania globalne (Tryb B)

Zadaj userowi **WSZYSTKIE pytania jednocześnie** przed Fazą 4:

1. **Tytuł strony + slug** (`{Page Title}`, `{page-slug}`)
2. **Kolejność bloków** — potwierdź listę z tabeli mapowania
3. **Pomijane sekcje** (zwykle: navbar, footer)
4. **Istniejące bloki** — użyć jak są, czy zmodyfikować pod nowy design?
5. **Warianty** — sekcje podobne: wariant czy nowy blok?
6. **Treść z HTML jako default** — sample czy puste?
7. **Assety** — które hardcoded (logo, ikony stałe), które uploadowalne (zdjęcia content, hero)?
8. **Theme.json** — design wymaga nowych slugów (font-family, color, spacing)?
9. **Frameworki CSS** — HTML używa Bootstrap/Tailwind/MUI, replikujemy w SCSS czy enqueue framework?
10. **JS** — HTML używa jQuery / custom JS, przepisujemy na vanilla / wzorce theme czy enqueue oryginalne?

**Czekaj na wszystkie odpowiedzi** przed Fazą 4.

---

## 13. Checklist startowy

### Tryb A (single component)
1. [ ] Wczytaj fragment HTML (Read / paste)
2. [ ] Wycinka CSS dotycząca sekcji
3. [ ] Klasyfikacja (istniejący / wariant / nowy)
4. [ ] Pytania (atrybuty, layout, items, JS, defaulty, assety)
5. [ ] Plan kompaktowy
6. [ ] Akceptacja → kod → build → test

### Tryb B (whole page)
1. [ ] Read HTML body, lista sekcji top-level
2. [ ] Tabela mapowania (status per sekcja)
3. [ ] Pytania globalne (10 punktów) — naraz
4. [ ] Plan per nowy blok — seryjnie z akceptacją
5. [ ] Theme.json update (jeśli trzeba)
6. [ ] Implementacja per-blok: CSS scope + scaffolding + assety hardcoded
7. [ ] `npm run build` — raz
8. [ ] WP MCP `wp_insert_post` z duplicate check
9. [ ] Pobieranie assetów hardcoded batch
10. [ ] Zwróć userowi: URL frontend, URL edytor, ID strony

---

## 14. Oszczędności tokenów (skrót)

| Technika | Zysk |
|---|---|
| Read tylko `<body>`, skip `<head>` poza meta/fonty | -30-50% HTML |
| CSS per sekcja (grep po class names), NIE całość | -50-70% CSS |
| Tryb B: nie ładuj całego CSS w Fazie 1-5 | -100-500k tok |
| Plan per-blok kompaktowy (~30 linii) | redukcja outputu |
| Jeden build na końcu | czas + zero dodatkowych tokenów |
| Assety batch (`xargs curl`) | szybciej + mniej overhead |
| `wp_insert_post` w jednym call (WP MCP / WP-CLI) | ~500 tok per call |

---

## 15. Edge cases

### HTML z frameworków (Bootstrap, Tailwind, MUI)
Patrz sekcja 9. Replikuj wartości w SCSS, NIE enqueue framework. Wyjątek: Tailwind z dużym custom config + heavy use → zapytaj usera o włączenie PostCSS.

### HTML z inline styles (`style="..."`)
- **Stałe wartości** (kolor, padding, font-size) → wyciągnij do SCSS bloku
- **Zmienne wartości** (background-image z atrybutu) → zachowaj inline w `render.php` z `style="background-image: url(<?php echo esc_url($attributes['image']['url']) ?>)"`

### HTML z `id` kolidującym z anchor blocku
Jeśli HTML ma `<section id="contact">` a blok używa `anchor` → użyj `id` jako default `anchor`. Ostrzeż usera o potencjalnej kolizji jeśli blok wstawi się dwa razy na stronie.

### HTML z external CSS framework (link)
```html
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap/dist/css/bootstrap.min.css">
```
Zapytaj: replicate w SCSS czy enqueue? **Default: replicate** (lżej, brak external dependency, brak tracking).

### HTML z formularzami
- `<form action="..." method="POST">` z custom backendem → CF7 pattern (jeśli `block-template.md` definiuje)
- HTML5 validation (`required`, `type="email"`) → CF7 obsługuje natywnie
- Newsletter forms (Mailchimp, Brevo) → wtyczka integration plugin LUB CF7 + hook actions
- **NIE wklejaj** `<form>` 1:1 do `render.php`

### HTML z analytics / tracking
- GA, GTM, Facebook Pixel, Hotjar → pomiń w bloku
- Dodaj do `functions.php` osobno gdy user poprosi (lub plugin dedykowany)
- Inline `<script>` z trackingiem → wyrzuć

### HTML z pre-rendered content (lista postów)
```html
<article class="post"><h2>Post 1</h2>...</article>
<article class="post"><h2>Post 2</h2>...</article>
<article class="post"><h2>Post 3</h2>...</article>
```

Zapytaj:
1. **Hardcode** — atrybut array, user edytuje w bloku (statyczny)
2. **Dynamic** — `WP_Query` + CPT, user dodaje przez WP admin (Dynamic Block pattern z `block-template.md`)

### HTML z multiple stron (`index.html` + `about.html` + `contact.html`)
Każda strona = osobny Tryb B. Współdzielone sekcje (navbar, footer, CTA) → identyfikuj raz, użyj na wszystkich (template parts dla navbar/footer, pattern dla CTA).

### Gdy slug strony zajęty
WP MCP zwraca `status: exists`. Zapytaj: nadpisać (update post_content) czy stworzyć jako `{slug}-2`?

### HTML z RTL (right-to-left)
- `<html dir="rtl">` → dodaj support do `theme.json` lub osobne `style-rtl.css`
- W bloku użyj logical properties (`margin-inline-start` zamiast `margin-left`)

### HTML z dark mode toggle
Jeśli HTML ma toggle `prefers-color-scheme` lub button "dark mode":
- W block theme: użyj **style variations** (FSE)
- NIE rób per-blok dark mode toggle

### HTML to email template
NIE konwertuj. Email templates używają inline styles + tabel + `<center>` — niekompatybilne z block theme. Powiedz userowi: "To wygląda na email template. Block theme jest dla web pages. Skonwertuję jeśli potwierdzisz."

### HTML z server-side templating (Twig, Handlebars, EJS, Liquid)
```html
{{ post.title }}
{% for item in items %}...{% endfor %}
```
Zignoruj składnię template engine. Zostaw tylko HTML structure. Logikę przepisz na PHP w `render.php` (`WP_Query`, `foreach`).

### HTML z React/Vue components (compiled output)
Compiled JSX/Vue HTML wygląda jak normalny HTML, ale ma artefakty (`data-react-id`, `v-if`). Wyrzuć atrybuty `data-*` reactowe. Komponenty interaktywne → przepisz na pattern z `block-template.md`.

---

## 16. Co NIE robić

- ❌ NIE kopiuj HTML 1:1 do `render.php` — przepisz przez block attributes
- ❌ NIE wczytuj całego CSS frameworku jeśli używane są tylko 3 klasy
- ❌ NIE enqueue Bootstrap/Tailwind/MUI w theme — replikuj wartości w SCSS
- ❌ NIE zachowuj inline `<script>` z HTML — przepisz na `view.js` lub usuń
- ❌ NIE pobieraj obrazów decoracyjnych do `assets/` jeśli mają być uploadowalne
- ❌ NIE generuj `theme.json` od zera — extenduj istniejący
- ❌ NIE buduj per-blok w Trybie B — jeden build na końcu
- ❌ NIE używaj klas frameworków w wynikowym SCSS — BEM only
- ❌ NIE twórz strony Novamirą przed buildem (bloki muszą być zarejestrowane)
- ❌ NIE wstawiaj atrybutów bloków do Gutenberg markup (user uzupełnia w edytorze)
- ❌ NIE wczytuj całego CSS w Fazie 1-5 Trybu B
- ❌ NIE używaj jQuery — vanilla JS only
- ❌ NIE kopiuj custom JS 1:1 — przepisz na pattern
- ❌ NIE konwertuj email templates — niekompatybilne z block theme
- ❌ NIE wywołuj WP MCP / runtime do weryfikacji bloku — trust `npm run build`, user testuje wizualnie
- ❌ NIE hardcoduj nazw slugów `theme.json` w tym pliku — zawsze odczytuj z projektu
- ❌ NIE modyfikuj plików `.md` (CLAUDE.md, block-template.md, ten plik) bez wyraźnej zgody usera
