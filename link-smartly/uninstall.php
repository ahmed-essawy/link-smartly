<?php
/**
 * Uninstall handler — removes all plugin data from the database.
 *
 * Fired when the plugin is uninstalled via the WordPress admin.
 *
 * @package LinkSmartly
 * @since   1.0.0
 */

declare( strict_types = 1 );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'lsm_settings' );
delete_option( 'lsm_keywords' );
delete_option( 'lsm_linked_posts' );
delete_option( 'lsm_cache_versions' );

if ( function_exists( 'wp_cache_flush_group' ) ) {
	wp_cache_flush_group( 'lsm' );
}

// Unschedule cron events.
$cron_hooks = array( 'lsm_weekly_health_check', 'lsm_email_digest' );
foreach ( $cron_hooks as $hook ) {
	$timestamp = wp_next_scheduled( $hook );
	if ( false !== $timestamp ) {
		wp_unschedule_event( $timestamp, $hook );
	}
}

// Clean up all transients (known keys + user-scoped).
delete_transient( 'lsm_active_keywords' );
delete_transient( 'lsm_url_health' );
delete_transient( 'lsm_content_cache_hash' );
delete_transient( 'lsm_suggestions' );

// Preview, undo, and content cache transients are user/post-scoped; clean up for all users.
$users = get_users( array( 'fields' => 'ID' ) );
foreach ( $users as $user_id ) {
	delete_transient( 'lsm_preview_results_' . $user_id );
	delete_transient( 'lsm_undo_' . $user_id );
}

// Remove post meta for excluded posts — direct query is intentional during uninstall (one-time cleanup, no caching needed).
global $wpdb;
$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_lsm_exclude' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.slow_db_query_meta_key

// Remove all plugin transients, including versioned content-cache entries.
$transient_prefix         = $wpdb->esc_like( '_transient_lsm_' ) . '%';
$transient_timeout_prefix = $wpdb->esc_like( '_transient_timeout_lsm_' ) . '%';

$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $transient_prefix, $transient_timeout_prefix ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
