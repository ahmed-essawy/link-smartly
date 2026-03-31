<?php
/**
 * WordPress dashboard widget for Link Smartly.
 *
 * @package LinkSmartly
 * @since   1.3.0
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds a quick-stats dashboard widget to the WordPress admin dashboard.
 *
 * @since 1.3.0
 */
class Lsm_Dashboard {

	/**
	 * Keywords manager instance.
	 *
	 * @since 1.3.0
	 * @var Lsm_Keywords
	 */
	private Lsm_Keywords $keywords;

	/**
	 * Constructor.
	 *
	 * @since 1.3.0
	 *
	 * @param Lsm_Keywords $keywords Keywords manager.
	 */
	public function __construct( Lsm_Keywords $keywords ) {
		$this->keywords = $keywords;
	}

	/**
	 * Initialize hooks.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_dashboard_setup', array( $this, 'register_widget' ) );
	}

	/**
	 * Register the dashboard widget.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	public function register_widget(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'lsm_dashboard_widget',
			esc_html__( 'Link Smartly', 'link-smartly' ),
			array( $this, 'render_widget' )
		);
	}

	/**
	 * Render the dashboard widget content.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	public function render_widget(): void {
		$all     = $this->keywords->get_all();
		$total   = count( $all );
		$active  = 0;
		$links   = 0;

		foreach ( $all as $entry ) {
			if ( ! empty( $entry['active'] ) ) {
				++$active;
			}

			$links += (int) ( $entry['link_count'] ?? 0 );
		}

		$health  = new Lsm_Health( $this->keywords );
		$summary = $health->get_summary();
		$broken  = $summary['error'];

		$plugin_url = add_query_arg(
			'page',
			Lsm_Admin::PAGE_SLUG,
			admin_url( 'options-general.php' )
		);
		?>
		<div class="lsm-dashboard-widget">
			<div class="lsm-dashboard-stats" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
				<div style="text-align: center; padding: 10px; background: #f0f0f1; border-radius: 4px;">
					<div style="font-size: 24px; font-weight: 600; color: #1d2327;"><?php echo esc_html( (string) $total ); ?></div>
					<div style="font-size: 12px; color: #646970;"><?php esc_html_e( 'Total Keywords', 'link-smartly' ); ?></div>
				</div>
				<div style="text-align: center; padding: 10px; background: #f0f0f1; border-radius: 4px;">
					<div style="font-size: 24px; font-weight: 600; color: #00a32a;"><?php echo esc_html( (string) $active ); ?></div>
					<div style="font-size: 12px; color: #646970;"><?php esc_html_e( 'Active', 'link-smartly' ); ?></div>
				</div>
				<div style="text-align: center; padding: 10px; background: #f0f0f1; border-radius: 4px;">
					<div style="font-size: 24px; font-weight: 600; color: #2271b1;"><?php echo esc_html( (string) $links ); ?></div>
					<div style="font-size: 12px; color: #646970;"><?php esc_html_e( 'Links Inserted', 'link-smartly' ); ?></div>
				</div>
				<div style="text-align: center; padding: 10px; background: <?php echo $broken > 0 ? '#fcf0f1' : '#f0f0f1'; ?>; border-radius: 4px;">
					<div style="font-size: 24px; font-weight: 600; color: <?php echo $broken > 0 ? '#d63638' : '#1d2327'; ?>;"><?php echo esc_html( (string) $broken ); ?></div>
					<div style="font-size: 12px; color: #646970;"><?php esc_html_e( 'Broken URLs', 'link-smartly' ); ?></div>
				</div>
			</div>
			<p style="margin: 0;">
				<a href="<?php echo esc_url( $plugin_url ); ?>" class="button button-primary">
					<?php esc_html_e( 'View Details', 'link-smartly' ); ?>
				</a>
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'analytics', $plugin_url ) ); ?>" style="margin-left: 8px;">
					<?php esc_html_e( 'Analytics', 'link-smartly' ); ?> &rarr;
				</a>
			</p>
		</div>
		<?php
	}
}
