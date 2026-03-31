<?php
/**
 * Post meta box for excluding individual posts from auto-linking.
 *
 * @package LinkSmartly
 * @since   1.1.0
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers a meta box that lets editors disable auto-linking on specific posts.
 *
 * @since 1.1.0
 */
class Lsm_Meta_Box {

	/**
	 * Post meta key.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	const META_KEY = '_lsm_exclude';

	/**
	 * Nonce action.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	const NONCE_ACTION = 'lsm_meta_box_save';

	/**
	 * Initialize hooks.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'add_meta_boxes', array( $this, 'register' ) );
		add_action( 'save_post', array( $this, 'save' ), 10, 2 );
		add_action( 'init', array( $this, 'register_meta' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
	}

	/**
	 * Register post meta for REST API access (block editor).
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	public function register_meta(): void {
		$settings   = Lsm_Settings::get_all();
		$post_types = (array) ( $settings['post_types'] ?? array( 'post', 'page' ) );

		foreach ( $post_types as $post_type ) {
			register_post_meta(
				$post_type,
				self::META_KEY,
				array(
					'show_in_rest'  => true,
					'single'        => true,
					'type'          => 'string',
					'default'       => '',
					'auth_callback' => static function () {
						return current_user_can( 'edit_posts' );
					},
				)
			);
		}
	}

	/**
	 * Enqueue the block editor sidebar panel script.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	public function enqueue_editor_assets(): void {
		$screen = get_current_screen();

		if ( null === $screen || ! $screen->is_block_editor() ) {
			return;
		}

		$settings   = Lsm_Settings::get_all();
		$post_types = (array) ( $settings['post_types'] ?? array( 'post', 'page' ) );

		if ( ! in_array( $screen->post_type, $post_types, true ) ) {
			return;
		}

		$keywords       = new Lsm_Keywords();
		$active         = $keywords->get_active();
		$keyword_count  = count( $active );

		wp_enqueue_script(
			'lsm-editor',
			LSM_PLUGIN_URL . 'assets/lsm-editor.js',
			array( 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-i18n' ),
			LSM_VERSION,
			true
		);

		wp_localize_script(
			'lsm-editor',
			'lsmEditor',
			array(
				'metaKey'      => self::META_KEY,
				'keywordCount' => $keyword_count,
			)
		);
	}

	/**
	 * Register the meta box on supported post types.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public function register(): void {
		$settings   = Lsm_Settings::get_all();
		$post_types = (array) ( $settings['post_types'] ?? array( 'post', 'page' ) );

		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'lsm-exclude',
				esc_html__( 'Link Smartly', 'link-smartly' ),
				array( $this, 'render' ),
				$post_type,
				'side',
				'low'
			);
		}
	}

	/**
	 * Render the meta box content.
	 *
	 * @since 1.1.0
	 *
	 * @param WP_Post $post The current post.
	 * @return void
	 */
	public function render( WP_Post $post ): void {
		$excluded = (bool) get_post_meta( $post->ID, self::META_KEY, true );

		wp_nonce_field( self::NONCE_ACTION, 'lsm_meta_nonce' );
		?>
		<label>
			<input type="checkbox"
				   name="lsm_exclude_post"
				   value="1"
				   <?php checked( $excluded ); ?> />
			<?php esc_html_e( 'Disable auto-linking on this post', 'link-smartly' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'Check this box to prevent Link Smartly from inserting automatic links in this content.', 'link-smartly' ); ?>
		</p>
		<?php
	}

	/**
	 * Save the meta box value.
	 *
	 * @since 1.1.0
	 *
	 * @param int     $post_id The post ID.
	 * @param WP_Post $post    The post object.
	 * @return void
	 */
	public function save( int $post_id, WP_Post $post ): void {
		if ( ! isset( $_POST['lsm_meta_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lsm_meta_nonce'] ) ), self::NONCE_ACTION ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$excluded = ! empty( $_POST['lsm_exclude_post'] );

		if ( $excluded ) {
			update_post_meta( $post_id, self::META_KEY, '1' );
		} else {
			delete_post_meta( $post_id, self::META_KEY );
		}
	}

	/**
	 * Check if a post is excluded from auto-linking.
	 *
	 * @since 1.1.0
	 *
	 * @param int $post_id The post ID.
	 * @return bool True if excluded.
	 */
	public static function is_excluded( int $post_id ): bool {
		return (bool) get_post_meta( $post_id, self::META_KEY, true );
	}
}
