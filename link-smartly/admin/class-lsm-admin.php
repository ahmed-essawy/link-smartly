<?php
/**
 * Admin page orchestrator — menu, assets, and plugin metadata.
 *
 * @package LinkSmartly
 * @since   1.0.0
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the admin menu page, enqueues assets, and coordinates
 * the handler and renderer sub-classes.
 *
 * @since 1.0.0
 */
class Lsm_Admin {

	/**
	 * Admin page hook suffix.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private string $hook_suffix = '';

	/**
	 * Keywords manager instance.
	 *
	 * @since 1.0.0
	 * @var Lsm_Keywords
	 */
	private Lsm_Keywords $keywords;

	/**
	 * Form handlers instance.
	 *
	 * @since 1.3.0
	 * @var Lsm_Admin_Handlers
	 */
	private Lsm_Admin_Handlers $handlers;

	/**
	 * Tab renderer instance.
	 *
	 * @since 1.3.0
	 * @var Lsm_Admin_Renderer
	 */
	private Lsm_Admin_Renderer $renderer;

	/**
	 * The admin page slug.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const PAGE_SLUG = 'link-smartly';

	/**
	 * Nonce action for settings form.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const NONCE_SETTINGS = 'lsm_save_settings';

	/**
	 * Nonce action for keyword operations.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const NONCE_KEYWORD = 'lsm_keyword_action';

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param Lsm_Keywords $keywords Keywords manager.
	 */
	public function __construct( Lsm_Keywords $keywords ) {
		$this->keywords = $keywords;
		$this->handlers = new Lsm_Admin_Handlers( $keywords );
		$this->renderer = new Lsm_Admin_Renderer( $keywords );
	}

	/**
	 * Initialize admin hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'plugin_action_links_' . LSM_PLUGIN_BASENAME, array( $this, 'add_settings_link' ) );
		add_filter( 'plugin_row_meta', array( $this, 'add_row_meta' ), 10, 2 );
		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );

		$this->handlers->init();
	}

	/**
	 * Register the admin menu page.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_menu(): void {
		$this->hook_suffix = add_options_page(
			esc_html__( 'Link Smartly', 'link-smartly' ),
			esc_html__( 'Link Smartly', 'link-smartly' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue admin assets only on the plugin page.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook The current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {
		if ( $hook !== $this->hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'lsm-admin',
			LSM_PLUGIN_URL . 'assets/lsm-admin.css',
			array(),
			LSM_VERSION
		);

		wp_enqueue_script(
			'lsm-admin',
			LSM_PLUGIN_URL . 'assets/lsm-admin.js',
			array(),
			LSM_VERSION,
			true
		);

		wp_localize_script(
			'lsm-admin',
			'lsmAdmin',
			array(
				'confirmDelete'     => esc_html__( 'Are you sure you want to delete this keyword mapping?', 'link-smartly' ),
				'confirmBulkDelete' => esc_html__( 'Are you sure you want to delete the selected keyword mappings?', 'link-smartly' ),
				'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
				'nonce'             => wp_create_nonce( 'lsm_ajax' ),
				'noResults'         => esc_html__( 'No keyword mappings found.', 'link-smartly' ),
				'textActive'        => esc_html__( 'Active', 'link-smartly' ),
				'textInactive'      => esc_html__( 'Inactive', 'link-smartly' ),
				'textEdit'          => esc_html__( 'Edit', 'link-smartly' ),
				'textDelete'        => esc_html__( 'Delete', 'link-smartly' ),
				'textSave'          => esc_html__( 'Save', 'link-smartly' ),
				'textCancel'        => esc_html__( 'Cancel', 'link-smartly' ),
				'textGroup'         => esc_html__( 'Group', 'link-smartly' ),
				'textPage'          => esc_html__( 'Page', 'link-smartly' ),
				'textItems'         => esc_html__( 'items', 'link-smartly' ),
				'healthResults'     => $this->get_health_results(),
			)
		);
	}

	/**
	 * Add settings link to the plugins list page.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, string> $links Existing action links.
	 * @return array<int, string> Modified action links.
	 */
	public function add_settings_link( array $links ): array {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'options-general.php?page=' . self::PAGE_SLUG ) ),
			esc_html__( 'Settings', 'link-smartly' )
		);

		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * Add extra links to the plugin row meta on the Plugins page.
	 *
	 * Adds a "View Details" thickbox link and a "Docs" link.
	 *
	 * @since 1.2.0
	 *
	 * @param array<int, string> $meta   Existing row meta links.
	 * @param string             $file   Plugin basename being displayed.
	 * @return array<int, string> Modified row meta.
	 */
	public function add_row_meta( array $meta, string $file ): array {
		if ( LSM_PLUGIN_BASENAME !== $file ) {
			return $meta;
		}

		$meta[] = sprintf(
			'<a href="%s" class="thickbox open-plugin-details-modal" aria-label="%s">%s</a>',
			esc_url(
				admin_url( 'plugin-install.php?tab=plugin-information&plugin=link-smartly&TB_iframe=true&width=600&height=550' )
			),
			esc_attr__( 'More information about Link Smartly', 'link-smartly' ),
			esc_html__( 'View Details', 'link-smartly' )
		);

		$meta[] = sprintf(
			'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
			esc_url( 'https://github.com/ahmed-essawy/link-smartly' ),
			esc_html__( 'Docs', 'link-smartly' )
		);

		return $meta;
	}

	/**
	 * Supply plugin information for the "View Details" thickbox popup.
	 *
	 * WordPress calls this filter when displaying plugin-install.php with
	 * the plugin slug. Since Link Smartly is not yet on WP.org, we provide
	 * the metadata manually so the popup renders correctly.
	 *
	 * @since 1.2.0
	 *
	 * @param false|object|array<string, mixed> $result The result object or array. Default false.
	 * @param string                            $action The type of information being requested.
	 * @param object                            $args   Plugin API arguments.
	 * @return false|object The plugin information object or false for other plugins.
	 */
	public function plugin_info( $result, string $action, object $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		if ( ! isset( $args->slug ) || 'link-smartly' !== $args->slug ) {
			return $result;
		}

		$info = (object) array(
			'name'            => 'Link Smartly',
			'slug'            => 'link-smartly',
			'version'         => LSM_VERSION,
			'author'          => '<a href="https://minicad.io">Ahmed Essawy</a>',
			'author_profile'  => 'https://minicad.io',
			'homepage'        => 'https://github.com/ahmed-essawy/link-smartly',
			'requires'        => '6.0',
			'tested'          => '6.9',
			'requires_php'    => '8.0',
			'downloaded'      => 0,
			'last_updated'    => gmdate( 'Y-m-d' ),
			'sections'        => array(
				'description'  => '<p>' . esc_html__( 'Link Smartly automatically inserts internal links into your WordPress posts and pages based on keyword-to-URL mappings you define. It is the easiest way to build a strong internal link structure that improves your SEO rankings.', 'link-smartly' ) . '</p>'
					. '<h4>' . esc_html__( 'Key Features', 'link-smartly' ) . '</h4>'
					. '<ul>'
					. '<li>' . esc_html__( 'Keyword-to-URL mappings with groups, synonyms, and scheduling.', 'link-smartly' ) . '</li>'
					. '<li>' . esc_html__( 'Smart matching â€” case-insensitive, first-occurrence-only, longest-keyword priority.', 'link-smartly' ) . '</li>'
					. '<li>' . esc_html__( 'Safe linking â€” never links in headings, existing anchors, code blocks, or to the current page.', 'link-smartly' ) . '</li>'
					. '<li>' . esc_html__( 'Configurable limits â€” max links per post, minimum content length, per-keyword max uses.', 'link-smartly' ) . '</li>'
					. '<li>' . esc_html__( 'CSV import/export for bulk keyword management.', 'link-smartly' ) . '</li>'
					. '<li>' . esc_html__( 'Preview/dry-run to test links before going live.', 'link-smartly' ) . '</li>'
					. '<li>' . esc_html__( 'Analytics dashboard with per-keyword link counts and URL health checks.', 'link-smartly' ) . '</li>'
					. '<li>' . esc_html__( 'REST API, WP-CLI, and developer hooks for full extensibility.', 'link-smartly' ) . '</li>'
					. '</ul>',
				'installation' => '<ol>'
					. '<li>' . esc_html__( 'Upload the link-smartly folder to /wp-content/plugins/.', 'link-smartly' ) . '</li>'
					. '<li>' . esc_html__( 'Activate the plugin through the Plugins menu.', 'link-smartly' ) . '</li>'
					. '<li>' . esc_html__( 'Go to Settings â†’ Link Smartly to configure keyword mappings.', 'link-smartly' ) . '</li>'
					. '</ol>',
				'changelog'    => '<h4>' . esc_html( 'v' . LSM_VERSION ) . '</h4>'
					. '<ul>'
					. '<li>' . esc_html__( 'AJAX-powered keyword management â€” add, edit, delete, toggle without page reload.', 'link-smartly' ) . '</li>'
					. '<li>' . esc_html__( 'Server-side pagination, sorting, and search for keyword table.', 'link-smartly' ) . '</li>'
					. '<li>' . esc_html__( 'URL health checker with batch processing and status badges.', 'link-smartly' ) . '</li>'
					. '<li>' . esc_html__( 'Improved admin UX with clear field descriptions and safe defaults.', 'link-smartly' ) . '</li>'
					. '<li>' . esc_html__( 'Fully responsive admin pages across all breakpoints.', 'link-smartly' ) . '</li>'
					. '</ul>',
			),
			'banners'         => array(
				'low'  => '',
				'high' => '',
			),
		);

		return $info;
	}

	/**
	 * Render the admin page.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'keywords'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$settings   = Lsm_Settings::get_all();
		$keywords   = $this->keywords->get_all();
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		$renderer   = $this->renderer;

		require LSM_PLUGIN_DIR . 'admin/views/admin-page.php';
	}

	/**
	 * Get cached URL health results for JS localization.
	 *
	 * @since 1.2.0
	 *
	 * @return array<string, array{status: string, code: int}> Health results keyed by URL.
	 */
	private function get_health_results(): array {
		$health = new Lsm_Health( $this->keywords );

		return $health->get_results();
	}
}

