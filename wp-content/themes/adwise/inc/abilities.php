<?php
/**
 * WP MCP runtime abilities (Abilities API → MCP Adapter).
 *
 * Wystawia akcje runtime jako typed, permission-gated abilities, które
 * oficjalny plugin `mcp-adapter` udostępnia jako toole MCP. Zamiast arbitralnego
 * execute-php. Patrz: docs/wp-mcp.md.
 *
 * Wymaga: WordPress 6.9+ (Abilities API w core) + plugin WordPress/mcp-adapter.
 *
 * Kontrakt core (zweryfikowany w wp-includes/abilities-api/ + test na WP 7.0):
 *  - kategorie: hook `wp_abilities_api_categories_init` (core robi _doing_it_wrong
 *    + null gdy `wp_register_ability_category` wołane poza tym hookiem),
 *  - abilities: hook `wp_abilities_api_init`,
 *  - `category` WYMAGANE i musi wskazywać kategorię zarejestrowaną wyżej,
 *  - ability wywoływalne przez REST/MCP tylko z `meta.show_in_rest = true`
 *    (domyślnie false → run-controller zwraca 404).
 *
 * Namespace ability = 'adwise'. Zmieniasz nazwę theme → zmień $ns (w obu hookach).
 *
 * @package adwise
 */

defined( 'ABSPATH' ) || exit;

// Kategoria — OSOBNY, wcześniejszy hook niż abilities.
add_action( 'wp_abilities_api_categories_init', function () {
	if ( ! function_exists( 'wp_register_ability_category' ) ) {
		return;
	}
	wp_register_ability_category( 'adwise-runtime', [
		'label'       => __( 'Adwise — akcje runtime', 'adwise' ),
		'description' => __( 'Budowanie stron, seed CPT, front page, CF7 przez MCP.', 'adwise' ),
	] );
} );

add_action( 'wp_abilities_api_init', function () {

	// Brak Abilities API (WP < 6.9 lub plugin nieaktywny) → nie rejestruj (zero fatala).
	if ( ! function_exists( 'wp_register_ability' ) ) {
		return;
	}

	$ns  = 'adwise';
	$cat = "{$ns}-runtime";

	/* --- Postaw stronę z listy bloków --- */
	wp_register_ability( "{$ns}/create-page", [
		'label'        => __( 'Utwórz stronę z bloków', 'adwise' ),
		'description'  => 'Tworzy stronę z podanego markupu bloków (post_content). Idempotentne po slug.',
		'category'     => $cat,
		'input_schema' => [
			'type'       => 'object',
			'properties' => [
				'title'  => [ 'type' => 'string' ],
				'slug'   => [ 'type' => 'string' ],
				'blocks' => [ 'type' => 'string', 'description' => 'Markup bloków, np. <!-- wp:adwise/hero /-->' ],
			],
			'required'   => [ 'title', 'slug', 'blocks' ],
		],
		'output_schema'       => [
			'type'       => 'object',
			'properties' => [
				'status' => [ 'type' => 'string', 'enum' => [ 'created', 'exists', 'error' ] ],
				'id'     => [ 'type' => 'integer' ],
				'url'    => [ 'type' => 'string' ],
			],
		],
		'permission_callback' => function () { return current_user_can( 'publish_pages' ); },
		'execute_callback'    => function ( $input ) {
			$slug     = sanitize_title( $input['slug'] );
			$existing = get_posts( [
				'name'        => $slug,
				'post_type'   => 'page',
				'post_status' => [ 'publish', 'draft', 'pending', 'private' ],
				'numberposts' => 1,
			] );
			if ( $existing ) {
				return [ 'status' => 'exists', 'id' => $existing[0]->ID, 'url' => get_permalink( $existing[0]->ID ) ];
			}
			$id = wp_insert_post( [
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => sanitize_text_field( $input['title'] ),
				'post_name'    => $slug,
				// wp_slash — wp_insert_post robi wewnętrznie wp_unslash; bez tego backslashe
				// w JSON atrybutów bloków giną i psują parser (docs/wp-mcp.md §Pułapki).
				'post_content' => wp_slash( $input['blocks'] ), // markup zaufany; bloki same escapują render
			], true );
			if ( is_wp_error( $id ) ) {
				return [ 'status' => 'error', 'message' => $id->get_error_message() ];
			}
			return [ 'status' => 'created', 'id' => $id, 'url' => get_permalink( $id ) ];
		},
		'meta' => [ 'show_in_rest' => true, 'annotations' => [ 'destructiveHint' => true, 'idempotentHint' => true ] ],
	] );

	/* --- Seed wpisu CPT (lub post/page) --- */
	wp_register_ability( "{$ns}/seed-cpt", [
		'label'        => __( 'Utwórz wpis CPT', 'adwise' ),
		'description'  => 'Tworzy wpis danego typu (CPT musi istnieć i mieć show_in_rest dla edycji w edytorze).',
		'category'     => $cat,
		'input_schema' => [
			'type'       => 'object',
			'properties' => [
				'post_type' => [ 'type' => 'string' ],
				'title'     => [ 'type' => 'string' ],
				'slug'      => [ 'type' => 'string' ],
				'content'   => [ 'type' => 'string' ],
				'status'    => [ 'type' => 'string', 'enum' => [ 'publish', 'draft' ] ],
			],
			'required'   => [ 'post_type', 'title' ],
		],
		'output_schema'       => [
			'type'       => 'object',
			'properties' => [
				'status' => [ 'type' => 'string', 'enum' => [ 'created', 'error' ] ],
				'id'     => [ 'type' => 'integer' ],
				'url'    => [ 'type' => 'string' ],
			],
		],
		'permission_callback' => function () { return current_user_can( 'edit_posts' ); },
		'execute_callback'    => function ( $input ) {
			$pt  = sanitize_key( $input['post_type'] );
			$pto = get_post_type_object( $pt );
			if ( ! $pto ) {
				return [ 'status' => 'error', 'message' => "Typ '$pt' nie istnieje (zarejestruj w functions.php)." ];
			}
			// Whitelist: tylko publiczne typy widoczne w REST. Blokuje eskalację na wp_template_part,
			// wp_navigation, wp_block itd. przez rolę mającą tylko edit_posts.
			if ( ! $pto->public || ! $pto->show_in_rest ) {
				return [ 'status' => 'error', 'message' => "Typ '$pt' nie jest publiczny/REST — odmowa." ];
			}
			// Capability specyficzna dla typu, nie globalne edit_posts.
			if ( ! current_user_can( $pto->cap->create_posts ) || ! current_user_can( $pto->cap->publish_posts ) ) {
				return [ 'status' => 'error', 'message' => "Brak uprawnień do typu '$pt'." ];
			}
			$id = wp_insert_post( [
				'post_type'    => $pt,
				'post_status'  => sanitize_key( $input['status'] ?? 'publish' ),
				'post_title'   => sanitize_text_field( $input['title'] ),
				'post_name'    => isset( $input['slug'] ) ? sanitize_title( $input['slug'] ) : '',
				'post_content' => wp_slash( $input['content'] ?? '' ),
			], true );
			if ( is_wp_error( $id ) ) {
				return [ 'status' => 'error', 'message' => $id->get_error_message() ];
			}
			return [ 'status' => 'created', 'id' => $id, 'url' => get_permalink( $id ) ];
		},
		'meta' => [ 'show_in_rest' => true, 'annotations' => [ 'destructiveHint' => true, 'idempotentHint' => false ] ],
	] );

	/* --- Ustaw stronę startową (front page) --- */
	wp_register_ability( "{$ns}/set-front-page", [
		'label'        => __( 'Ustaw stronę startową', 'adwise' ),
		'description'  => 'Ustawia statyczną stronę główną (show_on_front=page, page_on_front=ID).',
		'category'     => $cat,
		'input_schema' => [
			'type'       => 'object',
			'properties' => [ 'page_id' => [ 'type' => 'integer' ] ],
			'required'   => [ 'page_id' ],
		],
		'output_schema'       => [
			'type'       => 'object',
			'properties' => [
				'status'        => [ 'type' => 'string', 'enum' => [ 'ok', 'error' ] ],
				'page_on_front' => [ 'type' => 'integer' ],
			],
		],
		'permission_callback' => function () { return current_user_can( 'manage_options' ); },
		'execute_callback'    => function ( $input ) {
			$pid = absint( $input['page_id'] );
			if ( ! get_post( $pid ) ) {
				return [ 'status' => 'error', 'message' => "Strona $pid nie istnieje." ];
			}
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', $pid );
			return [ 'status' => 'ok', 'page_on_front' => $pid ];
		},
		'meta' => [ 'show_in_rest' => true, 'annotations' => [ 'destructiveHint' => true, 'idempotentHint' => true ] ],
	] );

	/* --- Flush rewrite rules (po nowych CPT / permalinkach) --- */
	wp_register_ability( "{$ns}/flush-rewrites", [
		'label'               => __( 'Flush rewrite rules', 'adwise' ),
		'description'         => 'Przebudowuje reguły permalinków (np. po rejestracji CPT).',
		'category'            => $cat,
		'input_schema'        => [ 'type' => 'object', 'properties' => [] ],
		'output_schema'       => [
			'type'       => 'object',
			'properties' => [ 'status' => [ 'type' => 'string' ] ],
		],
		'permission_callback' => function () { return current_user_can( 'manage_options' ); },
		'execute_callback'    => function () {
			flush_rewrite_rules( false );
			return [ 'status' => 'ok' ];
		},
		'meta' => [ 'show_in_rest' => true, 'annotations' => [ 'destructiveHint' => true, 'idempotentHint' => true ] ],
	] );

	/* --- Utwórz formularz CF7 --- */
	wp_register_ability( "{$ns}/create-cf7-form", [
		'label'        => __( 'Utwórz formularz Contact Form 7', 'adwise' ),
		'description'  => 'Tworzy formularz CF7 z podanego markupu (CF7 musi być aktywny).',
		'category'     => $cat,
		'input_schema' => [
			'type'       => 'object',
			'properties' => [
				'title' => [ 'type' => 'string' ],
				'form'  => [ 'type' => 'string', 'description' => 'Markup formularza CF7 (pola [text*...] itd.)' ],
			],
			'required'   => [ 'title', 'form' ],
		],
		'output_schema'       => [
			'type'       => 'object',
			'properties' => [
				'status'    => [ 'type' => 'string', 'enum' => [ 'created', 'error' ] ],
				'id'        => [ 'type' => 'integer' ],
				'shortcode' => [ 'type' => 'string' ],
			],
		],
		'permission_callback' => function () { return current_user_can( 'manage_options' ); },
		'execute_callback'    => function ( $input ) {
			if ( ! class_exists( 'WPCF7_ContactForm' ) ) {
				return [ 'status' => 'error', 'message' => 'Contact Form 7 nieaktywny.' ];
			}
			$cf7   = WPCF7_ContactForm::get_template();
			$cf7->set_title( sanitize_text_field( $input['title'] ) );
			$props         = $cf7->get_properties();
			$props['form'] = wp_slash( $input['form'] );
			$cf7->set_properties( $props );
			$id = $cf7->save();
			if ( ! $id ) {
				return [ 'status' => 'error', 'message' => 'Zapis formularza CF7 nie powiódł się.' ];
			}
			// CF7 5.8+ preferuje hash zamiast numerycznego id (id= jest deprecated).
			$ref = method_exists( $cf7, 'hash' ) ? $cf7->hash() : $id;
			return [ 'status' => 'created', 'id' => $id, 'shortcode' => sprintf( '[contact-form-7 id="%s" title="%s"]', esc_attr( $ref ), esc_attr( $cf7->title() ) ) ];
		},
		'meta' => [ 'show_in_rest' => true, 'annotations' => [ 'destructiveHint' => true, 'idempotentHint' => false ] ],
	] );

} );
