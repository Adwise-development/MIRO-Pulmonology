# FIRST-RUN — start nowego projektu z blueprintu

Czytaj TYLKO przy świeżym klonie Adwise Blueprint (nowy projekt). W projekcie już prowadzonym
ten plik nie ma zastosowania — stan bieżący trzyma `project.md`.

---

## Kolejność (raz, po wgraniu z GitHuba)

1. **Wybór ścieżki — zapytaj usera:** „Budujemy z **Figmy** czy z **Claude Design (export)**?"
2. **Prune — usuń pliki nieużywanej ścieżki:**
   - **Figma** → usuń `docs/claude-design-export.md` + `docs/html-to-block.md`
   - **Claude Design** → usuń `docs/figma-to-block.md`
   (Blueprint trzyma oba komplety; w projekcie zostaje jeden. Prune zrób OD RAZU — inaczej
   martwe doki wiszą w tabeli index i mylą kolejne sesje.)
3. **Wprowadzenie** — przedstaw plan i kolejność (niżej).
4. **Preflight** — wykryj tooling, potwierdź pluginy (niżej).
5. **Kickoff** — zadaj pytania (wszystkie naraz, niżej), utwórz `project.md` z `docs/project-template.md`.
6. **Plan → akceptacja** przed kodem.
7. **Aktywacja theme** — zaproponuj `wp theme activate adwise` (WP-CLI lub WP MCP) → **zapytaj i czekaj na potwierdzenie**.
8. **Po ukończeniu** — przepisz sekcję FIRST-RUN w CLAUDE.md na „Stan projektu" (wzór niżej).

**Login KV już wdrożony** (`inc/adwise-login.php`) — NIE odbudowuj; rebrand pod inny brand tylko przez `docs/recipes/login-page/brand-swap.md`.

## Wprowadzenie (mów userowi na starcie)
> Plan: 1) wybór ścieżki (Figma / Claude Design) + sprzątnięcie zbędnych plików, 2) preflight (WP MCP / Figma MCP / WP-CLI / pluginy), 3) tokeny → `theme.json`, 4) bloki sekcja po sekcji (plan → akceptacja → kod), 5) strony (WP MCP / WP-CLI), 6) build + test (edytor + front), 7) aktywacja theme. Lecę?

## Preflight — sprawdź, potwierdź z userem (✓/✗)
- **WP MCP Adapter** (akcje runtime) — wykryj tool MCP `wordpress` / `mcp-adapter-discover-abilities`. ✗ → fallback WP-CLI / ręczny snippet (`docs/wp-mcp.md`).
- **Figma MCP** (`figma:*` / `use_figma`) — TYLKO ścieżka Figma. ✗ → user dostarcza screeny/wartości ręcznie.
- **WP-CLI** (`wp --version`) + **Node/npm** (`npm -v`).
- **Skille Claude:** `claude-md-management`, `time-tracker`, `context7` (+ `figma` dla ścieżki Figma).
- **Pluginy WP — WYMAGANE:** LiteSpeed Cache (cache + Image Optimization/webp — `docs/optymalizacja.md` §1–2; hosting bez LiteSpeed → zamiennik cache + JEDEN optymalizator obrazów zamiast) · **WPS Hide Login + Limit Login Attempts Reloaded** (zmiana slugu logowania OBOWIĄZKOWA — nie pytaj „czy", zapytaj **o slug**; `docs/security.md` §1).
- **Pluginy WP — wg zakresu:** CF7 (formularz), SVG support — **potwierdź które masz**.
→ Wypisz tabelę braków + co znaczą (Claude działa sam vs dowozi snippet), **czekaj na potwierdzenie**.

## Kickoff — pytania (wszystkie w jednej wiadomości)
1. **Theme czy plugin?** (plugin → `docs/plugin-mode.md`)
2. **Nazwa + namespace + prefix PHP** (namespace = folder theme'u/plugin).
3. **Źródło designu** — Figma link / file key **lub** ścieżka folderu exportu Claude Design. Budujemy **site (bloki) / produkt-wtyczkę / oba**?
4. **Zakres** — single landing / multi-page? ile stron i szablonów?
5. **CPT / taksonomie?** (`docs/patterns/dynamic-blocks.md`)
6. **Custom funkcje** — CF7 / slider / grid dynamiczny / inne integracje?
7. **Środowisko** — LocalWP z blueprinta + domena prod + hosting/cache (LiteSpeed?) + **WP MCP Adapter** skonfigurowany (WP 6.9+, `mcp-adapter`, App Password)?

`project.md` = **utrzymywany plik konfiguracyjny** (env/blueprint/WP MCP/tokeny/bloki/decyzje) — twórz na starcie, aktualizuj po zmianach. `.mcp.json` = config połączenia MCP (`docs/wp-mcp.md`). Oba w `.gitignore` — per-projekt/sekrety (App Password), NIE commituj do blueprintu.

---

## Po ukończeniu first-run — przepisz CLAUDE.md

Sekcję `## ⚡ FIRST-RUN` w CLAUDE.md zastąp sekcją `## Stan projektu`, żeby kolejne sesje
nie czytały martwej procedury:

```markdown
## Stan projektu (po first-run)

Projekt **prowadzony**, nie świeży klon: ścieżka = **<Figma / Claude Design>**, theme aktywny,
`project.md` istnieje — czytaj go zamiast zgadywać stan. Login KV wdrożony (`inc/adwise-login.php`) —
NIE odbudowuj; rebrand tylko przez `docs/recipes/login-page/brand-swap.md`.

Procedura startu nowego projektu z blueprintu → `docs/first-run.md` (ad-hoc, tylko przy świeżym klonie).

`project.md` (stan bieżący) i `.mcp.json` (połączenie MCP, App Password) są w `.gitignore` —
per-projekt, NIE commituj do blueprintu.
```

Zaktualizuj też: nagłówek CLAUDE.md (nazwa projektu, wybrana ścieżka), Index docs (usuń wiersze
doków skasowanych przy prune, dopisz „Auto-load" przy ścieżce która została).
