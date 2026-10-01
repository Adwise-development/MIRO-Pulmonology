# project.md — szablon

Skopiuj na nowym projekcie jako `project.md` i wypełnij. To **stan projektu** (auto-load) — single source of truth dla rzeczy NIE odczytywalnych z kodu: aktualne tokeny, status bloków, decyzje, log. Aktualizuj po każdej istotnej zmianie.

**Trzymaj chudo (auto-load = ładowany co sesję → token cost).** Nie wklejaj tu kodu, pełnych dumpów, długich opisów ani rzeczy odczytywalnych z plików. Stan zastępuj (aktualizuj wiersz), nie dopisuj kolejnych. Rolling log → przytnij do ostatnich ~10–15 wpisów; starsze usuń lub przenieś do `project-archive.md` (NIE auto-load).

> Usuń ten akapit i komentarze `<!-- … -->` po wypełnieniu.

---

## 1. Overview
- **Nazwa:** <!-- np. RB Events -->
- **Cel:** <!-- co to za strona / firma -->
- **Typ:** theme / plugin <!-- plugin → patrz plugin-mode.md -->
- **Namespace bloków:** <!-- = folder theme'u/plugin, underscores→hyphens -->
- **Prefix PHP:** <!-- skrót, snake_case -->
- **Język:** pl_PL

## 2. Środowisko
- **Dev:** <!-- LocalWP, ścieżka, URL local -->
- **Blueprint:** <!-- z jakiego blueprinta LocalWP / co w baseline (WP ver, pluginy) -->
- **WP MCP:** <!-- Adapter skonfigurowany? transport STDIO/HTTP, App Password — patrz wp-mcp.md -->
- **Prod:** <!-- domena -->
- **Hosting / cache:** <!-- np. LiteSpeed + LSCache / Cloudflare -->
- **PHP / WP:** <!-- wersja na dev / prod (WP 6.9+ dla MCP Adapter) -->

## 3. Design (Figma)
- **Link / file key:** <!-- -->
- **Widoki:** desktop 1440 / mobile <!-- szer. mobile z Figmy -->

## 4. Tokeny (aktualne mapowania)
Wartości = źródło prawdy `theme.json`. Tu mapowanie slug → rola w Figmie (reguły → figma-to-block.md).

| slug theme.json | wartość | rola (Figma) |
|-----------------|---------|--------------|
| primary | <!-- #... --> | <!-- nagłówki / accent --> |
| base | | tekst |
| background | | tło |
| <!-- … --> | | |

Fonty: <!-- rodziny + wagi --> · Spacing/font-sizes: <!-- jeśli warto odnotować -->

## 5. Strony / szablony
<!-- lista stron + przypisane szablony FSE / page-landing (plugin) -->

## 6. CPT / taksonomie
<!-- nazwa CPT, taxonomy, pola; lub "brak" -->

## 7. Bloki (status)
| Blok | Prefix CSS | Typ (static/dynamic) | Status |
|------|------------|----------------------|--------|
| <!-- hero --> | <!-- hero --> | static | todo / w toku / gotowy |

## 8. Custom funkcje / integracje
- Formularz: <!-- CF7 id, do czego -->
- Slider / dynamic grid / login page / inne: <!-- -->

## 9. Decyzje per-projekt
<!-- nietypowe ustalenia: warianty zamiast bloków, full-width sekcje, dodatkowe breakpointy, override template part, itp. -->

## 10. Rolling log
<!-- data — co zrobiono / zdecydowano. Najnowsze na górze. Max ~10–15 wpisów — starsze tnij/archiwizuj. -->
- YYYY-MM-DD — utworzono projekt, kickoff.
