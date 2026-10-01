# Figma-to-Block Workflow

Proces tworzenia nowego bloku WordPress na podstawie designu z Figma.

---

## 1. Pobranie kontekstu z Figma (optymalizacja tokenów)

### Kolejność wywołań (ZAWSZE ta sama):
```
# 1. Screenshot sekcji — tani podgląd wizualny (~200 tokenów)
get_screenshot(nodeId, fileKey)

# 2. Design context TYLKO na 1 powtarzalnym elemencie (karcie, slide, itemie)
#    NIGDY na całej stronie/sekcji — zwraca 500K+ znaków!
get_design_context(nodeId_pojedynczego_elementu, fileKey)

# 3. Jeśli potrzebujesz struktury dużego node — metadata zamiast design context
get_metadata(nodeId, fileKey)
```

### Jak znaleźć nodeId pojedynczego elementu:
- W URL Figmy: `?node-id=16207-159606` → to jest sekcja (ZA DUŻA)
- Kliknij w Figmie na pojedynczą kartę → skopiuj nodeId z URL
- Lub: `get_metadata` na sekcji → znajdź child nodeId karty → `get_design_context` na nim

### Co wyciągamy z design context:
- Struktura layoutu (flex/grid, kierunek, gap)
- Kolory → mapowanie na slugi z theme.json
- Typografia → mapowanie na font-size slugi
- Spacing → mapowanie na spacing slugi
- Obrazy → atrybuty typu `object`
- Teksty → atrybuty typu `string` z polskimi defaultami
- Elementy powtarzalne → atrybuty typu `array`

### Wariant vs nowy blok — decyzja przy analizie Figmy
Jeśli sekcja z Figmy wygląda podobnie do istniejącego bloku (ten sam layout, inne kolory/tło) → zapytaj usera:

> "Ten blok wygląda jak wariant istniejącego [nazwa]. Dodać wariant kolorystyczny czy tworzyć nowy blok?"

Wariant = atrybut `variant` + SelectControl + klasa CSS modifier. Zero duplikacji kodu.

### Full-width vs max-width — decyzja przy analizie Figmy
Jeśli na screenshocie element dotyka krawędzi ramki w widoku 1440px (mapa, slider, zdjęcie edge-to-edge, sekcja kontaktowa z kolumnami do krawędzi) → zapytaj usera:

> "Content full-width (edge-to-edge) czy opakowany w max-width 1440px?"

**Full-width:** `__inner` bez `max-width` i `margin: 0 auto`. Tylko padding (lub zero).
Typowe przypadki: mapy, slidery/karuzele, sekcje ze zdjęciami sięgającymi krawędzi.

**Opakowany (standard):** `__inner` z `max-width: 1440px; margin: 0 auto; padding: ...`.
Typowe przypadki: sekcje tekstowe, gridy kart, nagłówki, CTA bannery.

### Co wyciągamy ze screenshota (bez design context):
- Liczba kolumn w gridzie
- Ogólny spacing (mapuj na najbliższy token)
- Proporcje obrazów (aspect-ratio)
- Kolorystyka (ciemne/jasne tło, kolory tekstu)
- Responsywny układ (jeśli Figma ma wersje mobile)

## 2. Mapowanie tokenów Figma → theme.json

**`theme.json` jest single source of truth.** Wartości (kolory, fonty, spacing, font-sizes) ZAWSZE odczytuj z aktualnego `theme.json` projektu. Ten plik trzyma tylko **zasady mapowania**, nie wartości — w przeciwnym razie szybko stają się outdated.

### Zasada mapowania

Dla każdego nowego designu z Figmy:

1. **Otwórz aktualny `theme.json`** projektu — sprawdź jakie slugi już istnieją (`color.palette[*].slug`, `typography.fontSizes[*].slug`, `spacing.spacingSizes[*].slug`).
2. **Znajdź najbliższy istniejący slug** dla każdego elementu z Figmy (kolor, font-size, spacing).
3. **Brakujący slug → dodaj do `theme.json`** zanim użyjesz w bloku. Slug nazywaj **rolą semantyczną**, nie wartością wizualną:
   - ✅ `accent-purple`, `muted`, `surface-soft` — opisuje rolę
   - ❌ `purple-500`, `gray-300`, `light-bg` — opisuje wartość
4. **Konwencje slugów (per-projekt — sprawdź `project.md`):**
   - **Kolory:** `primary` (text), `muted` (secondary text), `base` (białe tło), `border` (separatory), `accent-{name}` (kolory akcentów per branding), `surface-{variant}` (tła sekcji)
   - **Font-sizes:** `xx-small` / `x-small` / `small` / `base` / `medium` / `large` / `x-large` / `xx-large` — od najmniejszego do hero H1. Stałe wartości lub `clamp()`.
   - **Spacing:** ta sama skala (`xx-small` → `xx-large`) zwykle 8 / 12 / 16 / 24 / 32 / 48 / 64 px.
5. **Po stworzeniu / zmianie tokenów** — zaktualizuj sekcję "Tokeny" w `project.md` żeby przyszły session miał aktualny obraz.

### Anti-patterny

- ❌ Hardcodowanie hex w SCSS bloku — zawsze `var(--wp--preset--color--{slug})`.
- ❌ Tworzenie nowego sluga dla wartości która już istnieje (sprawdź jeszcze raz palette).
- ❌ Slug per kolor Figmy 1:1 — Figma może mieć 50 odcieni szarego. Konsoliduj do 2-3 slugów semantycznych.
- ❌ Wstawianie konkretnych wartości tokenów do `figma-workflow.md` lub `block-template.md` — outdated po pierwszej zmianie projektu. Trzymaj w `theme.json` + `project.md`.

## 3. Responsywność: Figma → clamp()

Figma dostarcza widok desktop (1440px) i mobile. Przy konwersji:

1. **Wyciągnij wartość desktop** z Figmy (font-size, padding, gap)
2. **Wyciągnij wartość mobile** z Figmy mobile
3. **Użyj `clamp()`** — wartość skaluje się płynnie między 768px a 1440px

```scss
// clamp(MOBILE, calc(MOBILE + (DESKTOP - MOBILE) * ((100vw - 768px) / 672)), DESKTOP)
font-size: clamp(24px, calc(24px + 16 * ((100vw - 768px) / 672)), 40px);
```

**Layout (flex-direction, grid-columns)** zmienia się na breakpoincie `1024px` — widok mobile z Figmy stosujemy od 1024px w dół.

**Wartości liczbowe (font, padding, gap)** NIE zmieniają się na breakpointach — skalują się płynnie przez `clamp()`.

### Uwaga: design context mobile może kłamać
`get_design_context` na mobile node może zwrócić **odziedziczone wartości z desktop componenta** (np. padding-top 128px zamiast rzeczywistych 76px). ZAWSZE weryfikuj wartości mobile wizualnie ze screenshotem — nie kopiuj ślepo z design context. Jeśli wartość wygląda podejrzanie (taka sama jak desktop), zmierz proporcjonalnie ze screenshota.

## 4. Konwersja layoutu Figma → CSS

| Figma | CSS |
|---|---|
| Auto Layout (horizontal) | `display: flex; flex-direction: row;` |
| Auto Layout (vertical) | `display: flex; flex-direction: column;` |
| Grid | `display: grid; grid-template-columns: ...;` |
| Fill container | `width: 100%;` lub `flex: 1;` |
| Hug contents | `width: auto;` lub `width: fit-content;` |
| Fixed | `width: Xpx;` / `max-width: Xpx;` |

## 5. Assety z Figmy

`get_design_context` zwraca URL-e assetów jako `const img = "https://www.figma.com/api/mcp/asset/..."`.

### Kiedy pobierać do assets/:
- **TAK** — ikona hardcodowana inline w render.php (np. stały SVG w szablonie). Pobierz do `assets/icons/[block-name]/`.
- **NIE** — ikona/obraz jako atrybut bloku (`object { id, url, alt }`) uploadowany przez MediaUpload. User dodaje je przez bibliotekę mediów WP → trafiają do `wp-content/uploads/`.

### Zasada:
Jeśli atrybut w block.json = `"type": "object"` z `{ id, url, alt }` → NIE pobieraj do assets. User uploaduje sam.

## 6. Checklist przed ukończeniem

- [ ] Kolory używają tokenów z theme.json (nie hardcoded hex)
- [ ] Font-size, padding, gap w `clamp()` (768px–1440px)
- [ ] @1024px zmienia TYLKO layout (flex-direction, grid), NIE wartości liczbowe
- [ ] `box-sizing: border-box` na elementach z padding + width
- [ ] Buttony: `max-width: 100%` żeby nie overflow'owały
- [ ] `editor.scss` = `style.scss` (te same clamp, breakpointy)
- [ ] `anchor` + `className` jawnie w attributes block.json
- [ ] Sekcje z overlay (karty na zdjęciu): `background-image` zamiast `<img>`
- [ ] Arrow animation: `arrow-2` ukryty, `display: none` @1024px
- [ ] BEM nazewnictwo z krótkim prefiksem
- [ ] Wszystkie assety pobrane i użyte
