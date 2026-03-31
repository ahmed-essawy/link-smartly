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
			esc_url( 'https://www.paypal.com/paypalme/ahmessawy/10USD' ),
			esc_html__( 'Donate', 'link-smartly' )
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
			'donate_link'     => 'https://www.paypal.com/paypalme/ahmessawy/10USD',
			'requires'        => '6.3',
			'tested'          => '7.0',
			'requires_php'    => '7.4',
			'downloaded'      => 0,
			'last_updated'    => gmdate( 'Y-m-d' ),
			'sections'        => array(
				'description'  => '<p>' . esc_html__( 'Link Smartly automatically inserts internal links into your WordPress posts and pages based on keyword-to-URL mappings you define. It is the easiest way to build a strong internal link structure that improves your SEO rankings.', 'link-smartly' ) . '</p>'
					. '<h4>' . esc_html__( 'Key Features', 'link-smartly' ) . '</h4>'
					. '<ul>'
					. '<li>' . esc_html__( 'Keyword-to-URL mappings with groups, synonyms, and scheduling.', 'link-smartly' ) . '</li>'
. '<li>' . esc_html__( 'Smart matching — case-insensitive, first-occurrence-only, longest-keyword priority.', 'link-smartly' ) . '</li>'
					. '<li>' . esc_html__( 'Safe linking — never links in headings, existing anchors, code blocks, or to the current page.', 'link-smartly' ) . '</li>'
					. '<li>' . esc_html__( 'Configurable limits — max links per post, minimum content length, per-keyword max uses.', 'link-smartly' ) . '</li>'
					. '<li>' . esc_html__( 'CSV import/export for bulk keyword management.', 'link-smartly' ) . '</li>'
					. '<li>' . esc_html__( 'Preview/dry-run to test links before going live.', 'link-smartly' ) . '</li>'
					. '<li>' . esc_html__( 'Analytics dashboard with per-keyword link counts and URL health checks.', 'link-smartly' ) . '</li>'
					. '<li>' . esc_html__( 'REST API, WP-CLI, and developer hooks for full extensibility.', 'link-smartly' ) . '</li>'
					. '</ul>',
				'installation' => '<h4>' . esc_html__( 'Installation from within WordPress', 'link-smartly' ) . '</h4>'
					. '<ol>'
					. '<li>' . esc_html__( 'Visit Plugins > Add New.', 'link-smartly' ) . '</li>'
					. '<li>' . esc_html__( 'Search for Link Smartly.', 'link-smartly' ) . '</li>'
					. '<li>' . esc_html__( 'Install and activate the Link Smartly plugin.', 'link-smartly' ) . '</li>'
					. '</ol>'
					. '<h4>' . esc_html__( 'Manual installation', 'link-smartly' ) . '</h4>'
					. '<ol>'
					. '<li>' . esc_html__( 'Upload the entire link-smartly folder to the /wp-content/plugins/ directory.', 'link-smartly' ) . '</li>'
					. '<li>' . esc_html__( 'Visit Plugins.', 'link-smartly' ) . '</li>'
					. '<li>' . esc_html__( 'Activate the Link Smartly plugin.', 'link-smartly' ) . '</li>'
					. '</ol>',
				'changelog'    => '<h4>v1.3.0</h4>'
					. '<ul>'
					. '<li>' . esc_html__( 'Added unified cache layer with object cache support and transient fallback.', 'link-smartly' ) . '</li>'
					. '<li>' . esc_html__( 'Added keyword suggestion engine that scans existing content for link opportunities.', 'link-smartly' ) . '</li>'
					. '<li>' . esc_html__( 'Added orphan content detector to find pages not targeted by any keyword.', 'link-smartly' ) . '</li>'
					. '<li>' . esc_html__( 'Added WordPress dashboard widget with quick stats overview.', 'link-smartly' ) . '</li>'
					. '<li>' . esc_html__( 'Added link distribution report showing how links are spread across target URLs.', 'link-smartly' ) . '</li>'
					. '<li>' . esc_html__( 'Added analytics CSV export with keyword stats, health status, and link counts.', 'link-smartly' ) . '</li>'
					. '<li>' . esc_html__( 'Added weekly email digest with top performers, broken URLs, and zero-link keywords.', 'link-smartly' ) . '</li>'
					. '<li>' . esc_html__( 'Added WP-Cron automated health checks with configurable schedule.', 'link-smartly' ) . '</li>'
					. '<li>' . esc_html__( 'Added content processing cache for faster repeat renders.', 'link-smartly' ) . '</li>'
					. '<li>' . esc_html__( 'Added Gutenberg sidebar panel with auto-linking toggle and keyword count.', 'link-smartly' ) . '</li>'
					. '<li>' . esc_html__( 'Added REST API endpoints for suggestions and orphan pages.', 'link-smartly' ) . '</li>'
					. '<li>' . esc_html__( 'Added WP-CLI commands for keyword suggestions and orphan detection.', 'link-smartly' ) . '</li>'
					. '<li>' . esc_html__( 'Expanded inline edit to support group and active status fields.', 'link-smartly' ) . '</li>'
					. '<li>' . esc_html__( 'Added Automation settings section for cron health checks and email digest.', 'link-smartly' ) . '</li>'
					. '</ul>'
					. '<details><summary><strong>v1.2.0</strong></summary>'
					. '<ul>'
					. '<li>' . esc_html__( 'Added AJAX keyword CRUD without page reloads.', 'link-smartly' ) . '</li>'
					. '<li>' . esc_html__( 'Added server-side pagination for keywords table.', 'link-smartly' ) . '</li>'
					. '<li>' . esc_html__( 'Added sortable columns with visual sort indicators.', 'link-smartly' ) . '</li>'
					. '<li>' . esc_html__( 'Added debounced search and filter for keywords.', 'link-smartly' ) . '</li>'
					. '<li>' . esc_html__( 'Added URL health checker with batch HTTP HEAD requests.', 'link-smartly' ) . '</li>'
					. '<li>' . esc_html__( 'Added health badges and summary cards.', 'link-smartly' ) . '</li>'
					. '<li>' . esc_html__( 'Added REST API health endpoints.', 'link-smartly' ) . '</li>'
					. '<li>' . esc_html__( 'Added WP-CLI check-urls command.', 'link-smartly' ) . '</li>'
					. '<li>' . esc_html__( 'Added undo on AJAX delete with 5-minute window.', 'link-smartly' ) . '</li>'
					. '<li>' . esc_html__( 'Added AJAX bulk actions.', 'link-smartly' ) . '</li>'
					. '</ul>'
					. '</details>'
					. '<details><summary><strong>v1.1.0</strong></summary>'
					. '<ul>'
					. '<li>' . esc_html__( 'Added keyword groups for organization.', 'link-smartly' ) . '</li>'
					. '<li>' . esc_html__( 'Added synonym/alias support per keyword.', 'link-smartly' ) . '</li>'
					. '<li>' . esc_html__( 'Added per-keyword nofollow and new-tab overrides.', 'link-smartly' ) . '</li>'
					. '<li>' . esc_html__( 'Added per-keyword lifetime link limit (max uses).', 'link-smartly' ) . '</li>'
					. '<li>' . esc_html__( 'Added scheduled linking with start and end dates.', 'link-smartly' ) . '</li>'
					. '<li>' . esc_html__( 'Added link analytics dashboard with per-keyword counts.', 'link-smartly' ) . '</li>'
					. '<li>' . esc_html__( 'Added bulk actions (activate, deactivate, delete).', 'link-smartly' ) . '</li>'
					. '<li>' . esc_html__( 'Added search and filter for keywords.', 'link-smartly' ) . '</li>'
					. '<li>' . esc_html__( 'Added post-level exclusion meta box.', 'link-smartly' ) . '</li>'
					. '<li>' . esc_html__( 'Added external link auto-detection with nofollow and noopener.', 'link-smartly' ) . '</li>'
					. '<li>' . esc_html__( 'Added duplicate keyword detection.', 'link-smartly' ) . '</li>'
					. '<li>' . esc_html__( 'Added undo for deleted keywords.', 'link-smartly' ) . '</li>'
					. '<li>' . esc_html__( 'Added WP-CLI support.', 'link-smartly' ) . '</li>'
					. '<li>' . esc_html__( 'Added REST API endpoints.', 'link-smartly' ) . '</li>'
					. '<li>' . esc_html__( 'Extended CSV import/export with new fields.', 'link-smartly' ) . '</li>'
					. '</ul>'
					. '</details>'
					. '<details><summary><strong>v1.0.0</strong></summary>'
					. '<ul>'
					. '<li>' . esc_html__( 'Initial release.', 'link-smartly' ) . '</li>'
					. '<li>' . esc_html__( 'Keyword-to-URL mapping management with add, edit, delete, and toggle.', 'link-smartly' ) . '</li>'
					. '<li>' . esc_html__( 'DOMDocument-based content processing for safe and reliable linking.', 'link-smartly' ) . '</li>'
					. '<li>' . esc_html__( 'Case-insensitive matching with longest-keyword priority.', 'link-smartly' ) . '</li>'
					. '<li>' . esc_html__( 'Configurable max links per post and minimum content word count.', 'link-smartly' ) . '</li>'
					. '<li>' . esc_html__( 'Post type selection for auto-linking targets.', 'link-smartly' ) . '</li>'
					. '<li>' . esc_html__( 'CSV import and export for bulk keyword management.', 'link-smartly' ) . '</li>'
					. '<li>' . esc_html__( 'Preview/dry-run feature for testing link insertion.', 'link-smartly' ) . '</li>'
					. '<li>' . esc_html__( 'Full developer hook support (filters and actions).', 'link-smartly' ) . '</li>'
					. '<li>' . esc_html__( 'Sample keyword data loaded on activation.', 'link-smartly' ) . '</li>'
					. '</ul>'
					. '</details>',
			),
			'banners'         => array(
				'low'  => 'https://raw.githubusercontent.com/ahmed-essawy/link-smartly/assets/banner-1536x1024.png',
				'high' => 'https://raw.githubusercontent.com/ahmed-essawy/link-smartly/assets/banner-1536x1024.png',
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

