<?php
/**
 * Preview/dry-run handler for auto-linking.
 *
 * @package LinkSmartly
 * @since   1.0.0
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the preview/test feature that shows which links would be inserted.
 *
 * @since 1.0.0
 */
class Lsm_Preview {

	/**
	 * Keywords manager instance.
	 *
	 * @since 1.0.0
	 * @var Lsm_Keywords
	 */
	private Lsm_Keywords $keywords;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param Lsm_Keywords $keywords Keywords manager.
	 */
	public function __construct( Lsm_Keywords $keywords ) {
		$this->keywords = $keywords;
	}

	/**
	 * Initialize hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_post_lsm_preview', array( $this, 'handle_preview' ) );
	}

	/**
	 * Handle the preview form submission.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function handle_preview(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'link-smartly' ), 403 );
		}

		check_admin_referer( 'lsm_preview_action', 'lsm_nonce' );

		$post_id = isset( $_POST['lsm_post_id'] )
			? absint( wp_unslash( $_POST['lsm_post_id'] ) )
			: 0;

		if ( 0 === $post_id ) {
			$this->redirect_with_error( 'invalid_post' );
			return;
		}

		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post ) {
			$this->redirect_with_error( 'invalid_post' );
			return;
		}

		$results = array(
			'content'  => $post->post_content,
			'links'    => array(),
			'excluded' => false,
		);

		if ( ! Lsm_Meta_Box::is_excluded( $post->ID ) ) {
			$content  = apply_filters( 'the_content', $post->post_content );
			$url      = (string) get_permalink( $post );
			$settings = Lsm_Settings::get_all();

			$linker  = new Lsm_Linker( $this->keywords, $settings );
			$results = $linker->preview( $content, $url );
		} else {
			$results['excluded'] = true;
		}

		$results['title'] = $post->post_title;

		Lsm_Cache::set( 'preview_results_' . get_current_user_id(), $results, 60 );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'            => Lsm_Admin::PAGE_SLUG,
					'tab'             => 'preview',
					'preview_results' => '1',
				),
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}

	/**
	 * Redirect back to the preview tab with an error code.
	 *
	 * @since 1.0.0
	 *
	 * @param string $code Error code.
	 * @return void
	 */
	private function redirect_with_error( string $code ): void {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'  => Lsm_Admin::PAGE_SLUG,
					'tab'   => 'preview',
					'error' => $code,
				),
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}
}
