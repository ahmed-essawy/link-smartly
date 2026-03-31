<?php
/**
 * Object cache layer with transient fallback.
 *
 * Uses wp_cache_* functions when a persistent object cache (Redis, Memcached)
 * is available, otherwise falls back to WordPress transients.
 *
 * @package LinkSmartly
 * @since   1.3.0
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Unified caching interface for all plugin data.
 *
 * @since 1.3.0
 */
class Lsm_Cache {

	/**
	 * Cache group for wp_cache_* functions.
	 *
	 * @since 1.3.0
	 * @var string
	 */
	const GROUP = 'lsm';

	/**
	 * Whether a persistent external object cache is active.
	 *
	 * @since 1.3.0
	 * @var bool
	 */
	private static bool $use_object_cache;

	/**
	 * Check if a persistent external object cache is available.
	 *
	 * @since 1.3.0
	 *
	 * @return bool True if persistent object cache is active.
	 */
	private static function has_object_cache(): bool {
		if ( ! isset( self::$use_object_cache ) ) {
			self::$use_object_cache = wp_using_ext_object_cache();
		}

		return self::$use_object_cache;
	}

	/**
	 * Build a prefixed transient key.
	 *
	 * WordPress transient keys are limited to 172 characters. This method
	 * ensures the key stays within limits by using a hash for long keys.
	 *
	 * @since 1.3.0
	 *
	 * @param string $key Cache key.
	 * @return string Prefixed transient key.
	 */
	private static function transient_key( string $key ): string {
		$prefixed = 'lsm_' . $key;

		if ( strlen( $prefixed ) > 172 ) {
			$prefixed = 'lsm_' . md5( $key );
		}

		return $prefixed;
	}

	/**
	 * Retrieve a value from the cache.
	 *
	 * @since 1.3.0
	 *
	 * @param string $key Cache key.
	 * @return mixed Cached value or false if not found.
	 */
	public static function get( string $key ) {
		if ( self::has_object_cache() ) {
			$found = false;
			$value = wp_cache_get( $key, self::GROUP, false, $found );

			return $found ? $value : false;
		}

		return get_transient( self::transient_key( $key ) );
	}

	/**
	 * Store a value in the cache.
	 *
	 * @since 1.3.0
	 *
	 * @param string $key    Cache key.
	 * @param mixed  $data   Data to cache.
	 * @param int    $expiry Expiration in seconds. Default 0 (no expiry for object cache, or transient default).
	 * @return bool True on success, false on failure.
	 */
	public static function set( string $key, $data, int $expiry = 0 ): bool {
		if ( self::has_object_cache() ) {
			return wp_cache_set( $key, $data, self::GROUP, $expiry );
		}

		return set_transient( self::transient_key( $key ), $data, $expiry );
	}

	/**
	 * Delete a value from the cache.
	 *
	 * @since 1.3.0
	 *
	 * @param string $key Cache key.
	 * @return bool True on success, false on failure.
	 */
	public static function delete( string $key ): bool {
		if ( self::has_object_cache() ) {
			return wp_cache_delete( $key, self::GROUP );
		}

		return delete_transient( self::transient_key( $key ) );
	}

	/**
	 * Flush all plugin cache entries.
	 *
	 * When using object cache, flushes the entire group. When using transients,
	 * deletes all known plugin transient keys.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	public static function flush(): void {
		if ( self::has_object_cache() ) {
			wp_cache_flush_group( self::GROUP );
			return;
		}

		// Transient fallback: delete all known keys.
		$known_keys = array(
			'active_keywords',
			'url_health',
		);

		foreach ( $known_keys as $key ) {
			delete_transient( self::transient_key( $key ) );
		}

		// User-scoped transients.
		$users = get_users( array( 'fields' => 'ID' ) );
		foreach ( $users as $user_id ) {
			delete_transient( 'lsm_preview_results_' . $user_id );
			delete_transient( 'lsm_undo_' . $user_id );
		}
	}

	/**
	 * Reset the internal object cache detection flag.
	 *
	 * Useful for testing or when the cache backend changes at runtime.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	public static function reset(): void {
		unset( self::$use_object_cache );
	}
}
