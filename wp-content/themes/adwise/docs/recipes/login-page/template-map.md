# AdWise Login — dokumentacja referencyjna

**Tryb: ad-hoc** — czytaj gdy budujesz/modyfikujesz login KV. Generyczny workflow (pluginy, hardening, weryfikacja) → `workflow.md`. Adaptacja pod inny brand → `brand-swap.md`.

## Co to jest

`inc/adwise-login.php` (root theme’u — wdrożony w blueprincie) (~15KB, dołączony w tym recipe) = **gotowa implementacja** custom login KV pod brand **AdWise**. Jeden plik mu-style (CSS + HTML + JS + SVG inline). To wzorzec — nie buduj od zera, kopiuj `inc/adwise-login.php` (root theme’u — wdrożony w blueprincie) → `inc/{brand}-login.php` i podmieniaj (patrz `brand-swap.md`).

Registered jako require w `functions.php`:
```php
/* Custom login KV */
require_once __DIR__ . '/inc/adwise-login.php';
```

## Wygląd

Split-screen 50/50:
- **Lewa (`.adw-brand`)** — `position: fixed`, navy gradient (`#2B2B5C → #1E1E45`), 14 fioletowych kwadratów z mouse-parallax + ambient sin/cos drift. Layout: logo (góra) → tagline (środek) → footer © (dół) przez `justify-content: space-between`.
- **Prawa (`#login`)** — białe pole formularza, `margin-left: 50%`, flex column centered (`align-items: center` + `justify-content: center`).

Mobile `<900px`: panel stacked na górze (`position: relative`, `min-height: 240px`), formularz pod nim full-width.

## Struktura pliku (mapa hooków)

| Linia | Hook / funkcja | Rola |
|---|---|---|
| 13–15 | `adw_login_logo_svg()` | Inline SVG logo AdWise (196×26, fill `#fff` + `#7855DA`) |
| 19–333 | `login_head` | `<style id="adw-login-css">` — CSS vars + layout + form restyle + mobile + reduced-motion |
| 337–349 | `login_header` | `<aside class="adw-brand">` — squares container + logo (link adwise.pl) + tagline + footer © |
| 357–361 | `login_message` | Heading "Zaloguj się" + sub "Wprowadź swoje dane..." (wstrzykiwane NAD formularzem) |
| 365–370 | `login_headerurl` / `login_headertext` | Link logo → `home_url()`, text → "AdWise" |
| 374–426 | `login_footer` | `<script>` — generuje 14 kwadratów + parallax (`requestAnimationFrame` lerp) + ambient drift |

> `login_form` hook (353–355) jest pusty — heading idzie przez `login_message` (poza `<form>`), nie `login_form` (wewnątrz).

## CSS vars (`:root`) — źródło kolorów

```css
--adw-purple: #7855DA;        /* brand accent — squares, button, focus ring, links */
--adw-purple-hover: #6342c6;  /* button/link hover */
--adw-navy: #2B2B5C;          /* panel gradient start */
--adw-navy-deep: #1E1E45;     /* panel gradient end */
--adw-bg: #ffffff;            /* body bg + form column */
--adw-text: #2B2B5C;          /* heading + label text */
--adw-muted: #7b7b9a;         /* sub text, placeholders, eye icon */
--adw-border: #e2e0eb;        /* input border */
--adw-soft: #f7f6fb;          /* input bg, message bg */
```

## Detale formularza (utrwalone decyzje 2026-05-28)

- **Centrowanie:** `#login` flex column center. `.adw-login-heading` + `.adw-login-sub` + `#nav` + `#backtoblog` + `.submit` → `text-align: center`. Labels (`user_login`, `user_pass`) → JAWNIE `text-align: left; display: block`.
- **Eye button** (show/hide hasła): `.wp-pwd` relative, button absolute `top: 50%; right: 6px; translateY(-50%)`, input `padding-right: 44px`, `.text` ukryty, focus → purple ring.
- **`.forgetmenot`:** flex + `gap: 8px` + `padding-bottom: 24px`.
- **Inputy:** bg `--adw-soft`, `border-radius: 10px`, focus `box-shadow: 0 0 0 3px rgba(120,85,218,0.18)`.
- **Button primary:** purple, uppercase, `letter-spacing: 0.06em`, `width: 100%`, `!important` (override `.wp-core-ui`).
- **`#login_error`:** czerwony wariant (`#fef0f0` bg, `#d63638` border-left).
- **Hidden:** domyślne WP logo (`h1.wp-login-logo`), language switcher.

## Parallax JS (login_footer)

- `N = 14` kwadratów: random size `60–280px`, depth `8–46`, rot `−12°..+12°`, phase, opacity `0.05–0.23`, pozycja `−5%..105%`.
- `mousemove` na panelu → `cx/cy` znormalizowane `−0.5..+0.5`.
- `requestAnimationFrame` lerp: `tx += (cx - tx) * 0.07`. Transform = `translate(tx*depth + ambX, ty*depth + ambY) rotate(rot)`.
- Ambient: `sin(now/2400 + phase)*14` + `cos(now/2800 + phase)*9`.
- `prefers-reduced-motion: reduce` → `reduced = true` → ambient = 0.
- `mouseleave` → `cx = cy = 0` (reset do centrum).

## Brand-specific (to się zmienia per projekt)

| Element | Wartość AdWise | Gdzie |
|---|---|---|
| Logo SVG | `adw_login_logo_svg()` | linia 13–15 |
| Class prefix | `adw-` / `--adw-*` | cały plik |
| Tagline | "Procesowo wdrażamy **marketing B2B**" | linia 342–345 |
| Footer / linki | `https://adwise.pl/` | linia 341, 346 |
| Header text | "AdWise" | linia 369 |
| Kolory | purple `#7855DA` + navy | `:root` 22–32 |
| Login slug (WPS) | `whl_page = panel-gw` | DB option (nie w pliku) |

## Powiązane

- `workflow.md` — generyczny workflow (pluginy WPS Hide Login + Limit Login Attempts, hardening, curl weryfikacja, sandbox workaround)
- `brand-swap.md` — jak z tego wzorca zrobić login pod inny brand (checklist 7 punktów)
- `inc/adwise-login.php` (root theme’u — wdrożony w blueprincie) — sam wzorzec do skopiowania
