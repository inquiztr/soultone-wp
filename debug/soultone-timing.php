<?php
/**
 * Plugin Name: Soultone Request Timing
 * Description: Logs slow request breakdown to /tmp/soultone-timing.log. Drop into mu-plugins/, remove when done.
 *
 * HOW TO INSTALL:
 *   On the server, copy into the mu-plugins folder:
 *     sudo mkdir -p /var/lib/kamal/soultone/wp_data/mu-plugins
 *     sudo cp soultone-timing.php /var/lib/kamal/soultone/wp_data/mu-plugins/
 *
 * HOW TO READ LOGS:
 *   docker exec <wp-container> cat /tmp/soultone-timing.log
 *   or: docker exec <wp-container> tail -50 /tmp/soultone-timing.log
 *
 * HOW TO REMOVE:
 *   sudo rm /var/lib/kamal/soultone/wp_data/mu-plugins/soultone-timing.php
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Only log page loads, not AJAX/REST/cron
$start = microtime( true );

// Mark key WordPress lifecycle points
add_action( 'plugins_loaded', function() use ( $start ) {
    $GLOBALS['_soultone_plugins_loaded'] = microtime( true ) - $start;
}, 0 );

add_action( 'init', function() use ( $start ) {
    $GLOBALS['_soultone_init'] = microtime( true ) - $start;
}, 0 );

add_action( 'wp', function() use ( $start ) {
    $GLOBALS['_soultone_wp'] = microtime( true ) - $start;
}, 0 );

add_action( 'template_redirect', function() use ( $start ) {
    $GLOBALS['_soultone_template'] = microtime( true ) - $start;
}, 0 );

// Log on shutdown (after full page render)
add_action( 'shutdown', function() use ( $start ) {
    $total = microtime( true ) - $start;

    // Only log slow requests (> 3 seconds) to keep file small
    if ( $total < 3.0 ) return;

    $plugins  = isset( $GLOBALS['_soultone_plugins_loaded'] ) ? round( $GLOBALS['_soultone_plugins_loaded'], 3 ) : '?';
    $init     = isset( $GLOBALS['_soultone_init'] )           ? round( $GLOBALS['_soultone_init'], 3 )           : '?';
    $wp       = isset( $GLOBALS['_soultone_wp'] )             ? round( $GLOBALS['_soultone_wp'], 3 )             : '?';
    $template = isset( $GLOBALS['_soultone_template'] )       ? round( $GLOBALS['_soultone_template'], 3 )       : '?';
    $total_r  = round( $total, 3 );

    $uri  = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : 'cli';
    $time = gmdate( 'Y-m-d H:i:s' );
    $db_q = defined( 'SAVEQUERIES' ) && SAVEQUERIES && isset( $GLOBALS['wpdb'] )
        ? count( $GLOBALS['wpdb']->queries )
        : 'n/a';

    $line = "[{$time}] {$uri} | total={$total_r}s | plugins_loaded={$plugins}s | init={$init}s | wp={$wp}s | template_redirect={$template}s | db_queries={$db_q}\n";

    @file_put_contents( '/tmp/soultone-timing.log', $line, FILE_APPEND | LOCK_EX );
}, 9999 );
