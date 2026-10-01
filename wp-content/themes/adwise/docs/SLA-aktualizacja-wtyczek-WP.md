# SLA — Aktualizacja wtyczek WordPress (Novamira MCP)

> Generyczny runbook bezpiecznej aktualizacji wtyczek na produkcji WordPress/WooCommerce.
> Każdy krok ma rollback. Nic nie ruszamy bez backupu.
>
> Konwencje placeholderów:
> - `<site>` — domena / nazwa serwera MCP (np. `novamira-<site>`)
> - `<webroot>` — katalog `public_html` instalacji (ABSPATH)
> - `<backup>` — `<webroot>/../ai-rollback-YYYYMMDD/` (poza web-rootem)
> - `<slug>` — slug wtyczki z `wp plugin list`

---

## 0. Pytania do klienta PRZED startem

Zadaj **wszystkie naraz**, zanim cokolwiek tkniesz:

1. **Okno serwisowe** — kiedy można aktualizować? (ruch, transakcje, kampanie)
2. **Zakres** — wszystkie wtyczki czy tylko wybrane / tylko krytyczne security?
3. **Premium / licencjonowane wtyczki** — czy licencje aktywne? (rollback z repo nie zadziała dla premium → liczy się backup plików)
4. **Płatności / fakturowanie** — czy w trakcie okna idą realne transakcje? (bramki płatności, generowanie faktur)
5. **Kto testuje akceptacyjnie** po update i na jakich ścieżkach (zakup, checkout, faktura, formularze)?
6. **Zgoda na rollback** — w razie problemu przywracać automatycznie czy najpierw pingnąć?

---

## 1. Połączenie + rekonesans

```
MCP: novamira-<site>  →  discover-abilities
```

Zbierz:
- Wersje: WordPress, PHP, locale
- Lista wtyczek (aktywne/nieaktywne) + wersje
- Status Novamira Pro (licencja) — bez niej brak Pro-abilities
- Stack krytyczny (sklep, fakturowanie, płatności, cache, 2FA, multilang)

**Sprawdź dostępne updaty:**
```bash
wp plugin list --update=available \
  --fields=name,title,version,update_version,auto_update \
  --format=json --skip-plugins --skip-themes
```

> ⚠️ **Limit pamięci CLI.** Jeśli `wp` rzuca
> `Fatal error: Allowed memory size of NNN bytes exhausted` (często w wtyczce 2FA lub
> ciężkiej security/logującej), dodawaj `--skip-plugins --skip-themes` do KAŻDEJ
> komendy listującej/aktualizującej. Trwały fix: sekcja 6 (podbicie memory_limit).

**Sklasyfikuj updaty wg ryzyka:**
- 🟢 **Low** — narzędzia, SEO, edytory, logi, drobne UI
- 🟡 **Medium** — ekosystem sklepu, ale nie rdzeń (feedy, automatyzacje, filtry, pola checkout)
- 🔴 **High** — rdzeń biznesu: silnik sklepu (WooCommerce), fakturowanie, bramki płatności, multilang

---

## 2. Backup (warunek konieczny — bez tego STOP)

Katalog poza web-rootem, z datą:
```
<backup> = <webroot>/../ai-rollback-YYYYMMDD/
```

### 2a. Pliki wtyczek (rollback dla premium!)
```bash
tar -czf <backup>/plugins-before.tar.gz -C <webroot>/wp-content plugins
```

### 2b. Baza danych
```bash
wp db export <backup>/db-before.sql \
  --skip-plugins --skip-themes \
  --single-transaction --default-character-set=utf8mb4
```

### 2c. wp-config (jeśli będziesz go ruszać — sekcja 6)
```bash
cp <webroot>/wp-config.php <backup>/wp-config.php.bak
```

**Zweryfikuj że backupy istnieją i mają sensowny rozmiar** zanim ruszysz dalej.
Jeśli sklep WooCommerce — zapisz `woocommerce_db_version` sprzed update (do porównania).

---

## 3. Aktualizacja — grupami, od najmniej ryzykownej

Po każdej grupie sprawdzaj wynik (`status: Updated`).

```bash
# Grupa 🟢 low-risk
wp plugin update <slug1> <slug2> ... --skip-plugins --skip-themes --format=json

# Grupa 🟡 ekosystem sklepu
wp plugin update <slug...> --skip-plugins --skip-themes --format=json
```

### Rdzeń biznesu — POJEDYNCZO + test po każdej

```bash
# Silnik sklepu (np. WooCommerce) — osobno
wp plugin update <core-shop-slug> --skip-plugins --skip-themes --format=json
```

**DB upgrade silnika sklepu** (gdy `--skip-plugins` nie odpala migracji) — przez `execute-php`:
```php
@ini_set('memory_limit','512M');
// Przykład WooCommerce:
if ( class_exists('WC_Install') && WC_Install::needs_db_update() ) {
    WC_Install::install();      // publiczny entrypoint, odpala migracje
}
// sprawdź: get_option('woocommerce_db_version') == WC_VERSION
```
> Inne silniki/wtyczki mają własne procedury migracji DB — sprawdź w docs wtyczki,
> czy po update trzeba dobić upgrade (zwykle robi się sam na pierwszym `admin_init`).

```bash
# Fakturowanie / płatności (premium, krytyczne) — osobno
wp plugin update <invoicing-or-payment-slug> --skip-plugins --skip-themes --format=json
```

---

## 4. Weryfikacja (testy na froncie)

### 4a. Pozostałe updaty = 0
```bash
wp plugin list --update=available --format=count --skip-plugins --skip-themes
```

### 4b. Żadna wtyczka nie padła na deaktywację
```bash
wp plugin list --status=active --format=count   # liczba == sprzed update
```

### 4c. Kluczowe strony — HTTP + skan HTML
Przez `execute-php` (`wp_remote_get`) pobierz i sprawdź:

| Strona | Sprawdź |
|---|---|
| home | HTTP 200, `</body>` obecne |
| sklep / katalog | 200, lista produktów renderuje |
| produkt (single) | 200 |
| koszyk | 200 |
| checkout | 200 (pusty koszyk → redirect na cart = OK) |
| moje konto | 200 |
| kluczowe formularze/landingi | 200 |

W HTML (po wycięciu `<script>`) **nie może być**:
`Fatal error`, `Parse error`, `critical error`, `Notice:`, `Warning:`, `Deprecated:`, `Uncaught`

### 4d. Silnik sklepu — zdrowie po update (jeśli WooCommerce)
```php
WC_Install::needs_db_update();        // false
// outdated templates: porównaj @version override'ów motywu vs core
// (brak katalogu <motyw>/woocommerce = brak ryzyka)
```

### 4e. Log runtime
Sprawdź `wp-content/debug.log` / `error_log` — brak nowych fatali po update.

### 4f. Test głęboki (opcjonalny, wymaga sesji)
Pełna ścieżka biznesowa: **produkt → koszyk → checkout → płatność → faktura**.
Można przepchnąć programowo (utworzyć testowy order, sprawdzić fakturę) —
za zgodą klienta i potem usunąć order.

---

## 5. ROLLBACK (gdy test 4 wykryje regresję)

### 5a. Przywróć pliki wtyczek
```bash
cd <webroot>/wp-content
tar -xzf <backup>/plugins-before.tar.gz   # nadpisuje plugins/
```
> Działa też dla wtyczek premium (z repo by się nie dało).

### 5b. Przywróć bazę
```bash
wp db import <backup>/db-before.sql
```

### 5c. Przywróć wp-config (jeśli zmieniany)
```bash
cp <backup>/wp-config.php.bak <webroot>/wp-config.php
```

### 5d. Rollback pojedynczej wtyczki (gdy winowajca znany, niefirmowa)
```bash
wp plugin install <slug> --version=<stara_wersja> --force \
  --skip-plugins --skip-themes
```

Po rollbacku → ponów weryfikację z sekcji 4. Zgłoś klientowi co padło i na czym.

---

## 6. Stały fix limitu pamięci (opcjonalny, ale zalecany)

Objaw: `wp` bez `--skip-plugins` rzuca OOM (typowo na limicie 128M, w wtyczce 2FA/security).

1. Sprawdź: czy `ini_set('memory_limit','512M')` działa (brak host-capa),
   gdzie jest CLI `php.ini` (`php -i | grep "Loaded Configuration"`).
2. **Backup wp-config** (sekcja 2c).
3. Dodaj do `wp-config.php` między markerami
   `/* Add any custom values... */` a `/* That's all, stop editing! */`:

```php
/* Memory limits - front 256M, admin/CLI/cron 512M (avoids OOM on heavy ops). */
define( 'WP_MEMORY_LIMIT', '256M' );
define( 'WP_MAX_MEMORY_LIMIT', '512M' );
```

> WP ustawia ten limit **zanim załaduje wtyczki**, więc znika fatal zarówno na
> froncie, jak i w CLI. Front nie żre 512M na request.

4. **ZAWSZE zlintuj przed zapisem** (wp-config = PHP, błąd = whitescreen całej strony):
```bash
php -l <tmp_copy_wp-config.php>   # "No syntax errors detected"
```
5. Weryfikacja: `wp plugin list --status=active --format=count` **bez** `--skip-plugins`
   nie pada; front `home` = 200; `ini_get('memory_limit')` = 256M.

> ⚠️ **Gotcha Novamiry:** zapis plików `*.php` przez ability `write-file`/`edit-file`
> jest zablokowany poza `wp-content/novamira-sandbox/`. wp-config edytuj **surowo
> przez `execute-php`** (`file_get_contents` → `str_replace` → lint → `file_put_contents`).

---

## 7. Zamknięcie

- [ ] Wszystkie updaty zrobione, `--update=available` = 0
- [ ] Liczba aktywnych wtyczek == sprzed update
- [ ] Front: kluczowe strony 200, brak błędów w HTML
- [ ] (Sklep) DB silnika == kod, brak outdated templates
- [ ] Backupy zostają na serwerze min. do potwierdzenia stabilności (kilka dni)
- [ ] Raport do klienta: co zaktualizowano (z/na), co testowano, gdzie leży rollback
- [ ] (Po okresie stabilizacji) sprzątnięcie katalogu `<backup>`

---

## Ściąga — kolejność jednym rzutem oka

```
PYTAJ (okno, zakres, premium, płatności, kto testuje, zgoda na rollback)
  → POŁĄCZ + rekonesans (wersje, updaty, ryzyko)
  → BACKUP (pliki tar + DB sql + wp-config)  ← bez tego STOP
  → UPDATE grupami: 🟢 → 🟡 → 🔴 pojedynczo
  → DB upgrade silnika sklepu
  → WERYFIKUJ (0 updatów, aktywne==, strony 200, HTML czysty, log czysty)
  → [regresja?] → ROLLBACK (tar plików / import DB / wp-config)
  → RAPORT + sprzątanie
```

_Flaga przez cały proces:_ `--skip-plugins --skip-themes` na komendach `wp`,
dopóki nie podbity memory_limit (sekcja 6).
