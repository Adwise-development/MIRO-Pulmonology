<?php
/**
 * Adwise SVG sanitizer — rozszerza enshrined/svg-sanitize.
 *
 * Biblioteczne `removeRemoteReferences()` ścina referencje TYLKO w składni `url(...)`
 * (fill/mask/filter). Gołe zewnętrzne `href`/`xlink:href` (np. `<image href="http://…">`,
 * protocol-relative `//host`) przechodzą — to phone-home / tracking vector. Ta subklasa
 * domyka lukę: dodatkowo traktuje jako remote każdą wartość zaczynającą się od
 * `http(s):`, `ftp:` lub `//`. Wewnętrzne (`#id`), względne ścieżki i `data:` zostają.
 *
 * @package adwise
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( \enshrined\svgSanitize\Sanitizer::class ) && ! class_exists( 'Adwise_Svg_Sanitizer', false ) ) {

	class Adwise_Svg_Sanitizer extends \enshrined\svgSanitize\Sanitizer {

		/**
		 * @param string $value
		 * @return bool
		 */
		protected function hasRemoteReference( $value ) {
			if ( parent::hasRemoteReference( $value ) ) {
				return true;
			}
			// Gołe zewnętrzne URL-e: //host, http(s)://, ftp:// — biblioteka łapie tylko url(...).
			return (bool) preg_match( '~^\s*(?:https?:|ftp:)?//~i', (string) $value );
		}
	}
}
