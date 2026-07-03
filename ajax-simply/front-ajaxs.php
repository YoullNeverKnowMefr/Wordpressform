<?php
/**
 * WordPress AJAX handler for the front-end
 */

define( 'DOING_AJAX', true );

// try to find `wp-load.php`
$root = $_SERVER['DOCUMENT_ROOT'];
                                     $wp_load_path = "$root/wp-load.php";
if( ! file_exists( $wp_load_path ) ) $wp_load_path = "$root/core/wp-load.php";
if( ! file_exists( $wp_load_path ) ) $wp_load_path = "$root/wp/wp-load.php";

require_once $wp_load_path;

send_origin_headers();

if ( empty( $_REQUEST['action'] ) )
	wp_die( '0', 400 );

@header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );
@header( 'X-Robots-Tag: noindex' );

send_nosniff_header();
nocache_headers();

if ( is_user_logged_in() )
	$action = 'wp_ajax_' . $_REQUEST['action'];
else
	$action = 'wp_ajax_nopriv_' . $_REQUEST['action'];

if ( ! has_action( $action ) )
	wp_die( '0', 400 );

do_action( $action );

die( '0' );
