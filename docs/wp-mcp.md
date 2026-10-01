# WP MCP — akcje runtime (oficjalny MCP Adapter)

Domyślny mechanizm, żeby Claude **działał na żywym WP** (stawiał strony z bloków, seedował CPT, tworzył formularz CF7, ustawiał front page, czyścił cache) — przez **oficjalny WordPress MCP Adapter** oparty o **Abilities API** (WP 6.9+). Novamira (proprietary `execute-php`) **porzucona**. Tryb **ad-hoc**.

> Budowanie (pliki bloków, `register_post_type`, theme.json, `npm run build`) NIE wymaga MCP — to filesystem + build. WP MCP jest do **akcji runtime** (działania na bazie/WP za usera).

---

## Co jest czym
- **Abilities API** (WP 6.9+, w core): rejestrujesz „ability" = nazwa + schema in/out + `permission_callback` + `execute_callback`.
- **MCP Adapter** (plugin `WordPress/mcp-adapter`): wystawia abilities jako **toole MCP**. Serwer domyślny: `mcp-adapter-default-server`.
- Claude łączy się jako **zalogowany user** (Application Password) i wywołuje abilities. Bezpieczeństwo per ability przez `permission_callback`.

---

## Instalacja
- **Plugin:** pobierz z `github.com/WordPress/mcp-adapter/releases` → aktywuj. (lub `composer require wordpress/mcp-adapter` we własnym pluginie + autoload).
- **Wymagania:** WordPress **6.9+** (Abilities API w core), PHP 7.4+.
- App Password: WP Admin → Users → Application Passwords.

---

## Połączenie (Claude Code — `.mcp.json` projektu lub `~/.claude.json`)

**Lokalnie (LocalWP) — STDIO via WP-CLI** (zalecane na dev):
```json
{ "mcpServers": { "wordpress": {
  "command": "wp",
  "args": ["--path=/ścieżka/do/wp","mcp-adapter","serve","--server=mcp-adapter-default-server","--user=admin"]
} } }
```

**Remote / prod — HTTP proxy + App Password:**
```json
{ "mcpServers": { "wordpress": {
  "command": "npx",
  "args": ["-y","@automattic/mcp-wordpress-remote@latest"],
  "env": {
    "WP_API_URL": "https://site/wp-json/mcp/mcp-adapter-default-server",
    "WP_API_USERNAME": "admin",
    "WP_API_PASSWORD": "application-password"
  }
} } }
```

---

## Out-of-box = read-only
Domyślne toole: `core/get-site-info`, `core/get-user-info`, `core/get-environment-info` + discovery: `mcp-adapter-discover-abilities`, `mcp-adapter-get-ability-info`, `mcp-adapter-execute-ability`.
**Brak tworzenia stron/CPT/bloków bez własnych abilities.**

---

## Akcje budowania = własne abilities (zamiast execute-php)

Żeby Claude stawiał strony / seedował / tworzył CF7 → zarejestruj **typed abilities** w `inc/abilities.php` (require w functions.php), wystaw na serwerze MCP. Kontrakt core (WP 6.9+, zweryfikowany na WP 7.0): **kategorie i abilities to DWA różne hooki** — `wp_register_ability_category()` TYLKO na `wp_abilities_api_categories_init` (poza nim core robi `_doing_it_wrong` + null), `wp_register_ability()` na `wp_abilities_api_init`; `category` WYMAGane i musi wskazywać kategorię z tego pierwszego hooka; wywoływalność REST/MCP wymaga `meta.show_in_rest => true`. Przykład „postaw stronę z listy bloków":

```php
// Kategoria — OSOBNY, wcześniejszy hook (inaczej null → wszystkie abilities z tą kategorią też null).
add_action( 'wp_abilities_api_categories_init', function () {
	wp_register_ability_category( '{ns}-runtime', [ 'label' => 'Akcje runtime', 'description' => '...' ] );
} );

add_action( 'wp_abilities_api_init', function () {
	wp_register_ability( '{ns}/create-page', [
		'label'        => 'Utwórz stronę z bloków',
		'category'     => '{ns}-runtime',
		'input_schema' => [ 'type' => 'object', 'properties' => [
			'title'  => [ 'type' => 'string' ],
			'slug'   => [ 'type' => 'string' ],
			'blocks' => [ 'type' => 'string' ], // markup: <!-- wp:{ns}/hero /--> ...
		], 'required' => [ 'title', 'slug', 'blocks' ] ],
		'output_schema'       => [ 'type' => 'object' ],
		'permission_callback' => fn() => current_user_can( 'publish_pages' ), // realna capability!
		'execute_callback'    => function ( $input ) {
			$slug     = sanitize_title( $input['slug'] );
			$existing = get_posts( [ 'name' => $slug, 'post_type' => 'page', 'post_status' => [ 'publish', 'draft' ], 'numberposts' => 1 ] );
			if ( $existing ) {
				return [ 'status' => 'exists', 'id' => $existing[0]->ID, 'url' => get_permalink( $existing[0]->ID ) ];
			}
			$id = wp_insert_post( [
				'post_type'   => 'page', 'post_status' => 'publish',
				'post_title'  => sanitize_text_field( $input['title'] ),
				'post_name'   => $slug,
				'post_content'=> wp_slash( $input['blocks'] ), // wp_slash — inaczej JSON attrs się psuje (§Pułapki)
			], true );
			if ( is_wp_error( $id ) ) return [ 'status' => 'error', 'message' => $id->get_error_message() ];
			return [ 'status' => 'created', 'id' => $id, 'url' => get_permalink( $id ) ];
		},
		'meta' => [ 'show_in_rest' => true, 'annotations' => [ 'destructiveHint' => true ] ],
	] );
} );
```

Analogicznie: `{ns}/seed-cpt`, `{ns}/create-cf7-form`, `{ns}/set-front-page`, `{ns}/flush-rewrites`. **Typed + permission-gated = bezpieczniej niż arbitralny execute-php.**

> **`seed-cpt` — nie ufaj `post_type_exists()`:** sprawdź `$pto->public && $pto->show_in_rest` + `current_user_can( $pto->cap->create_posts )` per typ. Samo globalne `publish_posts` pozwala roli Author wstrzyknąć `wp_template_part`/`wp_navigation` = trwały HTML w layout (referencja: `inc/abilities.php`).
>
> **Smoke test po rejestracji:** `wp eval 'var_dump( wp_get_ability("{ns}/create-page") );'` — `null` = ability się nie zarejestrowała (zły hook / brak category / literówka). API młode — sygnaturę i wpięcie na serwer MCP zweryfikuj w `developer.wordpress.org/apis/abilities/` + README `WordPress/mcp-adapter`.

---

## Capability matrix
| Akcja | Jak |
|-------|-----|
| Strona z bloków | ability `create-page` (`wp_insert_post`, content = markup), duplicate-check `get_page_by_path` |
| Wpisy CPT (seed) | ability `seed-cpt` (CPT z `show_in_rest:true`; **meta przez REST → CPT MUSI mieć `'custom-fields'` w supports**, inaczej meta cicho ignorowana — patterns/dynamic-blocks.md) |
| **Rejestracja CPT** | KOD `functions.php` (`register_post_type`) — NIE MCP |
| Media | ability upload / lub WP-CLI `wp media import` |
| Rekord formularza CF7 | ability własna (API CF7) **lub** ręcznie w Kontakt → Formularze |
| Front page / options | ability `set-front-page` (`update_option show_on_front`/`page_on_front`) |
| Flush rewrites | ability `flush-rewrites` (`flush_rewrite_rules`) |
| search-replace (migracja) | **WP-CLI**, nie MCP (migracja-prod.md) |

---

## Pułapki seedowania treści (każda kosztowała realny debug — czytaj PRZED seedem)

### 1. `wp_slash()` przy zapisie contentu z tagami w attrs
`wp_insert_post`/`wp_update_post` **zdziera backslashe** z JSON-a w komentarzach bloków →
`<li>` w attrs wychodzi jako `u003cli` na froncie (renderuje się goły tekst zamiast listy).

```php
wp_update_post( [ 'ID' => $id, 'post_content' => wp_slash( $content ) ] ); // ZAWSZE wp_slash
```

### 2. Attrs bloków buduj przez `wp_json_encode()`, nigdy ręcznym sklejaniem
Ręcznie sklejony JSON z ASCII `"` w treści = **niepoprawny JSON → kses wycina cały blok attrs**,
blok cicho spada na defaulty z `block.json` (objaw: „seed przeszedł, a treści nie ma").

```php
$attrs = wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
$block = '<!-- wp:adwise/' . $name . ' ' . $attrs . ' /-->';
```

### 3. Flush rewrite dla nowego CPT — hook `wp_loaded`, nie `init`
`flush_rewrite_rules()` w `init` **nie zapisuje reguł** (WP odracza `update_option`; opcache/workery
mieszają stan) → single wpisu 404 mimo poprawnego permalinka. Działa:

```php
add_action( 'wp_loaded', function () {
	global $wp_rewrite;
	$wp_rewrite->flush_rules( false );
} );
```

### 4. Po seedzie — weryfikuj front, nie „brak błędu PHP"
`curl` na URL → 200 + sprawdź, czy attrs faktycznie są w HTML (nie defaulty bloku).
Mu-plugin użyty do seedu **usuń po weryfikacji**.

### 5. KAŻDY blok sekcji w seedzie z `"align":"full"` (też `core/image`)
Bez tego blok w edytorze stoi jako constrained i user musi ręcznie przestawiać
(front może wyglądać OK przez template post-content align:full — mylące).
Custom bloki: `{"align":"full",...}` w attrs. `core/image` band dodatkowo klasa na figure:
`<figure class="wp-block-image alignfull size-full adwise-img-band">`.

---

## Fallback (gdy MCP Adapter niedostępny)
1. **WP-CLI:** `wp post create`, `wp eval-file insert.php`, `wp media import`.
2. **Ręcznie:** Claude daje snippet PHP → user wkleja (`wp shell` / panel). Zawsze duplicate-check przed `wp_insert_post`.

---

## Bezpieczeństwo
- `permission_callback` z realną capability — NIGDY `__return_true` dla zapisu/usuwania.
- Dedykowany user o minimalnych prawach dla MCP.
- Publiczny endpoint HTTP → preferuj abilities read-only; akcje zapisu lokalnie (STDIO) lub za App Password.
- Loguj użycie (custom error/observability handler adaptera).

---

## Linki
- MCP Adapter: `github.com/WordPress/mcp-adapter`
- Abilities API: `developer.wordpress.org/apis/abilities/`
- HTTP proxy: `@automattic/mcp-wordpress-remote`
- Alt społecznościowy (REST, prostszy, bez pisania abilities): `kungtekno/wp-mcp` (App Passwords, `wp_create_post`/media/plugins) — gdy nie chcesz rejestrować abilities, ale mniej kontroli (brakuje CF7/flush/arbitrary).
