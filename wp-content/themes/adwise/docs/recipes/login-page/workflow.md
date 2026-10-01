# Login Page — generyczny workflow

Instalacja, hardening, weryfikacja. Niezależne od brandu (podmiana brandu → brand-swap.md).

---

## Stack standardowy
| Komponent | Implementacja |
|-----------|---------------|
| Custom slug | **WPS Hide Login** (`wps-hide-login`) — option `whl_page` + `whl_redirect: 404` |
| Brute force | **Limit Login Attempts Reloaded** (`limit-login-attempts-reloaded`) — domyślnie 4 próby / 20 min |
| Custom KV | `inc/{brand}-login.php` (require w functions.php) |
| XML-RPC | filter `xmlrpc_enabled`→false + `xmlrpc_methods`→`[]` + `.htaccess Deny` |
| REST users | filter `rest_endpoints` unset `/wp/v2/users` |
| Author enum | `template_redirect` blok na `?author=N` |
| Hide WP ver | `remove_action('wp_head','wp_generator')` + `the_generator` empty |
| X-Pingback | `wp_headers` filter unset header |

---

## Kroki
1. **Zapytaj** usera (7 pytań → README.md) — wszystkie naraz.
2. **Zainstaluj pluginy:** `wps-hide-login`, `limit-login-attempts-reloaded` (WP-CLI lub `Plugin_Upgrader`).
3. **Set options:** `whl_page = {slug}`, `whl_redirect = 404`.
4. **Stwórz** `inc/{brand}-login.php` (kopia wzorca + podmiana brandu → brand-swap.md).
5. **Require** w `functions.php`:
   ```php
   /* Custom login KV */
   require_once __DIR__ . '/inc/{brand}-login.php';
   ```
6. **Hardening** w functions.php (idempotent — sprawdź marker, np. `{NS}_SECURITY_HARDENING`):
   ```php
   add_filter( 'xmlrpc_enabled', '__return_false' );
   add_filter( 'xmlrpc_methods', '__return_empty_array' );
   add_filter( 'rest_endpoints', function ( $e ) {
       unset( $e['/wp/v2/users'], $e['/wp/v2/users/(?P<id>[\d]+)'] );
       return $e;
   } );
   add_action( 'template_redirect', function () {
       if ( is_author() || ( ! is_admin() && isset( $_GET['author'] ) ) ) {
           wp_safe_redirect( home_url(), 301 ); exit;
       }
   } );
   remove_action( 'wp_head', 'wp_generator' );
   add_filter( 'the_generator', '__return_empty_string' );
   add_filter( 'wp_headers', function ( $h ) { unset( $h['X-Pingback'] ); return $h; } );
   ```
7. **.htaccess** — blok xmlrpc na górze (przed `# BEGIN WordPress`):
   ```apache
   # BEGIN {NS}_XMLRPC_BLOCK
   <Files xmlrpc.php>
   <IfModule mod_authz_core.c>Require all denied</IfModule>
   <IfModule !mod_authz_core.c>Order deny,allow
   Deny from all</IfModule>
   </Files>
   # END {NS}_XMLRPC_BLOCK
   ```
8. **Flush rewrites + purge cache:**
   ```php
   flush_rewrite_rules( false );
   if ( class_exists( '\\LiteSpeed\\Purge' ) ) { \LiteSpeed\Purge::purge_all( 'manual' ); }
   ```
9. **Weryfikacja (curl):**
   - `/wp-admin` → 302 → `/404/`
   - `/wp-login.php` → 404
   - `/{slug}/` → 200 (login z custom KV)
   - `/?author=1` → 301 → home
   - `/xmlrpc.php` → 403
   - `/wp-json/wp/v2/users` → 404/401
   - `<meta name="generator">` → brak w HTML
10. **ZAPISZ adres** dla usera + ostrzeżenie: bez slug dostęp tylko przez reset DB option.

---

## Sandbox / write-blocked PHP
Niektóre hostingi/sandboxy blokują zapis plików PHP przez write/edit API. Workaround: runtime PHP `file_put_contents` (przez WP-CLI `wp eval-file`, ability WP MCP, lub ręczny snippet). Duże pliki (>10KB): base64 inline + `base64_decode` po stronie WP.

## Bezpieczeństwo
- Slug login trzymaj poza repo publicznym (sekret).
- 2FA / captcha tylko jeśli user chce (pytanie 7).
- Hardening idempotentny — marker, żeby nie dublować przy re-run.
