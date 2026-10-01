<?php
/**
 * Custom Login KV — split-screen branded login (purple panel + floating squares parallax + restyled form).
 *
 * Ładowany z functions.php (require_once). CSS/JS wydzielone: assets/css/login.css + assets/js/login.js
 * (enqueue przez login_enqueue_scripts z filemtime — cache-bust + CSP-friendly, bez inline).
 * Rebrand: filtr `adwise_login_config` (teksty/logo/link) + tokeny w login.css. NIE aktywować jako plugin.
 *
 * @package adwise
 */

defined( 'ABSPATH' ) || exit;

/* === Brand assets === */

if ( ! function_exists( 'adwise_login_logo_svg' ) ) {
	/**
	 * Logo marki (inline SVG, zaufane hardcoded źródło).
	 *
	 * @return string Markup SVG.
	 */
	function adwise_login_logo_svg() {
		return '<svg xmlns="http://www.w3.org/2000/svg" width="196" height="26" viewBox="0 0 196 26" fill="none" aria-label="AdWise"><g clip-path="url(#adw-l)"><path d="M68.025 3.276H65.078L55.066 22.781H57.08L59.55 17.763H73.55L76.022 22.781H78.036L68.025 3.276zM67.21 4.861 72.77 16.16H60.328L65.897 4.861H67.208z" fill="#fff"/><path d="M95.828 3.247V12.245C94.315 9.799 91.507 8.457 87.867 8.457C82.339 8.457 78.766 11.369 78.766 15.878C78.766 20.387 82.401 23.298 87.812 23.298C91.497 23.298 94.323 21.985 95.828 19.585V22.781H97.446V3.247H95.828zM95.856 15.741V16.121C95.856 19.397 92.611 21.775 88.138 21.775C83.665 21.775 80.557 19.516 80.557 15.875C80.557 12.235 83.332 9.975 87.975 9.975C92.619 9.975 95.856 12.4 95.856 15.738V15.741z" fill="#fff"/><path d="M48.62 1.629H29.164V17.884H48.62V1.629z" fill="#7855DA"/><path d="M182.804 15.459C182.804 11.999 180.156 9.254 176.058 8.462C174.173 8.098 171.921 8.098 170.037 8.462C165.939 9.254 163.291 11.999 163.291 15.459C163.291 18.921 165.939 21.667 170.037 22.455C170.98 22.636 172.012 22.727 173.046 22.727C174.08 22.727 175.115 22.636 176.056 22.455C179.127 21.863 181.382 20.172 182.32 17.905H178.207C177.214 18.745 176.025 19.086 175.448 19.198C173.964 19.485 172.123 19.485 170.639 19.198C169.07 18.895 167.542 18.086 166.924 16.682H182.682C182.757 16.286 182.799 15.878 182.799 15.459H182.804zM167.172 13.752C168.098 12.356 169.923 11.86 170.644 11.72C172.128 11.433 173.969 11.433 175.453 11.72C176.834 11.986 178.114 12.643 178.864 13.752H167.172z" fill="#fff"/><path d="M125.055 20.679L117.849 3.317L117.813 3.235H112.846L112.81 3.317L105.604 20.679V3.235H100.668V22.747H104.743H105.604H109.782L115.328 9.383L120.876 22.747H125.055H125.872H125.913H129.99V3.235H125.055V20.679z" fill="#fff"/><path d="M157.978 14.476C156.863 14.163 155.307 14.008 152.975 13.84C152.76 13.825 146.981 13.494 146.379 13.305C145.927 13.163 145.559 12.935 145.559 12.558C145.559 12.449 145.588 12.356 145.637 12.271C145.671 12.214 145.712 12.162 145.761 12.113C146.064 11.826 146.653 11.699 147.165 11.645C147.209 11.637 147.258 11.635 147.305 11.632C147.605 11.606 152.871 11.609 153.22 11.632C153.761 11.671 154.415 11.764 154.968 12.007C155.485 12.232 156.051 12.597 156.07 13.147H160.4C160.341 9.091 155.26 8.165 151.03 8.165C146.588 8.165 141.479 8.687 141.397 12.77C141.358 14.657 142.325 15.891 144.272 16.436C145.397 16.752 146.969 16.907 149.337 17.077C149.337 17.077 155.268 17.421 155.87 17.607C156.323 17.75 156.69 17.977 156.69 18.355C156.69 18.461 156.662 18.556 156.613 18.642C156.579 18.699 156.538 18.75 156.488 18.797C156.186 19.084 155.596 19.21 155.084 19.265C155.041 19.27 154.991 19.275 154.945 19.278C154.642 19.304 149.06 19.298 148.447 19.231C147.848 19.166 147.186 19.035 146.648 18.773C146.183 18.549 145.686 18.236 145.676 17.757H141.34C141.371 19.627 142.764 22.74 151.217 22.74C155.658 22.74 160.767 22.217 160.85 18.137C160.889 16.25 159.922 15.017 157.975 14.471L157.978 14.476z" fill="#fff"/><path d="M138.135 8.925H133.199V22.747H138.135V8.925z" fill="#fff"/><path d="M138.135 3.235H133.199V6.521H138.135V3.235z" fill="#fff"/><path d="M29.163 17.884H17.813V26H29.163V17.884z" fill="#7855DA"/><path d="M17.809 11.351H8.082V17.884H17.809V11.351z" fill="#7855DA"/><path d="M8.082 17.884H0V21.137H8.082V17.884z" fill="#7855DA"/><path d="M189.312 0C185.809 0 183.58 2.689 183.58 5.779C183.58 8.868 185.809 11.573 189.312 11.573C192.816 11.573 195.029 8.884 195.029 5.779C195.029 2.673 192.785 0 189.312 0zM189.315 10.531C186.535 10.531 184.684 8.292 184.684 5.776C184.684 3.26 186.535 1.039 189.315 1.039C192.094 1.039 193.93 3.258 193.93 5.776C193.93 8.294 192.079 10.531 189.315 10.531z" fill="#fff"/><path d="M190.436 6.549C191.111 6.205 191.488 5.574 191.488 4.806C191.488 3.659 190.625 2.779 189.226 2.779H187.328V8.687H188.569V6.818H189.322L190.281 8.656H191.68L190.438 6.552zM189.303 5.574L188.567 5.59V4.02L189.303 4.036C189.805 4.036 190.167 4.333 190.167 4.806C190.167 5.28 189.805 5.577 189.303 5.577V5.574z" fill="#fff"/></g><defs><clipPath id="adw-l"><rect width="195.028" height="26" fill="#fff"/></clipPath></defs></svg>';
	}
}

/**
 * Konfiguracja login KV — nadpisywalna filtrem (rebrand bez edycji PHP).
 *
 * @return array<string,string> logo_svg, brand_url, tagline (HTML), heading, subheading, footer_name.
 */
function adwise_login_config() {
	return apply_filters( 'adwise_login_config', [
		'logo_svg'    => adwise_login_logo_svg(),
		'brand_url'   => home_url( '/' ),
		'tagline'     => sprintf(
			'%s<br><strong>%s</strong>',
			esc_html__( 'Procesowo wdrażamy', 'adwise' ),
			esc_html__( 'marketing B2B', 'adwise' )
		),
		'heading'     => __( 'Zaloguj się', 'adwise' ),
		'subheading'  => __( 'Wprowadź swoje dane, aby przejść do panelu.', 'adwise' ),
		'footer_name' => 'AdWise',
	] );
}

/* === CSS + JS (wydzielone, enqueue z filemtime) === */

add_action( 'login_enqueue_scripts', function () {
	$dir = get_template_directory();
	$uri = get_template_directory_uri();
	$css = '/assets/css/login.css';
	$js  = '/assets/js/login.js';
	wp_enqueue_style( 'adwise-login', $uri . $css, [], file_exists( $dir . $css ) ? filemtime( $dir . $css ) : false );
	wp_enqueue_script( 'adwise-login', $uri . $js, [], file_exists( $dir . $js ) ? filemtime( $dir . $js ) : false, true );
} );

/* === Brand panel HTML in body === */

// aside z sensowną treścią (logo, tagline) = complementary landmark → BEZ aria-hidden
// (aria-hidden na kontenerze z focusowalnymi linkami łamie WCAG 4.1.2).
add_action( 'login_header', function () {
	$c = adwise_login_config();
	?>
	<aside class="adw-brand">
		<div class="adw-brand__squares" id="adw-squares"></div>
		<div class="adw-brand__logo"><a href="<?php echo esc_url( $c['brand_url'] ); ?>" aria-label="<?php echo esc_attr( $c['footer_name'] ); ?>"><?php
			echo $c['logo_svg']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- zaufany hardcoded SVG (wp_kses_post wycina <svg>/<path>)
		?></a></div>
		<div class="adw-brand__tagline"><?php echo wp_kses_post( $c['tagline'] ); ?></div>
		<div class="adw-brand__footer">© <?php echo esc_html( wp_date( 'Y' ) ); ?> <a href="<?php echo esc_url( $c['brand_url'] ); ?>"><?php echo esc_html( $c['footer_name'] ); ?></a></div>
	</aside>
	<?php
} );

/* === Form heading — TYLKO na ekranie logowania === */

// login_message odpala też na lostpassword/register/resetpass/checkemail — bez guardu
// user na „Nie pamiętasz hasła?" widziałby nagłówek „Zaloguj się".
add_filter( 'login_message', function ( $msg ) {
	$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : 'login';
	if ( ! in_array( $action, [ 'login', '' ], true ) ) {
		return $msg;
	}
	$c  = adwise_login_config();
	$h  = '<h1 class="adw-login-heading">' . esc_html( $c['heading'] ) . '</h1>';
	$h .= '<p class="adw-login-sub">' . esc_html( $c['subheading'] ) . '</p>';
	return $h . $msg;
} );
