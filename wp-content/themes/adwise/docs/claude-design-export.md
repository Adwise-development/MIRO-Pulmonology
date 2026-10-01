# Claude Design Export → bloki

Front tego flow: **dostajesz folder exportu z Claude Design** i z niego budujesz WP block theme/plugin. NIE generujesz designu na żywo („wygeneruj mi…") — pracujesz z **settled exportu** (po iteracjach), bo jest powtarzalny i kompletny. Tryb **auto-load** (mapa), konwersja → `html-to-block.md`.

---

## 0. Najpierw — user dostarcza export (powiedz mu to)

**Claude NIE ma dostępu do Claude Design.** Export robi **user** i kładzie folder na dysku, żebym mógł go przeczytać. Jeśli user nie podał ścieżki do exportu → zanim cokolwiek zrobisz, **poprowadź go** tą wiadomością (dostosuj ton):

> Żeby ruszyć, potrzebuję Twojego projektu z Claude Design jako folderu na dysku (sam designu nie wygeneruję — pracuję z Twojego exportu):
> 1. W **Claude Design** otwórz projekt → **Export / Pobierz** → zapisz `.zip`.
> 2. **Rozpakuj** zip.
> 3. Wrzuć folder do katalogu projektu WP — rekomendacja: `./claude-design-export/` w roocie (obok `style.css`/`functions.php`). Może być gdziekolwiek, byle lokalnie.
> 4. **Podaj mi ścieżkę** do folderu (np. `/Users/…/moj-projekt/claude-design-export/`) — albo do `.zip`, rozpakuję sam.

**Po otrzymaniu ścieżki:**
- Jeśli to `.zip` → rozpakuj (`unzip` w katalogu projektu).
- **Zweryfikuj że to faktycznie export:** folder zawiera `handoff_*/` i/lub pliki `*.html` + (zwykle) `*/STYLEGUIDE.md` / `*/tokens.css`. Jeśli nie — dopytaj (może user wskazał zły folder).
- Krótko **potwierdź co widzisz** (ile stron HTML, czy jest brand/styleguide, czy to site czy panel produktu) i przejdź do kickoff (CLAUDE.md) + kolejności pracy (niżej).

**Gdzie trzymać na stałe:** zostaw export w repo projektu (np. `claude-design-export/`, dodaj do `.gitignore` jeśli ciężki od screenshotów/team) — to artefakt źródłowy, przydaje się przy iteracjach i kolejnych stronach.

---

## Czym jest export
Folder (zwykle zip) wyprodukowany przez Claude Design. Zawiera: strony HTML hi-fi, brand/styleguide, gotowe „handoff" docki (które Claude Design sam pisze), referencyjne komponenty React, source HTML podstron, seed treści, assety, screenshoty iteracji.

Export sam dowozi swoje instrukcje handoff (GUTENBERG.md, ANIMACJE.md, STYLEGUIDE.md, posts.md). Traktuj je jako **input/podpowiedź**, ale przy konflikcie **wygrywają nasze konwencje** (CLAUDE.md + patterns/ + css-conventions.md).

---

## Anatomia exportu — co czytać, co ignorować

| Ścieżka w exporcie | Co to | Akcja |
|--------------------|-------|-------|
| `handoff_brand_profil/STYLEGUIDE.md` | Brand: logo, paleta, typografia, spacing, komponenty atomowe, ton | **Czytaj** → źródło tokenów |
| `handoff_brand_profil/css/tokens.css` | Tokeny jako CSS `:root` (kolory, fonty, spacing) | **Czytaj** → mapuj na `theme.json` |
| `handoff_brand_profil/*.html` + `components/*.jsx` + `panel.css` | UI panelu **samego produktu** (wtyczka admin) | Osobny build (NIE bloki strony) — patrz „Site vs Produkt" |
| `design_handoff_*/*.html` | HTML hi-fi landingu (źródło designu) | **Czytaj** → konwersja przez `html-to-block.md` |
| `design_handoff_*/README.md` | Inwentaryzacja sekcji → bloki + już-odpowiedziane pytania | **Czytaj** → wejście do planów per-blok |
| `design_handoff_*/assets/*.svg` | Logo (stałe) | Pobierz do `assets/icons/` (hardcoded) |
| `handoff_wp_react/source/*.html` | Source HTML podstron (Blog, Kontakt, O nas, Zamówienie, Wpisy) | **Czytaj per strona** → konwersja (Tryb B) |
| `handoff_wp_react/GUTENBERG.md` | Mapowanie sekcji/React→blok, Query Loop params, breakpoint uwagi | **Czytaj** → podpowiedź mapowania |
| `handoff_wp_react/GUTENBERG-pages.md` | Składanie podstron (layout, kolejność bloków, front page) | **Czytaj** → Tryb B / `wp_insert_post` |
| `handoff_wp_react/ANIMACJE.md` | Reveal / count-up / success state | Patrz `patterns/animations.md` (mamy) — porównaj |
| `handoff_wp_react/content/posts.md` | Seed treści (wpisy CPT, kategorie, okładki, FAQ) | **Czytaj** gdy seedujemy bloga |
| `handoff_wp_react/react/*.jsx` | Referencyjne komponenty (Header, PostCard, SinglePost, Faq…) | Referencja struktury — **nie** kopiuj kodu React |
| `handoff_wp_react/react/tokens.css` | Tokeny (duplikat brandu) | jak `tokens.css` wyżej |
| `uploads/html-workflow.md` | Kopia workflow dowieziona przez export | Mamy własny `html-to-block.md` — ignoruj kopię |
| `screenshots/*.png` | Wizualna referencja + historia iteracji | **Oglądaj** gdy niepewny finalnego wyglądu |
| `team/*.png`, `uploads/*.png` | Zdjęcia/treść | Content → MediaUpload (user uploaduje), NIE do `assets/` |
| `*.html` (top-level: Kontakt.html, Blog.html, Wpis-*.html…) | Duplikaty stron z canvasu | Używaj wersji z `handoff_wp_react/source/` |
| `design-canvas.jsx`, `.design-canvas.state.json`, `.thumbnail`, `*Brand Brief*.html`, `*Logo*.html`, `*Ikona*.html` | Wewnętrzne Claude Design / eksploracje logo | **Ignoruj** |

---

## Site vs Produkt (ważne rozróżnienie)
Export może mieszać dwie rzeczy:
- **Strona marketingowa** (landing + blog + kontakt…) → **bloki Gutenberga** (ten flow).
- **Produkt** (np. panel wtyczki „jawne ceny": `handoff_brand_profil/` pulpit/profil/ustawienia) → to **admin UI wtyczki**, osobny build (React admin lub PHP), NIE bloki strony. Jeśli budujesz produkt → `plugin-mode.md` + traktuj brand_profil jako spec UI.

Na starcie ustal z userem: budujemy stronę (bloki), produkt (wtyczka), czy oba.

---

## Kolejność pracy z exportem

1. **Brand → theme.json.** Czytaj `STYLEGUIDE.md` + `tokens.css`. Zmapuj `:root` CSS vars → slugi `theme.json` (reguły mapowania w `html-to-block.md` §4). Fonty woff2 → `assets/fonts/`.
2. **Inwentaryzacja.** Czytaj `design_handoff_*/README.md` + (dla podstron) `GUTENBERG.md`/`GUTENBERG-pages.md`. Zbuduj tabelę sekcja → blok (istniejący / wariant / nowy). Pomijaj navbar/footer (template parts).
3. **Pytania kickoff** (jeśli brak `project.md`) — patrz CLAUDE.md. Część odpowiedzi już jest w README exportu (wykorzystaj, nie pytaj ponownie).
4. **Konwersja** — każda strona/sekcja przez `html-to-block.md` (Tryb A: 1 sekcja → 1 blok; Tryb B: cała strona → bloki + `wp_insert_post`). Source = `handoff_wp_react/source/*.html` + `design_handoff_*/*.html`.
5. **Budowa bloków** — identycznie jak standard: `block-template.md` + `patterns/` + `css-conventions.md`. Zasady bez zmian.
6. **Strony** — `GUTENBERG-pages.md` (kolejność, front page) → `wp_insert_post` (html-to-block §11).
7. **Treść** — `content/posts.md` (jeśli blog) → CPT/wpisy.
8. **Animacje** — `patterns/animations.md` (reveal/count-up); porównaj z `ANIMACJE.md` exportu.

---

## Ekstrakcja tokenów — tu jest łatwiej niż Figma
Brak API. Tokeny czytasz **wprost z plików**:
- `tokens.css` / `styles.css` → `:root { --... }` = gotowa lista zmiennych (kolory, fonty, spacing).
- `STYLEGUIDE.md` → role + HEX + nazwy komponentów + ton copy.

Zmapuj na semantyczne slugi `theme.json` (nie kopiuj nazw 1:1 — patrz `html-to-block.md` §4). Wartości = źródło prawdy w `theme.json`; mapowania zapisz w `project.md`.

### ⚠️ ZASADA STAŁA — liczby ZAWSZE z `styles.css`, NIGDY z prozy
Kolory, spacing, font-size, clamp, radius, `white-space` — grepuj **wprost z `styles.css`** (`:root` + selektor komponentu). `DESIGN.md`/`STYLEGUIDE.md` = tylko **intencja/role**, NIE liczby — proza w handoffach bywa nieaktualna/przybliżona (np. opis `#ECEEE5`, a realny `styles.css` = `#E6EFEA`). Przed CSS **każdego** komponentu:
```bash
grep -n "selektor-komponentu\|--token" styles.css
```
- **Full-bleed wordmarki / wielkie napisy:** grepuj dokładny `clamp(...)` + `white-space: nowrap` (zgadnięty clamp → łamanie w 2 linie).
- **Grep wszystkie warianty selektora** (`:not()`, `::after`, `.modifier`) — patrz `css-conventions.md`. Literalne wartości z exportu bywają z nieukończonych iteracji → weryfikuj UI screenshotem (Chrome headless + crop), nie tylko kodem.

### Fonty z exportu
- **Variable font:** czytaj `font-variation-settings` z CSS (nie sam `font-weight`) — `css-conventions.md` §Fonty.
- **`@font-face` w theme.json:** każdy `fontFace` z `"fontDisplay": "swap"` (inaczej fallback system-ui przy wolnym ładowaniu). ttf → konwersja na woff2.

---

## Różnica vs flow Figma
| | Figma (figma-gutenberg/) | Claude Design (ten zestaw) |
|--|--------------------------|-----------------------------|
| Input designu | Figma file (MCP API) | Folder exportu (HTML + handoff) |
| Tokeny | `get_design_context` / variables | `tokens.css` + `STYLEGUIDE.md` (grep z plików) |
| Source sekcji | screenshot + design_context na elemencie | gotowe HTML (`source/*.html`) |
| Handoff | brak (czytasz Figmę) | export dowozi GUTENBERG/ANIMACJE/posts |
| Git/iteracja | URL zmienny | pliki w repo, settled po iteracjach |
| Konwersja | figma-to-block | **html-to-block.md** |

Output identyczny: `theme.json` + bloki + strony. Reszta zestawu (patterns, css, plugin-mode, optymalizacja, migracja, recipes) — wspólna.
