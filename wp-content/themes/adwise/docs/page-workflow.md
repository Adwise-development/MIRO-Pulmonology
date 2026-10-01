# Page Workflow

Proces budowy **całej strony WP z jednego frame Figma** (wiele sekcji naraz). Plik **ad-hoc** — ładowany tylko gdy user daje URL frame Figma z wieloma sekcjami. Dla pojedynczego bloku NIE używaj tego flow, użyj standardowego workflow per-blok (patrz `CLAUDE.md` → "Workflow: design → plan → akceptacja → kod").

## Kiedy ten flow

**Trigger:**
- User daje URL frame Figma z wieloma sekcjami (landing page, strona kategorii, strona usługi)
- User pisze: "zbuduj stronę X", "zrób całą podstronę", "cały frame"
- User daje widok desktop + mobile i chce kompletną stronę

**NIE używaj** gdy:
- User prosi o 1 blok lub modyfikację istniejącego → standardowy flow per-blok
- User daje URL pojedynczej sekcji (jednego node)

---

## Placeholdery

- `{namespace}` — namespace theme (lowercase)
- `{NAMESPACE_UPPER}` — UPPERCASE prefix PHP const
- `{fileKey}` — Figma file key
- `{desktopNodeId}` / `{mobileNodeId}` — root frame IDs (desktop + mobile widoki)
- `{page-slug}` — URL slug strony (kebab-case)
- `{Page Title}` — tytuł strony po polsku

---

## Fazy

### Faza 1 — Rozpoznanie frame (tanie)

1. **Screenshot** obu widoków równolegle:
   ```
   get_screenshot(desktopNodeId, fileKey)
   get_screenshot(mobileNodeId, fileKey)
   ```
2. Wizualnie rozpoznaj sekcje z góry na dół. Policz ile bloków.
3. Dla każdej sekcji oceń:
   - Czy to **istniejący blok** w theme (porównaj wizualnie z `blocks/*`)
   - Czy to **wariant** istniejącego (ten sam layout, inna paleta)
   - Czy to **nowy blok**

### Faza 2 — Mapowanie bloków (tanie, zwięzłe metadata)

**OSZCZĘDNOŚĆ TOKENÓW:** zamiast `get_metadata` (zwraca 15-20k tokenów dla dużego frame), użyj `get_design_context` z `forceCode=false`.

```
get_design_context(desktopNodeId, fileKey, excludeScreenshot=true, forceCode=false)
get_design_context(mobileNodeId, fileKey, excludeScreenshot=true, forceCode=false)
```

Gdy `forceCode=false` i output byłby za duży, Figma zwraca **tylko metadata** (~2-3k tokenów) z listą node IDs per sekcja. To wszystko czego potrzebujesz żeby zmapować sekcje.

Jeśli Figma mimo wszystko zwróci kod (mały frame) — też OK, dostaniesz więcej kontekstu gratis.

**Efekt:** tabela mapowania per blok:

| # | Sekcja | NodeId desktop | NodeId mobile | Status | Blok theme |
|---|---|---|---|---|---|
| 1 | Hero | `...:1234` | `...:5678` | ✓ istnieje | `{namespace}/hero` |
| 2 | Oferty | `...:2345` | `...:6789` | ✓ istnieje | `{namespace}/offers-grid` |
| 3 | Proces | `...:3456` | `...:7890` | nowy | `{namespace}/process-timeline` |
| ... | | | | | |

### Faza 3 — Pytania globalne (naraz, zanim piszesz plany)

Zadaj userowi **WSZYSTKIE pytania jednocześnie**:

1. **Tytuł strony + slug** (`{Page Title}`, `{page-slug}`)
2. **Kolejność bloków** — potwierdź listę
3. **Które sekcje pomijamy** (zwykle: navbar+footer, już w template parts)
4. **Istniejące bloki** — użyć jak są czy zmodyfikować?
5. **Warianty** — jeśli podobne do istniejącego, wariant czy nowy blok?
6. **Theme.json** — czy design wymaga nowych slugów (fontsize, color, spacing)?

**Poczekaj na wszystkie odpowiedzi** przed Fazą 4.

### ⛔ ZAKAZ — `get_design_context` przed Fazą 6

**TWARDA REGUŁA:** w Fazie 1-5 (rozpoznanie + mapowanie + pytania + plany + theme.json) używaj WYŁĄCZNIE:
- `get_screenshot` (tani podgląd)
- `get_design_context forceCode=false` z `excludeScreenshot=true` na CAŁY frame TYLKO raz dla mapowania nodeIDs (i tylko jeśli output mieści się w budżecie — nie spamuj subagentem 91k+ outputów)

**NIE WOLNO** w Fazie 1-5:
- `get_design_context` na pojedynczych sekcjach (to Faza 6)
- `get_metadata` na całym frame (zwykle 15k+ tokenów)
- `get_design_context forceCode=true`

**Plan per-blok (Faza 4) tworzony jest ZE SCREENSHOTA** — wystarczy widok wizualny do oceny atrybutów + skeletona. Wartości CSS dokładne (padding, gap, font-size) doprecyzujesz w Fazie 6 per blok.

**Konsekwencja złamania reguły:** marnotrawstwo 50-200k tokenów per nieuzasadnione wywołanie. User płaci za każdy.

### Faza 4 — Plan per-blok (seryjnie, nie naraz)

Dla **każdego nowego bloku** (istniejące pomijasz):

1. Plan kompaktowy (~30 linii) zawiera:
   - Nazwa bloku (`{namespace}/{block-name}`)
   - Atrybuty (tabela klucz/typ/default)
   - Skeleton HTML (klasy, bez CSS)
   - CSS: tokeny kolorów/fontów (z `theme.json`), wartości clamp desktop↔mobile
   - Decyzje specyficzne (naprzemienność, empty state, limity CLL)
2. Zadaj **ostatnie pytania decyzyjne** do tego konkretnego bloku (3-6 pytań naraz)
3. **Czekaj na akceptację planu** przed kolejnym

**Nie implementuj** niczego w tej fazie. Tylko plany.

### Faza 5 — Theme.json setup (raz, gdy wszystkie plany zaakceptowane)

Jeśli plany wymagają nowych slugów (nowy `xxx-large` font, nowa paleta kolorów, nowy spacing):
- Edytuj `theme.json`
- Dodaj slug **z mobile-desktop clamp** dla fontów
- Nie resetuj istniejących slugów

### Faza 6 — Implementacja per-blok (seryjnie)

**Pre-implementation checklist (PRZED napisaniem kodu bloku):**

Przeczytaj sekcje `block-template.md` które stosują się do tego bloku:
- [ ] Wspólne: Remove button — standard ✕ rounded 18×18 dla WSZYSTKICH MediaUpload
- [ ] Edit Controls Overlay — gdy blok ma media slot z opcją "Zmień/Wyczyść"
- [ ] Auto Resolve z meta posta — gdy items zaciągają z meta CPT (NIE używaj SelectControl source w UI)
- [ ] Slider — wariant statyczny vs interaktywny w edytorze
- [ ] Multi-image Gallery — gdy blok ma więcej niż 1 zdjęcie (atrybut bloku, NIE meta CPT)
- [ ] Pattern: Warianty kolorystyczne / Toggle Select — jeśli blok ma warianty / toggle source

Plus z `CLAUDE.md`:
- [ ] Hardcode vs RichText vs Sidebar — odpowiednia decyzja per atrybut
- [ ] Block context-aware — gdy blok może być na single CPT
- [ ] Naming SelectControl — opcje opisują CO robi
- [ ] Minimal defaults — nie dodawaj atrybutów które user usunie

**Dla każdego bloku wykonaj:**

```
1. Bash: python3 ~/.claude/plugins/time-tracker/lib/parser.py block start {block-name}
2. get_design_context(desktopElementId, fileKey, excludeScreenshot=true)   # pojedynczy element!
3. get_design_context(mobileElementId, fileKey, excludeScreenshot=true)    # pojedynczy element!
4. Bash: mkdir -p blocks/{block-name}
5. Write: block.json + index.js + edit.js + save.js + render.php + style.scss + editor.scss (+ view.js jeśli potrzeba)
6. Bash: python3 ~/.claude/plugins/time-tracker/lib/parser.py block end {block-name}
```

**Wskazówki:**
- `design_context` TYLKO na reprezentatywnym elemencie (1 karta, 1 krok, 1 item) — NIGDY na całej sekcji (za duże)
- Mobile wartości weryfikuj ze screenshota, nie ślepo kopiuj (często `design_context` mobile zwraca odziedziczone desktop wartości)
- Pliki pisz od razu, bez dodatkowej weryfikacji
- Jeden blok = jeden komplet plików (bez dzielenia na kroki)

### Faza 7 — Build (RAZ, na końcu)

```
npm run build
```

Jeden build pokrywa wszystkie nowe bloki. Incremental buildy per-blok marnują czas i nie dają nic.

Jeśli build się wywali — fix i rebuild. Nie wracaj do poprzednich faz.

### Faza 8 — Tworzenie strony przez Novamira

**Użyj ability `novamira/execute-php`:**

```php
$existing = get_page_by_path( '{page-slug}', OBJECT, 'page' );
if ( $existing ) {
    return [ 'status' => 'exists', 'id' => $existing->ID, 'url' => get_permalink( $existing->ID ) ];
}

$content = implode( "\n\n", [
    '<!-- wp:{namespace}/{block-1} /-->',
    '<!-- wp:{namespace}/{block-2} /-->',
    '<!-- wp:{namespace}/{block-3} /-->',
    // ...
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

**Markup Gutenberg:** bloki self-closing `<!-- wp:{namespace}/{block-name} /-->` używają defaultów z `block.json`. Nie przekazuj atrybutów w JSON — user uzupełni w edytorze (obrazy, ikony, etc).

**Pomijaj navbar + footer** — są w template parts, ładują się automatycznie.

**Duplicate check:** sprawdź `get_page_by_path` PRZED `wp_insert_post` żeby nie tworzyć zduplikowanych slugów.

---

## Checklist startowy (użyj gdy trigger zidentyfikowany)

1. [ ] `get_screenshot` desktop + mobile
2. [ ] Identyfikacja sekcji (1-N blocks, oznacz istniejące/nowe)
3. [ ] `get_design_context forceCode=false` desktop + mobile — mapowanie nodeIDs
4. [ ] **Pytania globalne** (tytuł, slug, kolejność, pomijamy, warianty, theme.json) — naraz
5. [ ] **Plan per-blok** — seryjnie z akceptacją
6. [ ] Theme.json update (jeśli trzeba)
7. [ ] Implementacja per-blok: time-tracker + design_context element + kod
8. [ ] `npm run build` — raz
9. [ ] Novamira `wp_insert_post` z duplicate check
10. [ ] Zwróć userowi: URL frontend, URL edytor, ID strony

---

## Oszczędności tokenów

| Technika | Zysk |
|---|---|
| `get_design_context forceCode=false` zamiast `get_metadata` na całym frame | ~15k tok na frame |
| `excludeScreenshot=true` w `get_design_context` gdy screenshot już pobrany | ~200 tok per call |
| Batch `ToolSearch` — jedno wywołanie z `select:tool1,tool2,tool3` | ~500 tok per tool |
| Design_context TYLKO na 1 elemencie per sekcja (nie cała sekcja) | ~5-15k tok per sekcja |
| Jeden build na końcu (nie per blok) | czas + zero dodatkowych tokenów |
| Novamira `wp_insert_post` w jednym execute-php (nie per blok) | ~500 tok |

---

## Edge cases

### Wariant istniejącego bloku
Sekcja z Figmy różni się od istniejącego bloku TYLKO kolorami/tłem → **wariant** (atrybut `variant` + klasa modifier). Nie nowy blok. Patrz `block-template.md` → Pattern: Warianty.

### Navbar / Footer już w template parts
Pomijaj. Są automatycznie przez `parts/header.html` / `parts/footer.html`. Nie dodawaj `<!-- wp:{namespace}/navbar /-->` do `post_content`.

### CTA powtarzający się na wielu stronach
Jeśli ten sam CTA jest na końcu wielu stron → użyj **patternu** Gutenberg (`patterns/cta.php`) zamiast wstawiać ręcznie na każdej stronie. Patrz `optymalizacja.md`.

### Fallback gdy `design_context forceCode=false` zwróci pełny kod
Mały frame może zmieścić się w budżecie — Figma zwróci kod. OK, wykorzystaj (masz więcej kontekstu gratis). Nie wymuszaj forceCode=true bo to marnuje tokeny.

### Gdy user podaje slug zajęty
Novamira zwraca `status: exists`. Zapytaj usera: nadpisać (update post_content), czy stworzyć jako `{slug}-2`?

---

## Co NIE robić

- ❌ NIE ładuj `get_metadata` na cały frame (używaj `design_context forceCode=false`)
- ❌ NIE pobieraj `design_context` na całej sekcji (1 element wystarczy)
- ❌ NIE implementuj bloków bez akceptacji wszystkich planów
- ❌ NIE buduj per-blok — jeden build na końcu
- ❌ NIE twórz strony przed buildem (bloki muszą być zbudowane)
- ❌ NIE wstawiaj atrybutów bloków do Gutenberg markup (user uzupełnia obrazy/ikony w edytorze)
- ❌ NIE duplikuj nazw (sprawdź `get_page_by_path` przed insert)
- ❌ NIE wywołuj Novamira dla weryfikacji (trust `npm run build`, user testuje wizualnie)
