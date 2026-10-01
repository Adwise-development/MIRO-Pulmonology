# Migracja na produkcję

Wdrożenie block theme / plugin z lokalnego (LocalWP / Local by Flywheel) na prod. Tryb **ad-hoc**. Kolejność = workflow.

---

## 0. Przed migracją
- **Backup prod** (jeśli istnieje): DB + `wp-content` z panelu hostingu LUB `wp db export` + zip.
- **Backup local:** `wp db export` + zip theme/uploads.
- **Parytet wersji:** PHP local ≈ prod (ten sam minor, np. 8.2.x), ten sam DB prefix.
- **Build produkcyjny:** `npm run build` (commit `build/` lub dołącz do paczki — na prod zwykle nie ma node).

---

## 1. Transfer plików
Spakuj theme/plugin bez śmieci:
```bash
zip -r theme.zip wp-content/themes/{theme} \
  -x "*/node_modules/*" "*/.git/*" "*/.DS_Store"
```
| Wgrywam | Pomijam |
|---------|---------|
| `themes/{theme}/` (z `build/`) | `node_modules/` |
| `wp-content/uploads/` (media) | `.git/`, `.claude/` |
| `.htaccess` (jeśli custom) | źródła `blocks/*` opcjonalnie (potrzebny tylko `build/`) |

Metoda: SFTP / File Manager hostingu / WP-CLI. (Jeśli masz WP MCP z ability deploy/upload — możesz przez nią; patrz `wp-mcp.md`.)

---

## 2. Baza danych
Export local → import prod:
```bash
# local
wp db export dump-$(date +%Y%m%d).sql
# prod (SSH)
wp db import dump-YYYYMMDD.sql
# lub phpMyAdmin → Import (duże pliki: split / zwiększ limit)
```

---

## 3. Search-replace URL (KRYTYCZNE)
WP serializuje dane (`post_content`, `postmeta`, `options`) — **zwykły SQL `REPLACE` psuje serializowane stringi** (zła długość). Użyj WP-CLI (rozpakowuje → zamienia → re-serializuje):
```bash
# Najpierw dry-run
wp search-replace 'http://{local-domain}' 'https://{prod-domain}' --all-tables --skip-columns=guid --dry-run
# Jeśli OK:
wp search-replace 'http://{local-domain}' 'https://{prod-domain}' --all-tables --skip-columns=guid
```
Bez WP-CLI: narzędzie „Better Search Replace" (plugin) — też serializacja-safe. Nigdy goły SQL na `post_content`.

Sprawdź core options:
```bash
wp option get siteurl   # → https://{prod-domain}
wp option get home      # → https://{prod-domain}
```

---

## 4. Permalinki + .htaccess
```bash
wp rewrite flush
```
Lub Ustawienia → Bezpośrednie odnośniki → Zapisz. Jeśli 404 na wszystkich podstronach → brak/niepełny `.htaccess` (skopiuj z dev lub odtwórz z panelu permalinków).

---

## 5. SSL / mixed content
- Wymuś HTTPS (zwykle panel hostingu / Let's Encrypt). Force w `.htaccess`:
  ```apache
  RewriteCond %{HTTPS} off
  RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
  ```
- Mixed content (http:// zasoby na https stronie): naprawia search-replace z §3. Sprawdź konsolę przeglądarki.

---

## 6. Cache + transients
```bash
wp transient delete-all
# LiteSpeed:
wp litespeed-purge all   # lub plugin → Purge All
```
CDN (Cloudflare/Bunny) → purge. Hard refresh w przeglądarce. Konfiguracja cache → `optymalizacja.md`.

---

## 7. Indeksowanie (łatwo zapomnieć)
Dev zwykle ma „odradzaj wyszukiwarkom" (`blog_public = 0`). Na prod **włącz indeksowanie**:
```bash
wp option get blog_public      # 0 = noindex (dev), 1 = indexable (prod)
wp option update blog_public 1
```
Sprawdź `robots.txt` + brak globalnego `noindex` (SEO plugin).

---

## 8. Hardening prod (różnice vs dev)
`wp-config.php`:
```php
define( 'WP_DEBUG', false );
define( 'WP_DEBUG_DISPLAY', false );
define( 'DISALLOW_FILE_EDIT', true );   // wyłącz edytor plików w adminie
```
- Login slug + KV + XML-RPC/REST hardening → `recipes/login-page/` (workflow.md).
- SVG mime/sanitizer (jeśli theme dopuszcza SVG) → `patterns/media-images.md`.
- File permissions: pliki 644, foldery 755, `wp-config.php` 600.

---

## 9. Template parts (FSE) — gotcha
Jeśli edytowano header/footer w **Site Editor** na dev → siedzą w DB (`wp_template_part`) i jadą z importem DB, nadpisując `parts/*.html`. Edytuj w pliku, nie w Site Editorze; reset override → usuń post `wp_template_part`. Szczegóły → `patterns/editor-gotchas.md` §4.

---

## 10. Checklist po wdrożeniu
- [ ] `curl -I https://{prod-domain}` → 200 (nie 301-loop/404)
- [ ] Strona główna + podstrony renderują się (permalinki OK)
- [ ] Brak mixed content / 404 na assetach (konsola, Network)
- [ ] Formularze CF7: test wysyłki + dotarcie maila (sprawdź spam)
- [ ] Mobile: bloki responsywne (@1024 / @600)
- [ ] `blog_public = 1`, robots.txt OK
- [ ] Cache + CDN wyczyszczone
- [ ] Login slug działa, `/wp-admin` i `/wp-login.php` zachowują się wg hardeningu
- [ ] Hardening curl-checks przeszły (xmlrpc 403, wp-login 404, ?author 301, /users 401) — security.md §Weryfikacja
- [ ] Lighthouse / PageSpeed mobile (optymalizacja.md)
- [ ] Webp serwowany (DevTools → Network → typ obrazów = webp, nie png/jpg) — LSCache Image Optimization przeszedł

---

## 11. Rollback
Trzymaj backup prod z §0. Przy awarii:
```bash
wp db import prod-backup-YYYYMMDD.sql   # przywróć DB
# przywróć theme z backupu
wp rewrite flush && wp transient delete-all && wp litespeed-purge all
wp option get siteurl   # weryfikacja
```

---

## Gotchas
| Problem | Przyczyna | Fix |
|---------|-----------|-----|
| 404 na wszystkich podstronach | brak/niepełny `.htaccess` / rewrite | `wp rewrite flush`, skopiuj `.htaccess` |
| Serialized data error / popsute bloki | SQL replace zamiast WP-CLI | reimport + `wp search-replace` |
| Mixed content warnings | http:// w DB | `wp search-replace` http→https |
| Stare CSS/SVG | cache plugin/CDN | purge all + hard refresh |
| Strona nie w Google | `blog_public = 0` z dev | `wp option update blog_public 1` |
| Biały ekran, brak logów | `WP_DEBUG false` ukrywa błąd | tymczasowo `WP_DEBUG_LOG true`, `tail wp-content/debug.log` |
| Header/footer z danymi localhost | template part override w DB | edytuj plik / usuń `wp_template_part` |
| 403 na plikach | złe permissions | 644 pliki / 755 foldery |
