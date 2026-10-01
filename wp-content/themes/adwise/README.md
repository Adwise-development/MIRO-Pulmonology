# Adwise Blueprint

WordPress block theme blueprint (LocalWP + GitHub). Dwie ścieżki budowy bloków: **Figma** lub **Claude Design (export)**. Budowa bloków identyczna — różni się tylko źródło designu.

## Start
1. Sklonuj/pobierz blueprint do LocalWP: `wp-content/themes/adwise`.
2. Otwórz projekt w Claude Code — Claude czyta `CLAUDE.md` → sekcja **FIRST-RUN**.
3. Claude poprowadzi: **wybór ścieżki** (Figma / Claude Design) → usunięcie zbędnych plików → **preflight** (tooling/pluginy) → **kickoff** (pytania) → **plan → akceptacja** → **aktywacja theme** (pyta, czeka na potwierdzenie).
4. `npm install && npm run build`.

## Co w środku
- `CLAUDE.md` — orchestrator + FIRST-RUN (selektor ścieżki, preflight, kickoff, zasady).
- `docs/` — instrukcje: oba fronty (`figma-to-block.md` / `claude-design-export.md` + `html-to-block.md`) + shared core (css-conventions, block-template, plugin-mode, wp-mcp, optymalizacja, migracja-prod, project-template) + `patterns/` + `recipes/`.
- `inc/adwise-login.php` — branded login KV (**wdrożony**; rebrand → `docs/recipes/login-page/brand-swap.md`).
- `functions.php` / `theme.json` / `parts/` / `templates/` / `webpack.config.js` — baseline theme.

## Wymagania
WP 6.9+ (dla WP MCP Adapter / Abilities API), Node 16+, WP-CLI (LocalWP). Akcje runtime (postaw stronę/CPT/CF7) → `docs/wp-mcp.md`.

`project.md` (config projektu) powstaje na FIRST-RUN — nie jest commitowany w blueprincie.
