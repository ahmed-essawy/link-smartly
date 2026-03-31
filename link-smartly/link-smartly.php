<?php
/**
 * Plugin Name:       Link Smartly
 * Plugin URI:        https://github.com/ahmed-essawy/link-smartly
 * Description:       Automatically insert internal links into your content based on keyword-to-URL mappings. Lightweight, cache-friendly, and SEO-focused.
 * Version:           1.3.0
 * Author:            Ahmed Essawy
 * Author URI:        https://minicad.io
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       link-smartly
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Tested up to:      6.9
 * Requires PHP:      8.0
 *
 * @package LinkSmartly
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin version constant.
 *
 * @since 1.0.0
 * @var string
 */
define( 'LSM_VERSION', '1.3.0' );

/**
 * Plugin file path constant.
 *
 * @since 1.0.0
 * @var string
 */
define( 'LSM_PLUGIN_FILE', __FILE__ );

/**
 * Plugin directory path constant.
 *
 * @since 1.0.0
 * @var string
 */
define( 'LSM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Plugin directory URL constant.
 *
 * @since 1.0.0
 * @var string
 */
define( 'LSM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Plugin basename constant.
 *
 * @since 1.0.0
 * @var string
 */
define( 'LSM_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once LSM_PLUGIN_DIR . 'includes/class-lsm-activator.php';
require_once LSM_PLUGIN_DIR . 'includes/class-lsm-cache.php';
require_once LSM_PLUGIN_DIR . 'includes/class-lsm-keywords.php';
require_once LSM_PLUGIN_DIR . 'includes/class-lsm-settings.php';
require_once LSM_PLUGIN_DIR . 'includes/class-lsm-linker.php';
require_once LSM_PLUGIN_DIR . 'includes/class-lsm-meta-box.php';
require_once LSM_PLUGIN_DIR . 'includes/class-lsm-cli.php';
require_once LSM_PLUGIN_DIR . 'includes/class-lsm-rest.php';
require_once LSM_PLUGIN_DIR . 'includes/class-lsm-health.php';
require_once LSM_PLUGIN_DIR . 'includes/class-lsm-suggestions.php';
require_once LSM_PLUGIN_DIR . 'includes/class-lsm-notifications.php';
require_once LSM_PLUGIN_DIR . 'admin/class-lsm-admin-handlers.php';
require_once LSM_PLUGIN_DIR . 'admin/class-lsm-admin-renderer.php';
require_once LSM_PLUGIN_DIR . 'admin/class-lsm-admin.php';
require_once LSM_PLUGIN_DIR . 'admin/class-lsm-csv.php';
require_once LSM_PLUGIN_DIR . 'admin/class-lsm-preview.php';
require_once LSM_PLUGIN_DIR . 'admin/class-lsm-ajax.php';
require_once LSM_PLUGIN_DIR . 'admin/class-lsm-dashboard.php';

register_activation_hook( __FILE__, array( 'Lsm_Activator', 'activate' ) );
register_activation_hook( __FILE__, array( 'Lsm_Health', 'schedule_cron' ) );
register_activation_hook( __FILE__, array( 'Lsm_Notifications', 'schedule_cron' ) );
register_deactivation_hook( __FILE__, array( 'Lsm_Health', 'unschedule_cron' ) );
register_deactivation_hook( __FILE__, array( 'Lsm_Notifications', 'unschedule_cron' ) );

add_action( Lsm_Health::CRON_HOOK, array( 'Lsm_Health', 'cron_check' ) );
add_action( Lsm_Notifications::CRON_HOOK, array( 'Lsm_Notifications', 'send_digest' ) );

/**
 * Flush the content processing cache when keywords or settings change.
 *
 * @since 1.3.0
 *
 * @return void
 */
function lsm_flush_content_cache(): void {
	Lsm_Cache::flush();
}
add_action( 'lsm_keywords_saved', 'lsm_flush_content_cache' );
add_action( 'lsm_settings_saved', 'lsm_flush_content_cache' );

/**
 * Initialize the plugin.
 *
 * @since 1.0.0
 *
 * @return void
 */
function lsm_init(): void {
	// Translations are loaded automatically by WordPress 4.6+ for plugins hosted on WP.org.
}
add_action( 'init', 'lsm_init' );

/**
 * Boot the front-end linker on template_redirect.
 *
 * @since 1.0.0
 *
 * @return void
 */
function lsm_boot_linker(): void {
	if ( is_admin() ) {
		return;
	}

	$settings = Lsm_Settings::get_all();

	if ( empty( $settings['enabled'] ) ) {
		return;
	}

	$linker = new Lsm_Linker( new Lsm_Keywords(), $settings );

	/**
	 * Filters the priority of the_content filter for auto-linking.
	 *
	 * @since 1.0.0
	 *
	 * @param int $priority Filter priority. Default 999.
	 */
	$priority = (int) apply_filters( 'lsm_content_filter_priority', 999 );

	add_filter( 'the_content', array( $linker, 'process_content' ), $priority );
}
add_action( 'template_redirect', 'lsm_boot_linker' );

/**
 * Boot the admin interface.
 *
 * @since 1.0.0
 *
 * @return void
 */
function lsm_boot_admin(): void {
	if ( ! is_admin() ) {
		return;
	}

	$admin = new Lsm_Admin( new Lsm_Keywords() );
	$admin->init();

	$csv = new Lsm_Csv( new Lsm_Keywords() );
	$csv->init();

	$preview = new Lsm_Preview( new Lsm_Keywords() );
	$preview->init();

	$dashboard = new Lsm_Dashboard( new Lsm_Keywords() );
	$dashboard->init();
}
add_action( 'plugins_loaded', 'lsm_boot_admin' );

/**
 * Boot the meta box for post-level exclusion.
 *
 * @since 1.1.0
 *
 * @return void
 */
function lsm_boot_meta_box(): void {
	if ( ! is_admin() ) {
		return;
	}

	$meta_box = new Lsm_Meta_Box();
	$meta_box->init();
}
add_action( 'plugins_loaded', 'lsm_boot_meta_box' );

/**
 * Boot the REST API endpoints.
 *
 * @since 1.1.0
 *
 * @return void
 */
function lsm_boot_rest(): void {
	$rest = new Lsm_Rest( new Lsm_Keywords() );
	$rest->init();
}
add_action( 'plugins_loaded', 'lsm_boot_rest' );

/**
 * Boot the AJAX handlers for keyword management.
 *
 * @since 1.2.0
 *
 * @return void
 */
function lsm_boot_ajax(): void {
	if ( ! is_admin() ) {
		return;
	}

	$ajax = new Lsm_Ajax( new Lsm_Keywords() );
	$ajax->init();
}
add_action( 'plugins_loaded', 'lsm_boot_ajax' );

/**
 * Register WP-CLI commands if running in CLI mode.
 *
 * @since 1.1.0
 *
 * @return void
 */
function lsm_boot_cli(): void {
	if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
		return;
	}

	WP_CLI::add_command( 'link-smartly', new Lsm_Cli( new Lsm_Keywords() ) );
}
add_action( 'plugins_loaded', 'lsm_boot_cli' );
