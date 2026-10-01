# CSS Conventions

Reguły CSS/SCSS dla wszystkich bloków. Desktop-first (max-width media queries).

---

## BEM Naming
- Wrapper: `.wp-block-{namespace}-[block-name]` (auto WP)
- Elementy: `.{prefix}__element` (np. `.hero__content`)
- Modifiery: `.{prefix}__element--variant` (np. `.os__card--featured`)
- Stany: `.is-scrolled`, `.is-open`, `.is-active`, `.menu-open`

Prefix CSS = unikalny 2–5 literowy skrót per blok. Dla istniejącego bloku odczytaj z `.scss`, nie wymyślaj.

---

## Responsywność i breakpointy

**Figma daje desktop (1440px) i mobile (zwykle 360px).** Widok mobile stosujemy od **1024px** w dół (tablet = layout mobilny). Drugi breakpoint **600px** = telefon (tam schodzimy do 1 kolumny i wchodzą wartości z mobile Figmy).

```scss
@media (max-width: 1024px) { /* tablet + mobile — zmiana LAYOUTU */ }
@media (max-width: 600px)  { /* telefon — 1 kolumna, wartości 1:1 z mobile Figmy */ }
```

**Uwaga przy mobile Figmie:** frame 360px często trzyma **te same** wartości co desktop (gap/padding) — nie zaniżaj ich „na oko" przy przepisywaniu; bywa też odwrotnie (element ma na mobile większą typografię niż na desktopie). Czytaj geometrię z `get_metadata`, nie z intuicji.

**Breakpoint zmienia TYLKO layout** (flex-direction, grid-columns, kolejność). **Wartości liczbowe** (font, padding, gap) skalują się przez `clamp()`, NIE przez breakpointy.

---

## Fluid values (clamp)

Font-size, padding, gap — ZAWSZE `clamp()` zamiast stałych px.

```scss
// clamp(min, preferred, max)
// min = wartość Figma mobile (osiągana na 768px)
// max = wartość Figma desktop (osiągana na 1440px)
// preferred = MOBILE + (DESKTOP - MOBILE) * ((100vw - 768px) / (1440 - 768))
// 672 = 1440 - 768

font-size: clamp(24px, calc(24px + 16 * ((100vw - 768px) / 672)), 40px); // 24→40
padding:   clamp(24px, calc(24px + 40 * ((100vw - 768px) / 672)), 64px); // 24→64
gap:       clamp(24px, calc(24px + 24 * ((100vw - 768px) / 672)), 48px); // 24→48
```

- Desktop max = wartość Figma desktop (widoczna 1440px+)
- Mobile min = wartość Figma mobile (widoczna 768px-)
- Między 768–1440px skaluje płynnie

---

## Layout: sekcja vs inner wrapper

KAŻDY blok:
```
<section class="wp-block-...">      ← ZERO padding, tło edge-to-edge, WP kontroluje szerokość
  <div class="{prefix}__inner">    ← max-width, margin: 0 auto, padding (horizontal + vertical)
    treść…
  </div>
</section>
```
**NIGDY** padding na `<section>`. Padding TYLKO na `__inner`. Tło (kolor, obraz) na sekcji — pełna szerokość.

### Full-width bloki (bez max-width)
Content do krawędzi ekranu (mapy, slidery, zdjęcia edge-to-edge) → `__inner` bez `max-width`/`margin: 0 auto`, tylko padding (lub zero).

---

## Box-sizing
Każdy element z `padding` + `width: 100%` (lub constraint od rodzica) MUSI mieć `box-sizing: border-box`. Bez tego padding dodaje się do szerokości → overflow. Dotyczy: kontenerów wewnętrznych (`__left`, `__right`, `__box`), CTA wrapperów, kart, inputów.

## Stała width + overflow
Elementy ze stałą `width` (np. button 336px) MUSZĄ mieć `max-width: 100%` + `box-sizing: border-box`. Bez tego na wąskim ekranie wykraczają poza rodzica.

## Kontenery — bez sztywnego height
NIE ustawiaj `height` na kontenerach flex (navbar inner, sekcje). Niech content + padding determinują wysokość. Sztywny `height` → overflow gdy content rośnie (walidacja formularza, dłuższy tekst).

---

## Editor SCSS = chrome, nie kopia style.scss
`style.scss` (block.json `style`) ładuje się **też w iframe edytora** (WP 6.3+), więc clamp/`@media`/box-sizing działają w edytorze same z siebie — NIE kopiuj ich do `editor.scss`. `editor.scss` (`editorStyle`) trzymaj tylko na chrome edytora: dashed bordery pickerów, placeholdery, ✕, drobne korekty. Duplikacja layoutu = konflikt specyficzności + podwójne utrzymanie (patterns/editor-gotchas.md §9). Responsywność testuj w wąskim panelu (~500px) — ale to `style.scss` na nią odpowiada.

---

## Anchory i scroll
- Globalny `scroll-behavior: smooth` + `scroll-margin-top` na `[id]` — w `functions.php` (inline style). Nie dodawaj per blok.
- Navbar `position: sticky` → `body padding-top` NIE potrzebne (patrz patterns/navbar-menu.md). Jeśli wyjątkowo `fixed` → element pod nim potrzebuje `margin-top` = wysokość navbara.
- Filter `render_block` w `functions.php` wstrzykuje `id` z anchora do PIERWSZEGO tagu HTML (`get_block_wrapper_attributes()` tego nie robi).
- **Uwaga:** filter sprawdza `id=` tylko w pierwszym tagu. Bloki z zagnieżdżonym `id` (np. CF7 `id="wpcf7-..."`) nie kolidują z anchorem sekcji.

---

## Background image vs `<img>`
Zdjęcie jako TŁO sekcji z elementami NA nim (karty, tekst) → `background-image` (inline style z render.php), nie `<img>`. Wtedy na desktop elementy overlay'ują naturalnie, na mobile zmienia się tylko layout (np. `padding-top` odsłania zdjęcie). Nie przełączasz `<img>` ↔ `background-image` na breakpoincie.

Zdjęcie jako samodzielny element (galeria, karta) → `<img>` z `object-fit: cover`. Pełne patterny → `patterns/backgrounds.md` + `patterns/media-images.md`.

---

## Fonty — variable weight + `font-display`
- **Variable font → czytaj `font-variation-settings`, nie tylko `font-weight`.** Efektywna grubość = wartość `'wght' N` z CSS exportu/Figmy. Sam `font-weight: 600` przy variable foncie daje inną (często za ciężką) grubość niż design. Kopiuj dokładnie:
  ```scss
  .{prefix}__heading { font-variation-settings: 'wght' 480; } // nie font-weight:500
  ```
- **`@font-face` w `theme.json` → ZAWSZE `"fontDisplay": "swap"`.** WP domyślnie generuje `font-display: fallback` → przy wolnym ładowaniu fontu zostaje system-ui i user widzi „zły font" w całym projekcie mimo poprawnego pliku. W KAŻDYM `fontFace`:
  ```json
  "fontFace": [ { "fontFamily": "Inter", "src": [ "file:./assets/fonts/inter.woff2" ], "fontDisplay": "swap" } ]
  ```
  Diagnoza: screenshot z `--virtual-time-budget` (font dociąga) = OK, szybki = fallback → brakuje swap.

---

## Popover / tooltip nad obszarem z `overflow: hidden`
Element popoverowy (tooltip, karta, dropdown) wychodzący poza obszar, który przycina treść przewijaną/pełzającą (`overflow: hidden`) → **osobna warstwa klipująca** tylko zawartość, popover poza nią:
```scss
.{prefix}__map        { position: relative; /* BEZ overflow — tooltip dziecko nie przycięte */ }
.{prefix}__map-clip   { position: absolute; inset: 0; overflow: hidden; border-radius: 16px; } // przycina TYLKO obraz+piny
.{prefix}__tooltip    { position: absolute; /* dziecko .map, nie .map-clip → pełne */ }
```
NIE przenoś popovera do rodzica wyżej (element bywa centrowany na mobile → offset się rozjedzie).

---

## Grep wszystkich wariantów selektora
Przy ekstrakcji CSS grepuj **wszystkie** warianty bazowego selektora: `.pin`, `.pin:not(.multi)`, `.pin::after`, `.pin.multi`. Pominięcie `:not()`/`::after`/`.modifier` = zgubiony wariant (np. pojedynczy pin = kropla, multi = koło). Jeśli JS musi skalować element animowany hoverem → przekaż wartość przez **CSS var** (`--pk`), NIE nadpisuj całego `transform` inline (inline blokuje transform/hover z CSS).

---

## Animacja strzałki na buttonach
Dwa spany: `arrow-1` (widoczny) + `arrow-2` (ukryty). Hover: `arrow-1` wyjeżdża, `arrow-2` wjeżdża. Mobile (@1024px): `arrow-2 display: none` + hover na `arrow-1` wyłączony.

```scss
.{prefix}__btn-arrow   { position: relative; overflow: hidden; width: 20px; }
.{prefix}__btn-arrow-2 { position: absolute; transform: translateX(-22px); }
@media (hover: hover) {
  .{prefix}__btn:hover .{prefix}__btn-arrow-1 { transform: translateX(22px); }
  .{prefix}__btn:hover .{prefix}__btn-arrow-2 { transform: translateX(0); }
}
@media (max-width: 1024px) {
  .{prefix}__btn-arrow-2 { display: none; }
  .{prefix}__btn:hover .{prefix}__btn-arrow-1 { transform: none; }
}
```

---

## iOS / mobile — elementy interaktywne
- Buttony i klikalne MUSZĄ mieć jawny `color` + `-webkit-tap-highlight-color: transparent`. iOS Safari koloruje buttony na niebiesko i daje niebieski flash przy tapnięciu.
- SVG z `stroke="currentColor"`/`fill="currentColor"` dziedziczy kolor — bez jawnego `color` na rodzicu będzie niebieski na iOS.

## Hover tylko na desktop
Hover na froncie (zmiana koloru/transform) opakuj w `@media (hover: hover)`. Na dotyku `:hover` „przykleja się” po tapnięciu — element nie wraca.
```scss
@media (hover: hover) {
  &:hover { color: var(--wp--preset--color--accent-blue); }
}
```
NIE dotyczy edytorowego UI (placeholdery, remove buttony, sidebar) — działa tylko na desktop.

## Linki tel: / mailto:
Renderuj jako `<a href="tel:">` / `<a href="mailto:">` z jawnym `color` + `text-decoration: none`. NIE używaj meta `format-detection` — link jest klikalny i kontrolujesz kolor.

---

## Warianty kolorystyczne (jasny/ciemny)
NIE polegaj na `color` na wrapperze + dziedziczeniu — inne reguły mogą nadpisać. Ustaw kolor jawnie na KAŻDYM elemencie tekstowym wariantu:
```scss
.os__card--featured .os__name,
.os__card--featured .os__subtitle,
.os__card--featured .os__price,
.os__card--featured .os__feature-item { color: #ffffff; }
```
Przy pobieraniu z Figmy porównaj dane zwykłego vs wyróżnionego elementu — szukaj różnic `text-white` vs `text-[color:var(...)]`.

---

## Walidacja formularzy — bez layout shift
Komunikaty błędów (`position: absolute`) pod inputem, pole z `position: relative` + `padding-bottom` rezerwujący miejsce. Pełny pattern → `patterns/forms.md`.
```scss
.{prefix}-cf7__field { position: relative; padding-bottom: 16px; }
.{prefix}__form .wpcf7-not-valid-tip { position: absolute; bottom: -20px; left: 0; }
```

---

## Reduced motion
Animacje (reveal, parallax, ambient) wyłączaj przy `@media (prefers-reduced-motion: reduce)`. Patrz `patterns/animations.md`.
