<?php
/**
 * Admin page template — delegates rendering to Lsm_Admin_Renderer.
 *
 * This file is required from within Lsm_Admin::render_page(), so $this
 * refers to the Lsm_Admin instance and all local variables ($active_tab,
 * $settings, $keywords, $post_types, $renderer) are set before inclusion.
 *
 * @package LinkSmartly
 * @since   1.0.0
 *
 * @var Lsm_Admin                    $this       The admin instance.
 * @var Lsm_Admin_Renderer           $renderer   The admin renderer instance.
 * @var string                       $active_tab Current active tab.
 * @var array<string, mixed>         $settings   Plugin settings.
 * @var array<int, array>            $keywords   All keyword mappings.
 * @var array<string, WP_Post_Type>  $post_types Available public post types.
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap lsm-wrap">
	<h1><?php esc_html_e( 'Link Smartly', 'link-smartly' ); ?></h1>

	<?php $renderer->render_notices(); ?>

	<nav class="nav-tab-wrapper lsm-tabs">
		<a href="<?php echo esc_url( admin_url( 'options-general.php?page=link-smartly&tab=keywords' ) ); ?>"
		   class="nav-tab <?php echo 'keywords' === $active_tab ? 'nav-tab-active' : ''; ?>"
		   aria-label="<?php esc_attr_e( 'Keywords tab', 'link-smartly' ); ?>">
			<?php esc_html_e( 'Keywords', 'link-smartly' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'options-general.php?page=link-smartly&tab=settings' ) ); ?>"
		   class="nav-tab <?php echo 'settings' === $active_tab ? 'nav-tab-active' : ''; ?>"
		   aria-label="<?php esc_attr_e( 'Settings tab', 'link-smartly' ); ?>">
			<?php esc_html_e( 'Settings', 'link-smartly' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'options-general.php?page=link-smartly&tab=analytics' ) ); ?>"
		   class="nav-tab <?php echo 'analytics' === $active_tab ? 'nav-tab-active' : ''; ?>"
		   aria-label="<?php esc_attr_e( 'Analytics tab', 'link-smartly' ); ?>">
			<?php esc_html_e( 'Analytics', 'link-smartly' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'options-general.php?page=link-smartly&tab=import-export' ) ); ?>"
		   class="nav-tab <?php echo 'import-export' === $active_tab ? 'nav-tab-active' : ''; ?>"
		   aria-label="<?php esc_attr_e( 'Import/Export tab', 'link-smartly' ); ?>">
			<?php esc_html_e( 'Import / Export', 'link-smartly' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'options-general.php?page=link-smartly&tab=preview' ) ); ?>"
		   class="nav-tab <?php echo 'preview' === $active_tab ? 'nav-tab-active' : ''; ?>"
		   aria-label="<?php esc_attr_e( 'Preview tab', 'link-smartly' ); ?>">
			<?php esc_html_e( 'Preview', 'link-smartly' ); ?>
		</a>
	</nav>

	<div class="lsm-tab-content">
		<?php
		switch ( $active_tab ) {
			case 'settings':
				$renderer->render_settings_tab( $settings, $post_types );
				break;

			case 'analytics':
				$renderer->render_analytics_tab( $keywords );
				break;

			case 'import-export':
				$renderer->render_import_export_tab();
				break;

			case 'preview':
				$renderer->render_preview_tab();
				break;

			default:
				$renderer->render_keywords_tab( $keywords );
				break;
		}
		?>
	</div>
</div>
