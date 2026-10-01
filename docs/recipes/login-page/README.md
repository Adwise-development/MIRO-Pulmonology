# Recipe: Login Page (branded KV + security)

Deliverable per projekt: **custom URL logowania + branded login screen (KV) + security hardening**. Osobny od flow figma→blok (to PHP module + pluginy, nie blok Gutenberga). **Tryb ad-hoc** — czytaj tylko gdy budujesz login page.

Bazuje na wzorcu **AdWise** (`inc/adwise-login.php`): split-screen 50/50, logo + tagline na ciemnym panelu, floating-squares parallax, restyle formularza. Pod nowy projekt **kopiujesz wzorzec i podmieniasz brand** — nie budujesz od zera.

## Pliki
| Plik | Zawartość |
|------|-----------|
| **README.md** (ten) | Przegląd, kiedy używać, struktura wzorca |
| **workflow.md** | Generyczny workflow: pluginy, hardening, weryfikacja curl |
| **brand-swap.md** | Checklist podmiany wzorca AdWise → nowy brand |
| **template-map.md** | Mapa wzorca (hooki, linie, CSS vars, decyzje UI) |
| **inc/adwise-login.php** | **Sam wzorzec** (~15KB) — gotowy do skopiowania |

## Źródło wzorca
Wzorzec jest **dołączony**: `inc/adwise-login.php` (root theme’u — wdrożony w blueprincie). Przy nowym projekcie kopiuj go do `inc/{brand}-login.php` w theme'ie i podmień brand (brand-swap.md). Mapa pliku (co gdzie) → `template-map.md`.

## Kiedy stosować
Każdy nowy projekt produkcyjny MUSI mieć:
1. Zmieniony URL logowania (slug zamiast `/wp-admin` + `/wp-login.php`)
2. Branded KV login page (split-screen + logo + animacja tła)
3. Hardening (XML-RPC, REST users, author enum, hide WP version)

## NIE implementuj bez zapytania usera (wszystko naraz)
1. Slug login (np. `/panel-{brand}`)
2. Logo SVG brandu (viewBox?)
3. Tagline (1–2 linie, fragment `<strong>` na akcent)
4. Kolory brand (primary/accent + ciemny panel) — z theme.json czy poda?
5. Link brandu (href logo + stopka) + nazwa w stopce
6. Layout/animacja jak AdWise (split 50/50 + floating squares) czy inny wariant?
7. Hardening: tylko podstawy czy też 2FA + Limit Login Attempts?

## Struktura wzorca `inc/{brand}-login.php`
Wszystko w 1 pliku (CSS + JS + PHP + inline SVG). Sekcje:
```
1. Plugin header + ABSPATH check
2. {brand}_login_logo_svg() — inline SVG
3. add_action('login_head')   — <style>: CSS vars (--{brand}-*) + layout + form restyle + mobile
4. add_action('login_header') — <aside class="{brand}-brand"> logo + tagline + footer
5. add_filter('login_message')   — heading "Zaloguj się" + sub text
6. add_filter('login_headerurl') + 'login_headertext' — link do home
7. add_action('login_footer') — <script> parallax floating squares + ambient drift
```

### Wymagania CSS (skrót)
- CSS vars dla kolorów (`:root { --{brand}-accent; --{brand}-navy; }`)
- `.{brand}-brand` panel left 50% — gradient bg, padding clamp, z-index 1
- `.{brand}-brand__squares` absolute inset, z-index 0, overflow hidden
- `#login` flex column center + `margin-left: 50%`
- Labels left, heading/sub/nav/submit center
- Input `border-radius`, focus ring `box-shadow`
- Button uppercase, `width: 100%`
- `@media (max-width: 900px)` — panel stacked top, form pod
- `@media (prefers-reduced-motion: reduce)` — wyłącz ambient drift

### Wymagania JS (floating squares)
- N≈14 kwadratów, random size (60–280px), depth (8–46), rot (−12…+12), phase
- `mousemove` na panelu → `cx`, `cy` znormalizowane (−0.5…+0.5)
- `requestAnimationFrame` lerp (`tx += (cx - tx) * 0.07`) + sin/cos ambient
- `prefers-reduced-motion` → ambient = 0; `mouseleave` → reset

→ workflow.md (instalacja, hardening, weryfikacja) · brand-swap.md (podmiana brandu)
