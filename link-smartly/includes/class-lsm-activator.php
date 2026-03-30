<?php
/**
 * Plugin activation handler.
 *
 * @package LinkSmartly
 * @since   1.0.0
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles plugin activation tasks including default settings and sample data.
 *
 * @since 1.0.0
 */
class Lsm_Activator {

	/**
	 * Run activation routines.
	 *
	 * Sets default settings and loads sample keyword data if none exist.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function activate(): void {
		self::set_default_settings();
		self::load_sample_keywords();

		/**
		 * Fires after the plugin is activated.
		 *
		 * @since 1.0.0
		 */
		do_action( 'lsm_activated' );
	}

	/**
	 * Set default settings if they do not already exist.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private static function set_default_settings(): void {
		if ( false !== get_option( Lsm_Settings::OPTION_NAME ) ) {
			return;
		}

		add_option( Lsm_Settings::OPTION_NAME, Lsm_Settings::defaults() );
	}

	/**
	 * Load sample keyword-to-URL mappings if none exist.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private static function load_sample_keywords(): void {
		if ( false !== get_option( Lsm_Keywords::OPTION_NAME ) ) {
			return;
		}

		$samples = self::get_sample_keywords();

		$keywords = new Lsm_Keywords();
		$entries  = array();

		foreach ( $samples as $sample ) {
			$entries[] = $keywords->sanitize_entry(
				array(
					'id'      => wp_generate_uuid4(),
					'keyword' => $sample['keyword'],
					'url'     => $sample['url'],
					'active'  => true,
					'group'   => $sample['group'] ?? '',
				)
			);
		}

		add_option( Lsm_Keywords::OPTION_NAME, $entries );
	}

	/**
	 * Get the sample keyword-to-URL mappings.
	 *
	 * These serve as example data. Users should replace them with their own keywords.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array{keyword: string, url: string}> Sample mappings.
	 */
	private static function get_sample_keywords(): array {
		/**
		 * Filters the sample keyword data loaded on activation.
		 *
		 * @since 1.0.0
		 *
		 * @param array<int, array{keyword: string, url: string}> $samples Sample keyword mappings.
		 */
		return (array) apply_filters(
			'lsm_sample_keywords',
			array(
				array(
					'keyword' => 'contact us',
					'url'     => '/contact/',
					'group'   => 'Navigation',
				),
				array(
					'keyword' => 'our services',
					'url'     => '/services/',
					'group'   => 'Navigation',
				),
				array(
					'keyword' => 'about us',
					'url'     => '/about/',
					'group'   => 'Navigation',
				),
				array(
					'keyword' => 'pricing plans',
					'url'     => '/pricing/',
					'group'   => 'Conversion',
				),
				array(
					'keyword' => 'get started',
					'url'     => '/get-started/',
					'group'   => 'Conversion',
				),
			)
		);
	}
}
