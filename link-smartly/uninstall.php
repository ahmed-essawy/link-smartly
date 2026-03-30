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

delete_transient( 'lsm_active_keywords' );

// Preview and undo transients are user-scoped; clean up for all users.
$users = get_users( array( 'fields' => 'ID' ) );
foreach ( $users as $user_id ) {
	delete_transient( 'lsm_preview_results_' . $user_id );
	delete_transient( 'lsm_undo_' . $user_id );
}

// Remove post meta for excluded posts — direct query is intentional during uninstall (one-time cleanup, no caching needed).
global $wpdb;
$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_lsm_exclude' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.slow_db_query_meta_key
