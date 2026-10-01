# Login Page — podmiana brandu (AdWise → nowy)

Wzorzec `inc/adwise-login.php` jest pod **AdWise** (kolory, logo, link adwise.pl). Pod nowy projekt **kopiujesz i podmieniasz tylko brand** — layout/animacja/form restyle/mobile/a11y zostają.

## Zasada
**NIE buduj od zera.** Kopia `inc/adwise-login.php` → `inc/{brand}-login.php`, podmień 7 rzeczy, resztę zostaw.

## Pytania (naraz, na starcie)
1. Logo SVG brandu (paste/URL Figma) — wymiary viewBox?
2. Tagline (1–2 zdania, który fragment `<strong>` na akcent)?
3. Kolory: primary/accent + ciemny panel (gradient) — z theme.json czy podasz?
4. Link brandu (href logo + footer) + nazwa w stopce/header?
5. Login slug (np. `/panel-{brand}`) → workflow.md
6. Layout/animacja jak AdWise (split 50/50 + floating squares) czy zmiana?

## Checklist podmiany (TYLKO to)
| # | Co | Gdzie | Jak |
|---|----|-------|-----|
| 1 | **Prefix** | cały plik | `adw-`→`{xx}-`, `--adw-*`→`--{xx}-*`, `adw_login_logo_svg`→`{xx}_login_logo_svg`, id `adw-squares`/`adw-login-css`/`adw-login-js`→`{xx}-*` |
| 2 | **Logo SVG** | `{xx}_login_logo_svg()` | Wklej SVG brandu. `fill="var(--fill-0,...)"`→`currentColor` lub jawny hex. Dopasuj `width` w `.{xx}-brand__logo svg` |
| 3 | **Kolory** | `:root` | Podmień `--{xx}-accent`, `--{xx}-navy`/`--{xx}-navy-deep` (gradient panelu). Reszta (bg/text/muted/border) zwykle bez zmian |
| 4 | **Tagline** | `login_header` `.{xx}-brand__tagline` | Tekst brandu, `<strong>` na akcent, `<br>` jeśli 2 linie |
| 5 | **Linki + nazwa** | `login_header` (logo `<a href>` + footer ©) | URL brandu, `aria-label`, nazwa w stopce |
| 6 | **Header text** | `login_headertext` filter | Nazwa brandu (`login_headerurl` zostaw `home_url()`) |
| 7 | **Require** | `functions.php` | Ścieżka require → `inc/{brand}-login.php`. Usuń stary require jeśli nadpisujesz |

> **Squares accent** czerpią z `--{xx}-accent` (`.{xx}-brand__sq { background: var(--{xx}-accent) }`) — zmiana w `:root` wystarcza, JS nie ruszasz.

## Czego NIE ruszać (zostaje z AdWise)
- Layout split 50/50 + mobile `<900px` stacked
- Form restyle (inputy radius, focus ring, eye button, `.forgetmenot`, button uppercase 100%)
- Parallax JS (N≈14, lerp 0.07, ambient sin/cos, `prefers-reduced-motion`)
- Centrowanie (`#login` flex center, labels left, heading/sub/nav center)
- `#login_error` czerwony wariant, hidden language switcher

Inny layout/animacja (centered card, gradient blobs) = NIE „podmiana brandu”, tylko nowy wariant → planuj osobno (README.md pyt. 6).

## Po podmianie
1. Build niepotrzebny (PHP, nie blok).
2. Slug + hardening + curl-verify → workflow.md.
3. Cache purge (LiteSpeed) po zmianie.
