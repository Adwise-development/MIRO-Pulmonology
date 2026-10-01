# Figma → Block — pobieranie designu i mapowanie tokenów

Proces konwersji designu z Figmy na blok WordPress. Zasada nadrzędna: **oszczędność tokenów** — screenshot najpierw, design_context tylko na pojedynczym elemencie.

---

## 1. Kolejność wywołań API (ZAWSZE ta sama)

```
# 1. Screenshot sekcji — tani podgląd wizualny
get_screenshot(nodeId, fileKey)

# 2. Design context TYLKO na 1 powtarzalnym elemencie (karta, slide, item)
#    NIGDY na całej stronie/sekcji — zwraca 500K+ znaków sparse metadata
get_design_context(nodeId_pojedynczego_elementu, fileKey)

# 3. Jeśli potrzebujesz struktury dużego node → metadata, NIE design context
get_metadata(nodeId, fileKey)
```

**Reguły:**
1. ZAWSZE `get_screenshot` najpierw.
2. NIGDY `get_design_context` na node całej strony/sekcji.
3. `get_design_context` TYLKO na jednym powtarzalnym elemencie.
4. Layout/spacing czytaj ze screenshota + tej instrukcji; design_context na 1 elemencie tylko dla dokładnych wartości CSS.
5. Strukturę dużego node poznaj przez `get_metadata`, nie `get_design_context`.

### Jak znaleźć nodeId pojedynczego elementu
- URL Figmy `?node-id=16207-159606` to zwykle **sekcja (za duża)**.
- Kliknij pojedynczą kartę w Figmie → skopiuj nodeId z URL.
- Lub: `get_metadata` na sekcji → znajdź child nodeId karty → `get_design_context` na nim.

### Co wyciągamy
| Ze screenshota (tanio) | Z design_context (1 element) |
|------------------------|------------------------------|
| Liczba kolumn w gridzie | Dokładny flex/grid, gap, kierunek |
| Ogólny spacing (→ token) | Kolory (→ slug theme.json) |
| Proporcje obrazów (aspect-ratio) | Typografia (→ font-size slug) |
| Kolorystyka (ciemne/jasne) | Spacing (→ spacing slug) |
| Układ responsywny (jeśli jest mobile) | URL-e assetów |

---

## 2. Decyzje przy analizie (zapytaj usera)

### Wariant vs nowy blok
Sekcja wygląda jak istniejący blok (ten sam layout, inne kolory/tło)?
> „Ten blok wygląda jak wariant istniejącego [nazwa]. Dodać wariant kolorystyczny czy nowy blok?”

Wariant = atrybut `variant` + SelectControl + klasa modifier. Zero duplikacji.

### Full-width vs max-width
Element dotyka krawędzi ramki w widoku 1440px (mapa, slider, zdjęcie edge-to-edge, sekcja z kolumnami do krawędzi)?
> „Content full-width (edge-to-edge) czy opakowany w max-width?”

- **Full-width:** `__inner` bez `max-width`/`margin: 0 auto`, tylko padding (lub zero). Mapy, slidery, zdjęcia do krawędzi.
- **Opakowany (standard):** `__inner` z `max-width` + `margin: 0 auto` + padding. Sekcje tekstowe, gridy kart, CTA.

Szczegóły CSS → `css-conventions.md`.

---

## 3. Mapowanie tokenów Figma → theme.json

**Aktualne WARTOŚCI (kolory, fonty, spacing, font-sizes) ZAWSZE odczytuj z `theme.json`.** Nie hardcoduj ich tutaj — różnią się per projekt. Ten plik trzyma tylko **reguły mapowania**.

### Flow
1. Tokeny z Figmy (`get_design_context`) → 2. zapis w `theme.json` (single source of truth) → 3. użycie przez CSS vary `var(--wp--preset--…)`.

### Kolory — reguła nazewnictwa slugów
Mapuj rolę z Figmy na slug semantyczny w `theme.json`, nie na nazwę koloru. Typowe role:

| Rola Figma (przykład) | slug theme.json | użycie |
|-----------------------|-----------------|--------|
| text/strong, heading | `primary` (lub `secondary` na dark) | nagłówki |
| text/neutral, body | `base` | tekst |
| background/white | `background` | tło strony |
| background/light | `tertiary` | jasne tła sekcji |
| neutral/divider | `quaternary` | bordery |
| text/link, accent | `accent-*` | linki, CTA |
| text/error | `error` | walidacja |

Slugi semantyczne (`primary`, `base`, `accent-blue`) > nazwy kolorów (`purple`, `navy`). Gdy zmienisz odcień w theme.json — wszystkie bloki podążają.

### Typografia — reguła
Figma token (np. `h/1`, `body/m`, `display`) → font-size slug w `theme.json` (`x-small`…`xxxx-large`). Fonty i warianty: `settings.typography.fontFamilies` w theme.json.
- **Variable font:** w blokach `font-variation-settings: 'wght' N` (dokładna grubość z design_context), NIE sam `font-weight` — efektywna waga = variation.
- **`@font-face` w theme.json `fontFace[]`:** każdy wpis z `"fontDisplay": "swap"` (WP domyślnie `fallback` → system-ui przy wolnym ładowaniu = „zły font"). Szczegóły → `css-conventions.md` §Fonty.

### Spacing — reguła
Figma odstęp (px) → najbliższy spacing slug. Wartości fluid (clamp) liczone osobno (patrz niżej + css-conventions.md).

**Anti-pattern:** hardcoded hex/px w SCSS. Zawsze `var(--wp--preset--color--*)`, `var(--wp--preset--font-size--*)`, lub clamp z wartości Figmy.

---

## 4. Responsywność: Figma → clamp()

Figma daje desktop (1440px) i mobile. Konwersja:
1. Wartość desktop z Figmy (max).
2. Wartość mobile z Figmy (min).
3. `clamp()` — skaluje płynnie między 768px a 1440px.

```scss
// clamp(MOBILE, calc(MOBILE + (DESKTOP - MOBILE) * ((100vw - 768px) / 672)), DESKTOP)
font-size: clamp(24px, calc(24px + 16 * ((100vw - 768px) / 672)), 40px);
```

- **Wartości liczbowe** (font, padding, gap) → `clamp()`, NIE zmieniają się na breakpointach.
- **Layout** (flex-direction, grid-columns) → zmienia się na `@media (max-width: 1024px)` (mobile od 1024px w dół). Drugi breakpoint 768px tylko gdy potrzeba.

Pełny wzór, przykłady i breakpointy → `css-conventions.md`.

### Uwaga: design_context mobile może kłamać
`get_design_context` na node mobile bywa **odziedziczony z desktop componenta** (np. padding 128px zamiast realnych 76px). ZAWSZE weryfikuj wartości mobile ze screenshotem. Jeśli wartość = identyczna jak desktop i wygląda podejrzanie — zmierz proporcjonalnie ze screenshota.

---

## 5. Konwersja layoutu Figma → CSS

| Figma | CSS |
|-------|-----|
| Auto Layout horizontal | `display: flex; flex-direction: row;` |
| Auto Layout vertical | `display: flex; flex-direction: column;` |
| Grid | `display: grid; grid-template-columns: …;` |
| Fill container | `width: 100%;` / `flex: 1;` |
| Hug contents | `width: auto;` / `fit-content;` |
| Fixed | `width: Xpx;` + `max-width: 100%;` |

---

## 6. Assety z Figmy

`get_design_context` zwraca URL-e jako `const img = "https://www.figma.com/api/mcp/asset/..."`. NIGDY ich nie ignoruj, nie wymyślaj zastępczych SVG/gradientów.

### Kiedy pobierać do `assets/`
- **TAK** — ikona/grafika **hardcodowana inline** w render.php lub CSS (stały element szablonu, nie edytowalny przez usera). Pobierz do `assets/icons/[block]/` lub `assets/images/[block]/`:
  ```bash
  curl -L -o assets/icons/[block]/icon.svg "[figma-asset-url]"
  ```
  URL-e wygasają po 7 dniach — pobieraj natychmiast. Koszt: 0 tokenów (zwykły HTTP).
- **NIE** — obraz/ikona to atrybut bloku (`object {id,url,alt}`) uploadowany przez `MediaUpload`. User dodaje przez bibliotekę mediów → trafia do `wp-content/uploads/`.

**Reguła:** atrybut block.json = `object {id,url,alt}` → MediaUpload → NIE do `assets/`. Szczegóły przechowywania obrazów → `patterns/media-images.md`.

Inline SVG z Figmy: zamień `fill="var(--fill-0, white)"` → `fill="currentColor"` (kolor sterowany przez CSS rodzica).

---

## 7. Checklist po pobraniu designu
- [ ] Screenshot zrobiony PRZED design_context
- [ ] design_context tylko na 1 elemencie (nie na sekcji/stronie)
- [ ] Kolory → slugi theme.json (nie hex)
- [ ] Font/padding/gap → clamp z wartości desktop+mobile
- [ ] Mobile values zweryfikowane ze screenshotem (nie ślepo z context)
- [ ] Wszystkie assety zidentyfikowane: hardcoded → pobrane do assets/, user-upload → MediaUpload
- [ ] Decyzja wariant-vs-blok i full-width-vs-max-width potwierdzona z userem
