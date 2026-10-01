# Security — baseline OBOWIĄZKOWY przy każdym wdrożeniu

Dok wynika z realnego incydentu **2026-08-06** (parasite-SEO/kasyna, konto hostingowe z 12 domenami
przejęte w całości). Każda reguła blokuje potwierdzone ogniwo tamtego ataku albo domyka wektor pokrewny.
To nie jest checklist „nice to have" — to minimum, poniżej którego nie wdrażamy.

**Zasada zero: każda kontrola ma JEDNEGO właściciela** (plugin ALBO snippet w theme, nigdy oba) —
zdublowane kontrole konfliktują i usypiają czujność.

---

## Anatomia włamu 2026-08-06 — czego bronimy

| # | Ogniwo ataku | Co zawiodło | Blokada |
|---|--------------|-------------|---------|
| 1 | Brute-force `xmlrpc.php` + `wp-login` (4229 prób z 1 IP) | xmlrpc otwarty, brak limitu prób | §1 |
| 2 | Login admina złamanym hasłem | słabe hasło, standardowy `/wp-login.php` | §1 |
| 3 | Wstrzyknięcie PHP przez `wp-admin/plugin-editor.php` | brak `DISALLOW_FILE_EDIT` | §2 |
| 4 | Webshelle w `mu-plugins/` + `wp-includes/` (nazwy podszyte pod legit pliki) | brak monitoringu integralności | §4 |
| 5 | 12 domen na 1 koncie hostingowym = wspólny filesystem | brak izolacji | §5 |

---

## §1 Logowanie — WYMAGANE

- **Zmiana slugu logowania = OBOWIĄZKOWA na każdym wdrożeniu** (WPS Hide Login).
  Nie pytamy usera „czy zmienić" — pytamy **„jaki slug"**. `/wp-login.php` i `/wp-admin`
  (bez sesji) mają zwracać 404/redirect. Strona logowania (KV) → `recipes/login-page/`.
- **Limit Login Attempts Reloaded** — obowiązkowy (limit prób + blokady IP).
- **xmlrpc.php OFF** (filtry `xmlrpc_enabled`/`xmlrpc_methods` + `.htaccess` deny) +
  REST `/wp/v2/users` unset + blok `?author=N` → pełne snippety w `recipes/login-page/workflow.md`.
- **Hasła:** unikalne, generowane (menedżer), min. 20 znaków. Konta zewnętrzne (podwykonawcy):
  osobne konto per osoba, minimalna rola, przegląd listy adminów co kwartał, kasowanie po
  zakończeniu współpracy — w incydencie na kontach wisiały osoby spoza firmy.
- **Application Passwords: NIE wyłączaj globalnie** (WP MCP go używa) — dedykowany user
  o minimalnej roli (`docs/wp-mcp.md` §Bezpieczeństwo).

## §2 wp-config / edytor plików — WYMAGANE

- `DISALLOW_FILE_EDIT` = `true` **ZAWSZE, też na dev** — to przez edytor pluginów wszedł payload.
- `WP_DEBUG` false na prod · unikalne klucze/sole · `wp-config.php` chmod 600.
- Pełna lista różnic dev→prod: `migracja-prod.md` §8.

## §3 Kod bloków (theme)

- Escaping wg **kontekstu wyjścia**, nie źródła → tabela w `block-template.md`.
- REST: `permission_callback` zawsze; `__return_true` TYLKO publiczny GET.
- SVG upload wyłącznie z sanityzacją + guard capability → `patterns/media-images.md`.
- Sekrety nigdy w repo: `project.md`, `.mcp.json` w `.gitignore` (App Password!).

## §4 Monitoring + wykrywanie (prod)

- **Alert godzinowy** (cron na koncie hostingowym): zmienione `.php`/`.js` (ctime) + md5
  `.htaccess` + skan typowych nazw/loaderów shelli. **Audyt tygodniowy:** `wp core verify-checksums`
  + szukanie PHP w `uploads/`.
- **`mu-plugins/` = ulubione miejsce shelli** — ładują się zawsze, bez aktywacji, a nazwy podszywają
  się pod legit pliki (w incydencie: „automation-installatron.php" obok prawdziwego
  „automation-by-installatron.php"). Każdy nieznany plik tam = alarm.
- **Blokada wykonywania PHP w `uploads/`** (`.htaccess`) — i weryfikacja po każdym deployu,
  bo wtyczki cache/backup potrafią ten plik nadpisać.
- **Logi hostingu rotują ~5 dni** — przy podejrzeniu incydentu zabezpiecz je NATYCHMIAST.

## §5 Architektura hostingu

- **1 serwis/klient = 1 konto hostingowe** (osobny filesystem). Wspólne konto = jeden włam
  kładzie wszystko — w incydencie 12 domen naraz.
- Hasła baz są **jawne w wp-config** — dostęp do plików = dostęp do wszystkich baz na koncie.
  Kolejny argument za izolacją; po incydencie rotujesz WSZYSTKIE.
- WP + wtyczki aktualne (auto-minor ON), nieużywane wtyczki USUWAJ (nie deaktywuj),
  martwe podinstalacje (`/stara-strona`, `/test`) kasuj — to niezałatane wejścia.

## §6 Po incydencie — kolejność działań

1. **Zabezpiecz logi** (rotują!) i podejrzane pliki do kwarantanny (dowody — nie kasuj ślepo).
2. Znajdź **WSZYSTKIE** shelle, nie pierwszy z brzegu — szukaj kopii w `wp-includes/`, `uploads/`,
   `mu-plugins/` (w incydencie: 22 kopie).
3. Zamknij wektor: xmlrpc, `DISALLOW_FILE_EDIT`, slug logowania.
4. Reset haseł: konta uprzywilejowane + konto hostingowe + bazy; unieważnij wszystkie sesje.
5. Włącz monitoring (§4).
6. **RODO: 72h od wykrycia** na ocenę zgłoszenia do UODO; minimum = wpis w wewnętrznym
   rejestrze naruszeń (art. 33 ust. 5) z osią czasu i oceną ryzyka.

---

## Weryfikacja (curl, po każdym deployu)

```bash
curl -sI https://{domena}/xmlrpc.php        | head -1   # → 403
curl -sI https://{domena}/wp-login.php      | head -1   # → 404/302 (slug ukryty)
curl -sI "https://{domena}/?author=1"       | head -1   # → 301 na home
curl -sI https://{domena}/wp-json/wp/v2/users | head -1 # → 401/404
```
