# Blok `adwise/hero` — brandowany home hero (styl login KV)

**Data:** 2026-08-12
**Status:** zaakceptowany (brainstorming)

## Cel

Pierwszy custom blok w blueprincie: brandowany hero na stronę główną w stylu login KV
(split-screen, purple, floating squares). Zastępuje toporny domyślny widok. Baseline
rebrandowalny per projekt. Zarazem **wzorzec referencyjny** dla kolejnych bloków blueprintu
(dziś `blocks/` jest pusty — tylko `.gitkeep`).

## Decyzje (z brainstormingu)

- **Zakres:** hero-only (jeden mocny ekran, jak login).
- **Forma:** custom blok React + SSR (`blocks/hero/`), nie pattern/template.
- **Branding:** adwise purple `#7855DA` (spójny 1:1 z login KV), rebrand przez tokeny.
- **Treść:** generyczny placeholder (do podmiany per projekt).
- **Układ:** split-screen (lewa purple panel + squares, prawa jasna treść).

## Struktura plików

```
blocks/hero/
  block.json     namespace adwise/hero, supports, attributes
  index.js       rejestracja
  edit.js        inline editing (edytor = front 1:1)
  save.js        return null (SSR)
  render.php     SSR + escaping wg kontekstu
  style.scss     split-screen layout, purple tokeny, @900px stack
  editor.scss    tylko chrome edytora
  view.js        floating squares + parallax (reduced-motion)
```

## Atrybuty (wszystkie inline — zero sidebara)

| Atrybut | Typ | Kontrolka (edit.js) | Render |
|---------|-----|---------------------|--------|
| `logo` | object `{id,url,alt}` | MediaUpload (trigger `<button>` + ✕, fallback placeholder) | `<img>` na panelu (opcjonalny) |
| `panelHeading` | string | RichText (na purple panelu) | tagline/claim na dole panelu |
| `eyebrow` | string | RichText inline | mały tekst nad heading (prawa) |
| `heading` | string | RichText inline | `<h1>` — JEDYNY H1 na home (SEO) |
| `lead` | string | RichText inline | akapit pod heading |
| `ctaText` | string | RichText inline (w buttonie) | tekst CTA |
| `ctaUrl` | string | LinkControl (popover) | `href` przycisku (`esc_url`) |
| `anchor` | string | (core) | `id` — jawnie w attributes (SSR) |
| `className` | string | (core) | jawnie w attributes (SSR) |

## Layout

- **Lewa 50% — purple panel:** gradient navy→navy-deep + akcent `#7855DA` (jak login),
  `#hero-squares` (kontener floating squares, parallax), opcjonalne `logo` u góry,
  `panelHeading` na dole. Full-height (min 100vh lub duży clamp).
- **Prawa 50% — jasna treść:** `eyebrow` → `heading` (h1) → `lead` → button CTA. Wyśrodkowana pionowo.
- **Mobile <900px:** `flex-direction: column` — panel purple góra, treść dół (breakpoint jak login).

## Interakcja (`view.js`)

Floating squares generowane w JS (N≈14, losowe rozmiary/pozycje/opacity), parallax na
`mousemove` panelu, `prefers-reduced-motion: reduce` → statyczne (bez rAF, bez mousemove).
Logika przeniesiona z `assets/js/login.js` (sprawdzona), z cache `getBoundingClientRect`.

## Styl

Purple tokeny lokalnie w `style.scss` (`--hero-purple: #7855DA` itd., spójne z login).
Clamp na wartościach (font/padding/gap), breakpoint TYLKO na layout (@900px). `box-sizing:
border-box`, `max-width: 100%` gdzie trzeba. Bez sztywnego `height` na kontenerach flex.
`editor.scss` = tylko chrome (dashed border pickera logo, placeholder CTA).

## Zgodność z blueprintem

- `supports`: `{ "html": false, "anchor": true, "customClassName": true, "align": ["wide","full"], "color": false, "spacing": false }`
- `save → null` (SSR), `anchor`+`className` jawnie w `attributes`
- Escaping wg kontekstu: `wp_kses_post` dla RichText, `esc_url` dla `ctaUrl`, `esc_attr`/`esc_html` reszta
- MediaUpload: trigger `<button>` + ✕, fallback theme przez `window.ADWISE.themeUri`
- Rejestracja automatyczna: webpack glob → `build/blocks/hero/` → `functions.php` glob

## Integracja i weryfikacja

1. `npm run build` → blok w `build/blocks/hero/`, `php -l render.php`, `npm run lint:js`
2. Ustawić blok na stronie Home (front page id 8 tej instancji) — seed przez wp-cli
   (`wp_slash` + `wp_json_encode` attrs, `align:full`) lub edytor
3. Weryfikacja na żywym WP (port 10023, świeży opcache): render front, edytor = front,
   parallax, mobile stack, reduced-motion

## Poza zakresem (YAGNI)

Warianty kolorystyczne, wiele sekcji, animacje scroll, konfiguracja w sidebarze.
Rebrand kolorów = edycja tokenów `style.scss` / mapowanie do `theme.json`.
