<?php
/**
 * Plugin Name: Lokalna poczta → Mailpit
 * Description: Tylko lokalnie (Docker). Każdy mail z WordPressa trafia do Mailpita (http://localhost:8131), nic nie wychodzi na zewnątrz.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'phpmailer_init', function ( $mailer ) {
	$mailer->isSMTP();
	$mailer->Host        = 'mailpit';
	$mailer->Port        = 1025;
	$mailer->SMTPAuth    = false;
	$mailer->SMTPAutoTLS = false;
} );

/* PHPMailer odrzuca nadawcę „wordpress@localhost" (domena bez TLD) — lokalnie dajemy poprawny adres. */
add_filter( 'wp_mail_from', function ( $from ) {
	return ( false === strpos( $from, '.' ) ) ? 'wordpress@localhost.test' : $from;
} );
add_filter( 'wp_mail_from_name', function ( $name ) { return $name ?: 'MIRO Pulmonology (lokalnie)'; } );
