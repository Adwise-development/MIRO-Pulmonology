# Adwise Blueprint — WordPress Block Theme (Figma / Claude Design → Gutenberg)

Blueprint startowy (LocalWP, z GitHuba). Baseline gotowy: WP theme + login KV (`inc/adwise-login.php`) + (zwykle) WP MCP. Zawiera **dwie ścieżki** budowy bloków: **Figma** lub **Claude Design (export)**. Budowa bloków IDENTYCZNA w obu — różni się tylko „przód" (skąd design). React + server-side render, `@wordpress/scripts` + Webpack 5, SCSS/BEM, PHP. Język: pl_PL.

Wartości specyficzne (nazwa, tokeny, prefiksy) odczytuj z `style.css` / `theme.json` / `functions.php` i z designu — nie hardcoduj.

---

## ⚡ FIRST-RUN — świeży klon? Wykonaj docs/first-run.md PRZED wszystkim

Świeży klon z GitHuba (brak `project.md`) → przeczytaj i wykonaj **`docs/first-run.md`**: wybór ścieżki (Figma / Claude Design), prune doków nieużywanej ścieżki, wprowadzenie, preflight, kickoff (`project.md` z szablonu), plan → akceptacja, aktywacja theme.

Po ukończeniu first-run **przepisz tę sekcję** na `## Stan projektu` (wzór na końcu docs/first-run.md) — kolejne sesje nie czytają wtedy martwej procedury.

**Login KV już wdrożony** (`inc/adwise-login.php`) — NIE odbudowuj; rebrand pod inny brand tylko przez `docs/recipes/login-page/brand-swap.md`.

`project.md` (stan bieżący) i `.mcp.json` (połączenie MCP, App Password) są w `.gitignore` — per-projekt/sekrety, NIE commituj do blueprintu.

---

## Index docs (`docs/`)

| Plik | Zawartość | Tryb |
|------|-----------|------|
| **CLAUDE.md** (ten) | Reguły, decision-guides, zasady | Auto-load |
| **docs/first-run.md** | Start nowego projektu z blueprintu (ścieżka, prune, preflight, kickoff) | Ad-hoc (świeży klon) |
| **docs/figma-to-block.md** | *Front Figma:* API, tokeny → theme.json, Figma → clamp | Auto-load (ścieżka Figma) |
| **docs/claude-design-export.md** | *Front Claude Design:* anatomia exportu, kolejność czytania | Auto-load (ścieżka Claude Design) |
| **docs/html-to-block.md** | *Front Claude Design:* konwersja HTML → bloki (Tryb A/B, wp_insert_post) | Ad-hoc (ścieżka Claude Design) |
| **docs/css-conventions.md** | BEM, breakpointy, clamp, sekcja/inner, box-sizing, hover, iOS, tel | Auto-load |
| **docs/block-template.md** | Bare scaffolding bloku | Auto-load |
| **docs/project-template.md** | Szablon `project.md` | Ad-hoc |
| **docs/patterns/** | navbar-menu, forms, backgrounds, media-images, buttons-links, dynamic-blocks, slider, animations, editor-gotchas | Ad-hoc |
| **docs/plugin-mode.md** | Bloki jako plugin (bootstrap, tokeny, scope, template) | Ad-hoc |
| **docs/wp-mcp.md** | Akcje runtime (WP MCP Adapter): postaw stronę, seed CPT, CF7 | Ad-hoc |
| **docs/security.md** | Baseline security (lekcje z incydentu 2026-08-06): login, hardening, monitoring, po-incydencie | Ad-hoc (wdrożenie/prod) |
| **docs/optymalizacja.md** | Performance & a11y: cache, obrazy, fonty, JS, CWV, WP_Query | Ad-hoc |
| **docs/migracja-prod.md** | Wdrożenie dev→prod | Ad-hoc |
| **docs/recipes/login-page/** | Rebrand login KV (live: `inc/adwise-login.php`) | Ad-hoc |

**Auto-load:** CLAUDE.md + front wybranej ścieżki + docs/css-conventions.md + docs/block-template.md + `project.md` (jeśli istnieje). Patterns/reszta — ad-hoc.

### Decision-guide: zadanie → plik
| Robię… | Czytam |
|--------|--------|
| Pobieram design (Figma) | docs/figma-to-block.md |
| Mam export Claude Design | docs/claude-design-export.md |
| Konwersja HTML → bloki | docs/html-to-block.md |
| Nowy blok od zera | docs/block-template.md + front ścieżki |
| Seed treści / stron (attrs bloków w PHP) | docs/wp-mcp.md §Pułapki |
| Navbar / menu | docs/patterns/navbar-menu.md |
| Formularz | docs/patterns/forms.md |
| Tło sekcji | docs/patterns/backgrounds.md |
| Zdjęcia/ikony/logo | docs/patterns/media-images.md |
| Button / CTA | docs/patterns/buttons-links.md |
| Grid postów / CPT | docs/patterns/dynamic-blocks.md |
| Slider | docs/patterns/slider.md |
| Animacje | docs/patterns/animations.md |
| Coś nie działa w edytorze | docs/patterns/editor-gotchas.md |
| Bloki jako plugin | docs/plugin-mode.md |
| Akcje runtime (postaw stronę, seed CPT, CF7) | docs/wp-mcp.md |
| Performance / a11y | docs/optymalizacja.md |
| Wdrożenie prod | docs/migracja-prod.md |
| Hardening / logowanie / incydent | docs/security.md |
| Rebrand login | docs/recipes/login-page/ |

---

## Architecture
```
blocks/[block-name]/  block.json · index.js · edit.js · save.js (null) · render.php · style.scss · editor.scss · view.js?
build/                # Output (NIE edytować, gitignored — na prodzie `npm run build`)
assets/fonts/ icons/ images/ js/   # js/ = globalne skrypty spoza bloków (np. content-length-limit.js)
templates/ parts/     # FSE templates + parts (header, footer)
inc/                  # PHP modules (adwise-login.php, abilities.php…)
docs/                 # Instrukcje (ten zestaw)
```
Webpack auto-discovers `blocks/*/index.js`+`view.js`; rejestracja: `glob()` w `functions.php` z `build/blocks/*/block.json`.

**Komendy:** `npm run start|build|lint:js|lint:css|format`.
**Weryfikacja po każdej zmianie** (w tej kolejności):
```bash
npm run build && php -l blocks/<blok>/render.php
curl -s -o /dev/null -w '%{http_code}\n' http://<local-url>/<strona>/   # 200 + zero PHP errors w HTML
```
+ purge cache (LiteSpeed: `do_action('litespeed_purge_all')` przez WP-CLI/MCP) gdy cache aktywny a zmiana dotyka frontu.

---

## Header / Footer — template parts (nie PHP, nie pattern)
Header/footer to **FSE template parts** (`parts/header.html`, `parts/footer.html`) z **block markupem**, NIE PHP (`get_header()`).
- Navbar/footer = bloki natywne w template part: `<!-- wp:{namespace}/navbar /-->` w `parts/header.html`.
- Template wskazuje part: `<!-- wp:template-part {"slug":"header","tagName":"header"} /-->`.
- **Wstawiaj edytując plik `parts/*.html`** (źródło prawdy). Site Editor robi DB override (`wp_template_part`) gubiony w gicie (docs/patterns/editor-gotchas.md §4).
- **template parts ≠ block patterns.** Navbar idzie do template **partu**, nie jako pattern.

---

## Akcje runtime (postaw stronę, seed CPT, CF7) — WP MCP
Budowanie (pliki, `register_post_type`, build) NIE wymaga MCP. Żeby Claude **sam** postawił stronę z bloków / seedował CPT / utworzył CF7 / przypiął front page:
- **Domyślnie: oficjalny WP MCP Adapter** (Abilities API, WP 6.9+) — typed abilities w `inc/abilities.php` (`create-page`, `seed-cpt`…). Setup → **docs/wp-mcp.md**. (Novamira porzucona.)
- **Fallback:** WP-CLI (`wp post create`, `wp eval-file`) / ręczny snippet (duplicate-check `get_page_by_path` przed `wp_insert_post`).
- Strony = bloki self-closing `<!-- wp:{namespace}/{block} /-->`; navbar/footer pomijasz (template parts).

---

## Block Conventions

### Supports (KAŻDY blok)
```json
"supports": { "html": false, "anchor": true, "customClassName": true, "align": ["wide","full"], "color": false, "spacing": false }
```
Bloki SSR (`save`→`null`) — `anchor` + `className` **jawnie w `attributes`** (inaczej giną po save).

### Namespace / prefiksy
Namespace = folder theme'u (underscores→hyphens). Textdomain = namespace. Prefix PHP z `functions.php`. Prefix CSS = 2–5 liter per blok.

### Inline vs Sidebar (jedno źródło prawdy)
- **Widoczna na froncie treść** (tekst, obraz, URL, tel, cena) → **inline** ZAWSZE (`RichText`/`MediaUpload`/`LinkControl` popover). Nigdy w sidebarze.
- **Wybór z sensownym podglądem na kanwie** (menu, formularz CF7, post-type) → **inline** editor-only (picker gdy pusto, preview + ✕ gdy wybrane).
- **Czysta konfiguracja bez podglądu** (delay ms, liczba kolumn/slajdów, toggle loop) + **warianty** (`variant`) → **sidebar** (`InspectorControls`).

Test: „czy redaktor widzi efekt tej wartości na karcie?" — tak → inline; nie → sidebar. Szczegóły/pułapki → docs/patterns/editor-gotchas.md.

### Edytor = front 1:1 — KAŻDY blok (egzekwuj per blok)
- **Media trigger = prawdziwy `<button>`** (preview w środku, 1 klik) — NIGDY `<img onClick>` (w Gutenbergu = 2 kliki, picker nie startuje → „brak edycji"). Każdy `MediaUpload` ma „✕".
- **Domyślny asset theme** (fallback w render.php) → IDENTYCZNY fallback w edit.js przez `window.ADWISE.themeUri`; „✕" przywraca domyślny (nie czyści). → docs/patterns/media-images.md.
- Widoczne treści (tel/email, linki, ceny) → **inline**, nie sidebar.

### Repeatable items
`.map()` w podglądzie, „✕" na hover, „+ Dodaj" pod listą (nie sidebar). Scaffolding → docs/block-template.md.

### Warianty zamiast duplikacji
Różni się tylko kolor/tło → atrybut `variant` + klasa modifier (kolory jawnie na każdym elemencie tekstowym wariantu).

---

## Workflow: design → plan → akceptacja → kod
1. **Czytaj design** wg ścieżki — Figma (docs/figma-to-block.md: screenshot → metadata → design_context na 1 elemencie) lub export (docs/claude-design-export.md) → tokeny do `theme.json`.
2. **Pytania do usera — wszystkie naraz** (per blok: typ danych, interaktywność, kolumny, pola, klikalność, full-width).
3. **Plan** — tabela clamp (desktop→mobile), zmiany layout @1024px, atrybuty, struktura. NIE implementuj bez planu.
4. **Czekaj na akceptację.**
5. Konwersja/scaffolding (docs/block-template.md + docs/patterns/) → implementacja.
6. Build → test edytor (desktop + wąski panel) → test front (desktop + mobile).

**Tryb autonomiczny — throughput NIE wypiera rygoru.** Nawet budując wszystkie bloki naraz, PER BLOK: przeczytaj właściwy pattern → przejdź „Checklist nowego bloku" (block-template.md) → diff screenshot vs design + checklista interakcji → dopiero następny blok. Weryfikacja „renderuje + brak błędu PHP" NIE wystarcza.

---

## Zasady BEZWZGLĘDNE
- Plan przed kodem — ZAWSZE · `editor.scss` = TYLKO chrome edytora; layout dziedziczy ze `style.scss` (ładowany też w iframe edytora — NIE kopiuj clamp/breakpointów)
- `box-sizing: border-box` + `max-width: 100%` gdzie trzeba · `anchor`+`className` jawnie (SSR)
- Każdy `MediaUpload` ma „✕" + trigger `<button>` (NIE `<img onClick>`) · Sekcje z overlay → `background-image`, nie `<img>`
- Domyślny asset theme → edit.js fallback `window.ADWISE.themeUri` ≡ render.php (edytor=front)
- Wartości (kolor/spacing/clamp/`white-space`) z `styles.css`/design_context, NIGDY z prozy `DESIGN.md`/`STYLEGUIDE.md`
- Variable font → `font-variation-settings` (nie sam `font-weight`) · `@font-face` w theme.json → `"fontDisplay": "swap"`
- CPT z meta przez REST → `supports: [...,'custom-fields']` · `block.json` bez prostego `"` (waliduj JSON)
- Sygnaturowe animacje → 1:1 z CSS exportu (NIE z pamięci)
- Clamp na wartościach, breakpoint TYLKO na layout
- Buttony → jawny `color` + `-webkit-tap-highlight-color: transparent` · Hover front → `@media (hover: hover)`
- Tel/email → `<a href="tel:/mailto:">` z kolorem · Kontenery bez sztywnego `height`
- Walidacja formularzy → `position: absolute`, bez layout shift
- Navbar → `position: sticky` (NIE pure `fixed`, NIE body padding-top)
- Obrazy user-upload → `object {id,url,alt}` + responsive srcset (NIE do `assets/`)
- Template parts → edytuj w pliku, NIE w Site Editor
- Seed treści w PHP → `wp_slash()` przy zapisie + attrs bloków przez `wp_json_encode()` + flush rewrite na `wp_loaded` (docs/wp-mcp.md §Pułapki)
- Pas obrazu = klasa `adwise-img-band` na `core/image`, NIE własny blok (docs/patterns/media-images.md)

---

## Token-Optimized
- NIE ładuj skilli `brainstorming`/`figma-implement-design`/`using-superpowers` — treść jest tu. Skill Figma `figma:figma-use` tylko przed `use_figma` (ścieżka Figma).
- NIE eksploruj projektu agentem (struktura identyczna). NIE czytaj ad-hoc patterns dopóki nie pracujesz nad tematem.
- Audyt MD: `claude-md-management:claude-md-improver`.

## Baseline / Dependencies
Theme jest już sprovisionowany w blueprincie (functions.php glob + anchor-injection + scroll-margin, theme.json, parts, webpack, login KV w inc/). NIE bootstrapuj WP od zera. Plugin path → docs/plugin-mode.md. Deps w package.json: `@wordpress/scripts` ^30 (+ copy-webpack-plugin, glob). `swiper`/`gsap` NIE są preinstalowane — instaluj per projekt gdy potrzebne (default animacji: vanilla IO, docs/patterns/animations.md).
