# SEVEN — Design System (z Claude-design exportów)

Źródło: 9 bundled exportów `~/Downloads/7/*.html` (zdekodowane → `/tmp/seven-decoded/`).
Single source of truth = `theme.json`. Tu mapowanie surowych wartości designu → slugi + reguły.

## Breakpoint
- **Mobile: `max-width: 860px`** (dominujący w designie; też 820/900/920 — normalizuj do 860).
- Desktop = powyżej 860px. Layout (flex-direction, grid-columns) przełączaj @860px; wartości liczbowe skalują się `clamp()` (nie zmieniaj na breakpoincie).

## Kolory (paleta → slug)
| Rola | HEX | slug |
|---|---|---|
| Tło główne (carbon) | `#0f0f12` | `carbon` |
| Carbon deep | `#0a0b0d` | `carbon-deep` |
| Surface / karty (dominujący) | `#15171b` | `surface` |
| Surface 2 | `#111317` | `surface-2` |
| Surface 3 | `#1a1c21` | `surface-3` |
| Linia / hairline | `#2a2c31` | `line` |
| Border | `#3a3d44` | `border` |
| **Champagne (akcent/brand)** | `#c7b299` | `champagne` |
| Champagne deep (hairline złoty) | `#9a7f5c` | `champagne-deep` |
| Champagne light | `#d9c4a8` | `champagne-light` |
| Porcelain (tekst) | `#ece9e3` | `porcelain` |
| Porcelain dim | `#c9ccd0` | `porcelain-dim` |
| Cream (highlight) | `#fff7ec` | `cream` |
| Titanium (muted) | `#8a8d93` | `titanium` |
| Titanium dim | `#54565a` | `titanium-dim` |
| Signal (koral, akcent/alert) | `#dc5b44` | `signal` |

## Typografia
- **Suisse Intl** (300 light, 400 regular) — nagłówki (light 300) + treść (400). slug `suisse`.
- **Kode Mono** (400, 500) — eyebrow / labelki / meta / CTA, zwykle `text-transform:uppercase` + `letter-spacing`. slug `kode`.
- Fonty: `assets/fonts/seven/` (rejestrowane w theme.json `fontFace`). Suisse = woff2 subset (EN); Kode Mono = ttf.

### Skala font-size (fluid clamp → slug → rola)
| slug | clamp | rola |
|---|---|---|
| `mono-2xs` | `clamp(9px,.7vw,11px)` | mikro-labelki mono |
| `mono-xs` | `clamp(10px,.78vw,13px)` | eyebrow (najczęstszy) |
| `caption` | `clamp(11px,.84vw,14px)` | caption |
| `small` | `clamp(12.5px,.92vw,15px)` | small |
| `body` | `clamp(13px,1vw,16px)` | tekst bazowy |
| `body-lg` | `clamp(14px,1.1vw,18px)` | tekst duży |
| `lead` | `clamp(15px,1.25vw,21px)` | lead |
| `h5` | `clamp(16px,1.4vw,22px)` | H5 |
| `h4` | `clamp(20px,1.95vw,34px)` | H4 |
| `h3` | `clamp(24px,2.4vw,42px)` | H3 |
| `h2` | `clamp(28px,2.6vw,48px)` | H2 |
| `h1` | `clamp(34px,5vw,86px)` | H1 |
| `display` | `clamp(44px,6.4vw,108px)` | display |
| `wordmark` | `clamp(54px,9vw,180px)` | wielki „7" |

## Spacing (fluid clamp → slug)
| slug | clamp |
|---|---|
| `xx-small` | `clamp(4px,.8vh,9px)` |
| `x-small` | `clamp(10px,1vw,14px)` |
| `small` | `clamp(14px,1.2vw,18px)` |
| `medium` | `clamp(14px,1.6vw,26px)` |
| `large` | `clamp(18px,1.8vw,32px)` |
| `x-large` | `clamp(24px,3.4vw,72px)` ← dominujący padding sekcji |
| `xx-large` | `clamp(28px,4vw,80px)` |
| `huge` | `clamp(40px,7vw,140px)` |

## ⚠️ Zasada (css-conventions)
Przy budowie **każdego** bloku bierz DOKŁADNE wartości z sekcji w `/tmp/seven-decoded/<Strona>.html` (inline `style=""`), nie z tej tabeli — tu jest skala semantyczna, w designie bywają wariacje per element. Grep: `grep -o 'style="[^"]*"' plik`.
