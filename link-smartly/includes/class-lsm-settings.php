<?php
/**
 * Plugin settings manager.
 *
 * @package LinkSmartly
 * @since   1.0.0
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages plugin settings stored in wp_options.
 *
 * @since 1.0.0
 */
class Lsm_Settings {

	/**
	 * Option name in wp_options table.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const OPTION_NAME = 'lsm_settings';

	/**
	 * Default settings values.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed> Default settings.
	 */
	public static function defaults(): array {
		return array(
			'enabled'            => true,
			'max_links_per_post' => 3,
			'post_types'         => array( 'post', 'page' ),
			'min_content_words'  => 300,
			'link_class'         => 'lsm-auto-link',
			'add_title_attr'     => true,
			'nofollow'           => false,
			'new_tab'            => false,
			'cron_health_check'  => true,
			'email_digest'       => false,
		);
	}

	/**
	 * Get all settings merged with defaults.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed> Plugin settings.
	 */
	public static function get_all(): array {
		$saved = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		return wp_parse_args( $saved, self::defaults() );
	}

	/**
	 * Get a single setting value.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value if key not found.
	 * @return mixed Setting value.
	 */
	public static function get( string $key, mixed $default = null ): mixed {
		$settings = self::get_all();

		return $settings[ $key ] ?? $default;
	}

	/**
	 * Save settings to the database.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $settings Settings to save.
	 * @return bool True if updated, false otherwise.
	 */
	public static function save( array $settings ): bool {
		$clean = self::sanitize( $settings );

		$result = update_option( self::OPTION_NAME, $clean );

		/**
		 * Fires after settings are saved.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, mixed> $clean Sanitized settings that were saved.
		 */
		do_action( 'lsm_settings_saved', $clean );

		return $result;
	}

	/**
	 * Sanitize settings values.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $input Raw settings input.
	 * @return array<string, mixed> Sanitized settings.
	 */
	public static function sanitize( array $input ): array {
		$defaults = self::defaults();

		$clean = array();

		$clean['enabled']            = ! empty( $input['enabled'] );
		$clean['max_links_per_post'] = isset( $input['max_links_per_post'] )
			? absint( $input['max_links_per_post'] )
			: $defaults['max_links_per_post'];
		$clean['min_content_words']  = isset( $input['min_content_words'] )
			? absint( $input['min_content_words'] )
			: $defaults['min_content_words'];
		$clean['link_class']         = isset( $input['link_class'] )
			? sanitize_html_class( $input['link_class'] )
			: $defaults['link_class'];
		$clean['add_title_attr']     = ! empty( $input['add_title_attr'] );
		$clean['nofollow']           = ! empty( $input['nofollow'] );
		$clean['new_tab']            = ! empty( $input['new_tab'] );
		$clean['cron_health_check']  = ! empty( $input['cron_health_check'] );
		$clean['email_digest']       = ! empty( $input['email_digest'] );

		$clean['post_types'] = isset( $input['post_types'] ) && is_array( $input['post_types'] )
			? array_map( 'sanitize_key', $input['post_types'] )
			: $defaults['post_types'];

		if ( $clean['max_links_per_post'] < 1 ) {
			$clean['max_links_per_post'] = 1;
		}

		if ( $clean['max_links_per_post'] > 50 ) {
			$clean['max_links_per_post'] = 50;
		}

		return $clean;
	}

	/**
	 * Delete all plugin settings from the database.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if deleted, false otherwise.
	 */
	public static function delete(): bool {
		return delete_option( self::OPTION_NAME );
	}
}
