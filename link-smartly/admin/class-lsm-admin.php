<?php
/**
 * Admin page and keyword management.
 *
 * @package LinkSmartly
 * @since   1.0.0
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the admin menu page and handles keyword CRUD operations.
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
		add_action( 'admin_post_lsm_save_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_lsm_add_keyword', array( $this, 'handle_add_keyword' ) );
		add_action( 'admin_post_lsm_delete_keyword', array( $this, 'handle_delete_keyword' ) );
		add_action( 'admin_post_lsm_toggle_keyword', array( $this, 'handle_toggle_keyword' ) );
		add_action( 'admin_post_lsm_edit_keyword', array( $this, 'handle_edit_keyword' ) );
		add_action( 'admin_post_lsm_bulk_action', array( $this, 'handle_bulk_action' ) );
		add_action( 'admin_post_lsm_undo', array( $this, 'handle_undo' ) );
		add_action( 'admin_post_lsm_reset_stats', array( $this, 'handle_reset_stats' ) );
		add_action( 'admin_post_lsm_scan_posts', array( $this, 'handle_scan_posts' ) );
		add_filter( 'plugin_action_links_' . LSM_PLUGIN_BASENAME, array( $this, 'add_settings_link' ) );
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

		require LSM_PLUGIN_DIR . 'admin/views/admin-page.php';
	}

	/**
	 * Handle settings form submission.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function handle_save_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'link-smartly' ), 403 );
		}

		check_admin_referer( self::NONCE_SETTINGS, 'lsm_nonce' );

		$input = array(
			'enabled'            => ! empty( $_POST['lsm_enabled'] ),
			'max_links_per_post' => isset( $_POST['lsm_max_links'] ) ? absint( wp_unslash( $_POST['lsm_max_links'] ) ) : 3,
			'min_content_words'  => isset( $_POST['lsm_min_words'] ) ? absint( wp_unslash( $_POST['lsm_min_words'] ) ) : 300,
			'post_types'         => isset( $_POST['lsm_post_types'] ) && is_array( $_POST['lsm_post_types'] )
				? array_map( 'sanitize_key', wp_unslash( $_POST['lsm_post_types'] ) )
				: array(),
			'link_class'         => isset( $_POST['lsm_link_class'] )
				? sanitize_html_class( wp_unslash( $_POST['lsm_link_class'] ) )
				: 'lsm-auto-link',
			'add_title_attr'     => ! empty( $_POST['lsm_title_attr'] ),
			'nofollow'           => ! empty( $_POST['lsm_nofollow'] ),
			'new_tab'            => ! empty( $_POST['lsm_new_tab'] ),
		);

		Lsm_Settings::save( $input );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => self::PAGE_SLUG,
					'tab'     => 'settings',
					'updated' => '1',
				),
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}

	/**
	 * Handle adding a new keyword mapping.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function handle_add_keyword(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'link-smartly' ), 403 );
		}

		check_admin_referer( self::NONCE_KEYWORD, 'lsm_nonce' );

		$keyword = isset( $_POST['lsm_keyword'] )
			? sanitize_text_field( wp_unslash( $_POST['lsm_keyword'] ) )
			: '';
		$url     = isset( $_POST['lsm_url'] )
			? esc_url_raw( wp_unslash( $_POST['lsm_url'] ) )
			: '';

		if ( '' === $keyword || '' === $url ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'  => self::PAGE_SLUG,
						'tab'   => 'keywords',
						'error' => 'empty_fields',
					),
					admin_url( 'options-general.php' )
				)
			);
			exit;
		}

		// Duplicate detection.
		$duplicates = $this->keywords->find_duplicates( $keyword );

		if ( ! empty( $duplicates ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'  => self::PAGE_SLUG,
						'tab'   => 'keywords',
						'error' => 'duplicate_keyword',
					),
					admin_url( 'options-general.php' )
				)
			);
			exit;
		}

		// Read extended fields.
		$group      = isset( $_POST['lsm_group'] ) ? sanitize_text_field( wp_unslash( $_POST['lsm_group'] ) ) : '';
		$synonyms   = isset( $_POST['lsm_synonyms'] ) ? sanitize_text_field( wp_unslash( $_POST['lsm_synonyms'] ) ) : '';
		$max_uses   = isset( $_POST['lsm_max_uses'] ) ? absint( wp_unslash( $_POST['lsm_max_uses'] ) ) : 0;
		$nofollow   = isset( $_POST['lsm_nofollow_kw'] ) ? sanitize_key( wp_unslash( $_POST['lsm_nofollow_kw'] ) ) : 'default';
		$new_tab    = isset( $_POST['lsm_new_tab_kw'] ) ? sanitize_key( wp_unslash( $_POST['lsm_new_tab_kw'] ) ) : 'default';
		$start_date = isset( $_POST['lsm_start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['lsm_start_date'] ) ) : '';
		$end_date   = isset( $_POST['lsm_end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['lsm_end_date'] ) ) : '';

		$all   = $this->keywords->get_all();
		$all[] = $this->keywords->sanitize_entry(
			array(
				'id'         => wp_generate_uuid4(),
				'keyword'    => $keyword,
				'url'        => $url,
				'active'     => true,
				'group'      => $group,
				'synonyms'   => $synonyms,
				'max_uses'   => $max_uses,
				'nofollow'   => $nofollow,
				'new_tab'    => $new_tab,
				'start_date' => $start_date,
				'end_date'   => $end_date,
			)
		);
		$this->keywords->save_all( $all );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'  => self::PAGE_SLUG,
					'tab'   => 'keywords',
					'added' => '1',
				),
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}

	/**
	 * Handle deleting a keyword mapping.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function handle_delete_keyword(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'link-smartly' ), 403 );
		}

		check_admin_referer( self::NONCE_KEYWORD, 'lsm_nonce' );

		$id = isset( $_POST['lsm_keyword_id'] )
			? sanitize_text_field( wp_unslash( $_POST['lsm_keyword_id'] ) )
			: '';

		if ( '' !== $id ) {
			// Store entry for undo before deleting.
			$all = $this->keywords->get_all();

			foreach ( $all as $entry ) {
				if ( $entry['id'] === $id ) {
					set_transient( 'lsm_undo_' . get_current_user_id(), array( $entry ), 300 );
					break;
				}
			}

			$this->keywords->delete( $id );
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => self::PAGE_SLUG,
					'tab'     => 'keywords',
					'deleted' => '1',
				),
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}

	/**
	 * Handle toggling a keyword mapping active/inactive.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function handle_toggle_keyword(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'link-smartly' ), 403 );
		}

		check_admin_referer( self::NONCE_KEYWORD, 'lsm_nonce' );

		$id     = isset( $_POST['lsm_keyword_id'] )
			? sanitize_text_field( wp_unslash( $_POST['lsm_keyword_id'] ) )
			: '';
		$active = ! empty( $_POST['lsm_active'] );

		if ( '' !== $id ) {
			$this->keywords->update( $id, array( 'active' => $active ) );
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => self::PAGE_SLUG,
					'tab'     => 'keywords',
					'toggled' => '1',
				),
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}

	/**
	 * Handle editing a keyword mapping.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function handle_edit_keyword(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'link-smartly' ), 403 );
		}

		check_admin_referer( self::NONCE_KEYWORD, 'lsm_nonce' );

		$id      = isset( $_POST['lsm_keyword_id'] )
			? sanitize_text_field( wp_unslash( $_POST['lsm_keyword_id'] ) )
			: '';
		$keyword = isset( $_POST['lsm_keyword'] )
			? sanitize_text_field( wp_unslash( $_POST['lsm_keyword'] ) )
			: '';
		$url     = isset( $_POST['lsm_url'] )
			? esc_url_raw( wp_unslash( $_POST['lsm_url'] ) )
			: '';

		if ( '' !== $id && '' !== $keyword && '' !== $url ) {
			$group      = isset( $_POST['lsm_group'] ) ? sanitize_text_field( wp_unslash( $_POST['lsm_group'] ) ) : '';
			$synonyms   = isset( $_POST['lsm_synonyms'] ) ? sanitize_text_field( wp_unslash( $_POST['lsm_synonyms'] ) ) : '';
			$max_uses   = isset( $_POST['lsm_max_uses'] ) ? absint( wp_unslash( $_POST['lsm_max_uses'] ) ) : 0;
			$nofollow   = isset( $_POST['lsm_nofollow_kw'] ) ? sanitize_key( wp_unslash( $_POST['lsm_nofollow_kw'] ) ) : 'default';
			$new_tab    = isset( $_POST['lsm_new_tab_kw'] ) ? sanitize_key( wp_unslash( $_POST['lsm_new_tab_kw'] ) ) : 'default';
			$start_date = isset( $_POST['lsm_start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['lsm_start_date'] ) ) : '';
			$end_date   = isset( $_POST['lsm_end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['lsm_end_date'] ) ) : '';

			$this->keywords->update(
				$id,
				array(
					'keyword'    => $keyword,
					'url'        => $url,
					'group'      => $group,
					'synonyms'   => $synonyms,
					'max_uses'   => $max_uses,
					'nofollow'   => $nofollow,
					'new_tab'    => $new_tab,
					'start_date' => $start_date,
					'end_date'   => $end_date,
				)
			);
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'   => self::PAGE_SLUG,
					'tab'    => 'keywords',
					'edited' => '1',
				),
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}

	/**
	 * Handle bulk actions on keyword mappings.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public function handle_bulk_action(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'link-smartly' ), 403 );
		}

		check_admin_referer( self::NONCE_KEYWORD, 'lsm_nonce' );

		$action = isset( $_POST['lsm_bulk_action'] )
			? sanitize_key( wp_unslash( $_POST['lsm_bulk_action'] ) )
			: '';
		$ids    = isset( $_POST['lsm_bulk_ids'] ) && is_array( $_POST['lsm_bulk_ids'] )
			? array_map( 'sanitize_text_field', wp_unslash( $_POST['lsm_bulk_ids'] ) )
			: array();

		if ( '' === $action || empty( $ids ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page' => self::PAGE_SLUG,
						'tab'  => 'keywords',
					),
					admin_url( 'options-general.php' )
				)
			);
			exit;
		}

		$all     = $this->keywords->get_all();
		$count   = 0;

		foreach ( $all as $index => $entry ) {
			if ( ! in_array( $entry['id'], $ids, true ) ) {
				continue;
			}

			switch ( $action ) {
				case 'activate':
					$all[ $index ]['active'] = true;
					++$count;
					break;

				case 'deactivate':
					$all[ $index ]['active'] = false;
					++$count;
					break;

				case 'delete':
					unset( $all[ $index ] );
					++$count;
					break;
			}
		}

		$this->keywords->save_all( array_values( $all ) );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'  => self::PAGE_SLUG,
					'tab'   => 'keywords',
					'bulk'  => $action,
					'count' => (string) $count,
				),
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}

	/**
	 * Handle resetting all link statistics.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public function handle_reset_stats(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'link-smartly' ), 403 );
		}

		check_admin_referer( 'lsm_reset_stats', 'lsm_nonce' );

		$this->keywords->reset_link_counts();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => self::PAGE_SLUG,
					'tab'     => 'analytics',
					'updated' => '1',
				),
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}

	/**
	 * Handle the "Scan All Posts" action to build the keyword-to-post mapping.
	 *
	 * Runs the linker in dry-run mode across all published content to discover
	 * which keywords match which posts.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function handle_scan_posts(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'link-smartly' ), 403 );
		}

		check_admin_referer( 'lsm_scan_posts', 'lsm_nonce' );

		$settings      = Lsm_Settings::get_all();
		$allowed_types = (array) ( $settings['post_types'] ?? array( 'post', 'page' ) );
		$min_words     = (int) ( $settings['min_content_words'] ?? 300 );
		$max_links     = (int) ( $settings['max_links_per_post'] ?? 3 );

		$linker      = new Lsm_Linker( $this->keywords, $settings );
		$keyword_map = $this->keywords->get_active();

		if ( empty( $keyword_map ) || empty( $allowed_types ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'    => self::PAGE_SLUG,
						'tab'     => 'analytics',
						'scanned' => '0',
					),
					admin_url( 'options-general.php' )
				)
			);
			exit;
		}

		$posts = get_posts(
			array(
				'post_type'      => $allowed_types,
				'post_status'    => 'publish',
				'posts_per_page' => 500,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		$linked_map = array();
		$scanned    = 0;

		foreach ( $posts as $post_id ) {
			$post = get_post( $post_id );

			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			// Check post-level exclusion.
			if ( Lsm_Meta_Box::is_excluded( $post->ID ) ) {
				continue;
			}

			$content    = apply_filters( 'the_content', $post->post_content );
			$word_count = str_word_count( wp_strip_all_tags( $content ) );

			if ( $word_count < $min_words ) {
				continue;
			}

			$url     = (string) get_permalink( $post );
			$results = $linker->preview( $content, $url );

			if ( ! empty( $results['links'] ) ) {
				foreach ( $results['links'] as $link ) {
					$keyword_id = $this->find_keyword_id_by_text( $keyword_map, $link['keyword'] );

					if ( '' !== $keyword_id ) {
						if ( ! isset( $linked_map[ $keyword_id ] ) ) {
							$linked_map[ $keyword_id ] = array();
						}

						$linked_map[ $keyword_id ][ $post->ID ] = $post->post_title;
					}
				}
			}

			++$scanned;
		}

		$this->keywords->save_linked_posts( $linked_map );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => self::PAGE_SLUG,
					'tab'     => 'analytics',
					'scanned' => (string) $scanned,
				),
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}

	/**
	 * Find a keyword entry ID by matching the linked text against keywords and synonyms.
	 *
	 * @since 1.2.0
	 *
	 * @param array<int, array<string, mixed>> $keyword_map Active keyword mappings.
	 * @param string                           $text        The matched text from the link.
	 * @return string The keyword entry ID, or empty string if not found.
	 */
	private function find_keyword_id_by_text( array $keyword_map, string $text ): string {
		$lower_text = mb_strtolower( $text );

		foreach ( $keyword_map as $mapping ) {
			if ( mb_strtolower( $mapping['keyword'] ) === $lower_text ) {
				return $mapping['id'];
			}

			$synonyms_str = trim( $mapping['synonyms'] ?? '' );

			if ( '' !== $synonyms_str ) {
				$synonyms = array_map( 'trim', explode( ',', $synonyms_str ) );

				foreach ( $synonyms as $synonym ) {
					if ( mb_strtolower( $synonym ) === $lower_text ) {
						return $mapping['id'];
					}
				}
			}
		}

		return '';
	}

	/**
	 * Handle undo of last delete action.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public function handle_undo(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'link-smartly' ), 403 );
		}

		check_admin_referer( 'lsm_undo_action', 'lsm_nonce' );

		$user_id       = get_current_user_id();
		$transient_key = 'lsm_undo_' . $user_id;
		$undo_data     = get_transient( $transient_key );
		delete_transient( $transient_key );

		$notice = 'undo_failed';

		if ( is_array( $undo_data ) && ! empty( $undo_data ) ) {
			$all   = $this->keywords->get_all();
			$all   = array_merge( $all, $undo_data );
			$this->keywords->save_all( $all );
			$notice = 'undo_success';
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'  => self::PAGE_SLUG,
					'tab'   => 'keywords',
					$notice => '1',
				),
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}

	/**
	 * Render admin notices based on query parameters.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function render_notices(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['updated'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'link-smartly' ) . '</p></div>';
		}

		if ( isset( $_GET['added'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Keyword mapping added.', 'link-smartly' ) . '</p></div>';
		}

		if ( isset( $_GET['deleted'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Keyword mapping deleted.', 'link-smartly' ) . '</p></div>';
		}

		if ( isset( $_GET['toggled'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Keyword mapping updated.', 'link-smartly' ) . '</p></div>';
		}

		if ( isset( $_GET['edited'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Keyword mapping updated.', 'link-smartly' ) . '</p></div>';
		}

		if ( isset( $_GET['bulk'] ) ) {
			$bulk_action = sanitize_key( wp_unslash( $_GET['bulk'] ) );
			$bulk_count  = isset( $_GET['count'] ) ? absint( $_GET['count'] ) : 0;

			if ( $bulk_count > 0 ) {
				$bulk_msg = '';

				switch ( $bulk_action ) {
					case 'activate':
						/* translators: %d: Number of keywords activated. */
						$bulk_msg = sprintf( _n( '%d keyword activated.', '%d keywords activated.', $bulk_count, 'link-smartly' ), $bulk_count );
						break;
					case 'deactivate':
						/* translators: %d: Number of keywords deactivated. */
						$bulk_msg = sprintf( _n( '%d keyword deactivated.', '%d keywords deactivated.', $bulk_count, 'link-smartly' ), $bulk_count );
						break;
					case 'delete':
						/* translators: %d: Number of keywords deleted. */
						$bulk_msg = sprintf( _n( '%d keyword deleted.', '%d keywords deleted.', $bulk_count, 'link-smartly' ), $bulk_count );
						break;
				}

				if ( '' !== $bulk_msg ) {
					echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $bulk_msg ) . '</p></div>';
				}
			}
		}

		if ( isset( $_GET['undo_success'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Action undone. Keywords restored.', 'link-smartly' ) . '</p></div>';
		}

		if ( isset( $_GET['undo_failed'] ) ) {
			echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Could not undo. The undo data has expired.', 'link-smartly' ) . '</p></div>';
		}

		if ( isset( $_GET['imported'] ) ) {
			$count = absint( $_GET['imported'] );
			echo '<div class="notice notice-success is-dismissible"><p>';
			printf(
				/* translators: %d: Number of keyword mappings imported. */
				esc_html( _n( '%d keyword mapping imported.', '%d keyword mappings imported.', $count, 'link-smartly' ) ),
				(int) $count
			);
			echo '</p></div>';
		}

		if ( isset( $_GET['error'] ) ) {
			$error = sanitize_key( wp_unslash( $_GET['error'] ) );
			$msg   = $this->get_error_message( $error );

			if ( '' !== $msg ) {
				echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Get a human-readable error message by error code.
	 *
	 * @since 1.0.0
	 *
	 * @param string $code Error code.
	 * @return string Error message.
	 */
	private function get_error_message( string $code ): string {
		$messages = array(
			'empty_fields'      => __( 'Both keyword and URL are required.', 'link-smartly' ),
			'invalid_csv'       => __( 'Invalid CSV file. Please upload a valid CSV file.', 'link-smartly' ),
			'upload_failed'     => __( 'File upload failed. Please try again.', 'link-smartly' ),
			'no_file'           => __( 'No file selected for import.', 'link-smartly' ),
			'invalid_post'      => __( 'Invalid post ID. Please enter a valid post ID.', 'link-smartly' ),
			'duplicate_keyword' => __( 'This keyword already exists. Duplicate keywords are not allowed.', 'link-smartly' ),
		);

		return $messages[ $code ] ?? '';
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

	/**
	 * Render the keywords management tab.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, array<string, mixed>> $keywords All keyword mappings.
	 * @return void
	 */
	private function render_keywords_tab( array $keywords ): void {
		$groups = $this->keywords->get_groups();

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$search_term = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$filter_group = isset( $_GET['group'] ) ? sanitize_text_field( wp_unslash( $_GET['group'] ) ) : '';
		$filter_status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		// Apply filters.
		$filtered = $keywords;

		if ( '' !== $search_term ) {
			$filtered = array_filter(
				$filtered,
				static function ( array $entry ) use ( $search_term ): bool {
					return false !== mb_stripos( $entry['keyword'], $search_term )
						|| false !== mb_stripos( $entry['url'], $search_term )
						|| false !== mb_stripos( $entry['group'] ?? '', $search_term )
						|| false !== mb_stripos( $entry['synonyms'] ?? '', $search_term );
				}
			);
		}

		if ( '' !== $filter_group ) {
			$filtered = array_filter(
				$filtered,
				static function ( array $entry ) use ( $filter_group ): bool {
					return ( $entry['group'] ?? '' ) === $filter_group;
				}
			);
		}

		if ( '' !== $filter_status ) {
			$filtered = array_filter(
				$filtered,
				static function ( array $entry ) use ( $filter_status ): bool {
					if ( 'active' === $filter_status ) {
						return ! empty( $entry['active'] );
					}
					return empty( $entry['active'] );
				}
			);
		}

		$filtered = array_values( $filtered );
		?>
		<div class="lsm-keywords-section">
			<h2><?php esc_html_e( 'Add New Keyword Mapping', 'link-smartly' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="lsm-add-form">
				<input type="hidden" name="action" value="lsm_add_keyword" />
				<?php wp_nonce_field( self::NONCE_KEYWORD, 'lsm_nonce' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="lsm-keyword"><?php esc_html_e( 'Keyword Phrase', 'link-smartly' ); ?></label>
						</th>
						<td>
							<input type="text"
								   id="lsm-keyword"
								   name="lsm_keyword"
								   class="regular-text"
								   required
								   placeholder="<?php esc_attr_e( 'e.g., contact us', 'link-smartly' ); ?>"
								   aria-required="true" />
							<p class="description"><?php esc_html_e( 'The exact word or phrase to match in your content. Use natural phrases your visitors would read (e.g., "contact us", "pricing plans"). Case does not matter.', 'link-smartly' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="lsm-url"><?php esc_html_e( 'Target URL', 'link-smartly' ); ?></label>
						</th>
						<td>
							<input type="url"
								   id="lsm-url"
								   name="lsm_url"
								   class="regular-text"
								   required
								   placeholder="<?php esc_attr_e( 'e.g., /contact/', 'link-smartly' ); ?>"
								   aria-required="true" />
							<p class="description"><?php esc_html_e( 'The page this keyword should link to. Use a relative path for pages on your own site (e.g., /contact/) or a full URL for external sites (e.g., https://example.com/page/). Tip: copy the URL from your browser address bar.', 'link-smartly' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="lsm-group"><?php esc_html_e( 'Group', 'link-smartly' ); ?></label>
						</th>
						<td>
							<input type="text"
								   id="lsm-group"
								   name="lsm_group"
								   class="regular-text"
								   placeholder="<?php esc_attr_e( 'e.g., Navigation, Products', 'link-smartly' ); ?>" />
							<p class="description"><?php esc_html_e( 'Optional. A label to organize your keywords (e.g., "Navigation", "Products", "Blog"). Leave empty if you don\'t need to group them. Groups only help you filter — they do not appear on your site.', 'link-smartly' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="lsm-synonyms"><?php esc_html_e( 'Synonyms', 'link-smartly' ); ?></label>
						</th>
						<td>
							<input type="text"
								   id="lsm-synonyms"
								   name="lsm_synonyms"
								   class="regular-text"
								   placeholder="<?php esc_attr_e( 'e.g., reach out, get in touch', 'link-smartly' ); ?>" />
							<p class="description"><?php esc_html_e( 'Optional. Other phrases that mean the same thing and should link to the same page. Separate with commas (e.g., "reach out, get in touch"). Leave empty if you only need the main keyword.', 'link-smartly' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Advanced Options', 'link-smartly' ); ?></th>
						<td>
							<fieldset>
								<label for="lsm-max-uses">
									<?php esc_html_e( 'Max uses:', 'link-smartly' ); ?>
									<input type="number"
										   id="lsm-max-uses"
										   name="lsm_max_uses"
										   value="0"
										   min="0"
										   class="small-text" />
								</label>
								<p class="description"><?php esc_html_e( 'How many times this keyword can be auto-linked across all your posts. Leave at 0 for unlimited (recommended for most users).', 'link-smartly' ); ?></p>

								<br />
								<label for="lsm-nofollow-kw"><?php esc_html_e( 'Nofollow:', 'link-smartly' ); ?></label>
								<select id="lsm-nofollow-kw" name="lsm_nofollow_kw">
									<option value="default"><?php esc_html_e( 'Use global setting (recommended)', 'link-smartly' ); ?></option>
									<option value="yes"><?php esc_html_e( 'Yes — tell search engines not to follow', 'link-smartly' ); ?></option>
									<option value="no"><?php esc_html_e( 'No — let search engines follow', 'link-smartly' ); ?></option>
								</select>

								<label for="lsm-new-tab-kw" class="lsm-kw-inline-label"><?php esc_html_e( 'New tab:', 'link-smartly' ); ?></label>
								<select id="lsm-new-tab-kw" name="lsm_new_tab_kw">
									<option value="default"><?php esc_html_e( 'Use global setting (recommended)', 'link-smartly' ); ?></option>
									<option value="yes"><?php esc_html_e( 'Yes — open in new tab', 'link-smartly' ); ?></option>
									<option value="no"><?php esc_html_e( 'No — open in same tab', 'link-smartly' ); ?></option>
								</select>
								<p class="description"><?php esc_html_e( 'Leave both on "Use global setting" unless you need this specific keyword to behave differently from your defaults in the Settings tab.', 'link-smartly' ); ?></p>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Schedule', 'link-smartly' ); ?></th>
						<td>
							<label for="lsm-start-date"><?php esc_html_e( 'From:', 'link-smartly' ); ?></label>
							<input type="date" id="lsm-start-date" name="lsm_start_date" value="" />
							<label for="lsm-end-date" class="lsm-kw-inline-label"><?php esc_html_e( 'Until:', 'link-smartly' ); ?></label>
							<input type="date" id="lsm-end-date" name="lsm_end_date" value="" />
							<p class="description"><?php esc_html_e( 'Optional. Set a date range to auto-link this keyword only during a specific period (e.g., a seasonal promotion). Leave both empty to keep the keyword active indefinitely — this is the best choice for most keywords.', 'link-smartly' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button( esc_html__( 'Add Keyword', 'link-smartly' ), 'primary', 'submit', true ); ?>
			</form>

			<hr />

			<h2>
				<?php esc_html_e( 'Keyword Mappings', 'link-smartly' ); ?>
				<span class="lsm-count">(<?php echo esc_html( (string) count( $keywords ) ); ?>)</span>
			</h2>

			<?php // Search and filter bar. ?>
			<div class="lsm-filter-bar">
				<form method="get" action="<?php echo esc_url( admin_url( 'options-general.php' ) ); ?>" class="lsm-search-form">
					<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>" />
					<input type="hidden" name="tab" value="keywords" />

					<input type="search"
						   name="s"
						   value="<?php echo esc_attr( $search_term ); ?>"
						   placeholder="<?php esc_attr_e( 'Search keywords…', 'link-smartly' ); ?>"
						   class="lsm-search-input" />

					<?php if ( ! empty( $groups ) ) : ?>
						<select name="group" class="lsm-filter-select lsm-filter-group">
							<option value=""><?php esc_html_e( 'All Groups', 'link-smartly' ); ?></option>
							<?php foreach ( $groups as $group ) : ?>
								<option value="<?php echo esc_attr( $group ); ?>" <?php selected( $filter_group, $group ); ?>>
									<?php echo esc_html( $group ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					<?php endif; ?>

					<select name="status" class="lsm-filter-select lsm-filter-status">
						<option value=""><?php esc_html_e( 'All Statuses', 'link-smartly' ); ?></option>
						<option value="active" <?php selected( $filter_status, 'active' ); ?>><?php esc_html_e( 'Active', 'link-smartly' ); ?></option>
						<option value="inactive" <?php selected( $filter_status, 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'link-smartly' ); ?></option>
					</select>

					<?php submit_button( esc_html__( 'Filter', 'link-smartly' ), 'secondary', 'submit', false ); ?>

					<?php if ( '' !== $search_term || '' !== $filter_group || '' !== $filter_status ) : ?>
						<a href="<?php echo esc_url( admin_url( 'options-general.php?page=' . self::PAGE_SLUG . '&tab=keywords' ) ); ?>" class="button lsm-clear-filters"><?php esc_html_e( 'Clear', 'link-smartly' ); ?></a>
					<?php endif; ?>
				</form>
			</div>

			<?php if ( empty( $filtered ) ) : ?>
				<p><?php esc_html_e( 'No keyword mappings found. Add your first keyword above or import from CSV.', 'link-smartly' ); ?></p>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="lsm-bulk-form">
					<input type="hidden" name="action" value="lsm_bulk_action" />
					<?php wp_nonce_field( self::NONCE_KEYWORD, 'lsm_nonce' ); ?>

					<div class="lsm-bulk-bar">
						<select name="lsm_bulk_action" class="lsm-bulk-select">
							<option value=""><?php esc_html_e( 'Bulk Actions', 'link-smartly' ); ?></option>
							<option value="activate"><?php esc_html_e( 'Activate', 'link-smartly' ); ?></option>
							<option value="deactivate"><?php esc_html_e( 'Deactivate', 'link-smartly' ); ?></option>
							<option value="delete"><?php esc_html_e( 'Delete', 'link-smartly' ); ?></option>
						</select>
						<button type="submit" class="button lsm-bulk-apply-btn"><?php esc_html_e( 'Apply', 'link-smartly' ); ?></button>
					</div>

				<div class="lsm-table-scroll">
					<table class="widefat striped lsm-keywords-table">
						<thead>
							<tr>
								<th scope="col" class="lsm-col-check"><input type="checkbox" id="lsm-select-all" /></th>
								<th scope="col" class="lsm-col-keyword lsm-sortable" data-orderby="keyword"><?php esc_html_e( 'Keyword', 'link-smartly' ); ?><span class="lsm-sort-arrow"></span></th>
								<th scope="col" class="lsm-col-url lsm-sortable" data-orderby="url"><?php esc_html_e( 'Target URL', 'link-smartly' ); ?><span class="lsm-sort-arrow"></span></th>
								<th scope="col" class="lsm-col-group lsm-sortable" data-orderby="group"><?php esc_html_e( 'Group', 'link-smartly' ); ?><span class="lsm-sort-arrow"></span></th>
								<th scope="col" class="lsm-col-status lsm-sortable" data-orderby="status"><?php esc_html_e( 'Status', 'link-smartly' ); ?><span class="lsm-sort-arrow"></span></th>
								<th scope="col" class="lsm-col-links lsm-sortable" data-orderby="link_count"><?php esc_html_e( 'Links', 'link-smartly' ); ?><span class="lsm-sort-arrow"></span></th>
								<th scope="col" class="lsm-col-actions"><?php esc_html_e( 'Actions', 'link-smartly' ); ?></th>
							</tr>
						</thead>
						<tbody class="lsm-keywords-tbody">
							<?php foreach ( $filtered as $entry ) : ?>
								<tr class="lsm-keyword-row" data-id="<?php echo esc_attr( $entry['id'] ); ?>">
									<td class="lsm-col-check">
										<input type="checkbox" name="lsm_bulk_ids[]" value="<?php echo esc_attr( $entry['id'] ); ?>" class="lsm-row-check" />
									</td>
									<td class="lsm-col-keyword">
										<span class="lsm-keyword-text"><?php echo esc_html( $entry['keyword'] ); ?></span>
										<input type="text"
											   class="lsm-edit-keyword regular-text"
											   value="<?php echo esc_attr( $entry['keyword'] ); ?>"
											   aria-label="<?php esc_attr_e( 'Edit keyword', 'link-smartly' ); ?>"
											   style="display:none;" />
										<?php if ( ! empty( $entry['synonyms'] ) ) : ?>
											<br /><span class="lsm-synonyms-label"><?php echo esc_html( $entry['synonyms'] ); ?></span>
										<?php endif; ?>
									</td>
									<td class="lsm-col-url">
										<span class="lsm-url-text"><?php echo esc_html( $entry['url'] ); ?></span>
										<input type="text"
											   class="lsm-edit-url regular-text"
											   value="<?php echo esc_attr( $entry['url'] ); ?>"
											   aria-label="<?php esc_attr_e( 'Edit URL', 'link-smartly' ); ?>"
											   style="display:none;" />
									</td>
									<td class="lsm-col-group">
										<?php if ( ! empty( $entry['group'] ) ) : ?>
											<span class="lsm-group-badge"><?php echo esc_html( $entry['group'] ); ?></span>
										<?php else : ?>
											<span class="lsm-no-group">&mdash;</span>
										<?php endif; ?>
									</td>
									<td class="lsm-col-status">
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="lsm-inline-form">
											<input type="hidden" name="action" value="lsm_toggle_keyword" />
											<input type="hidden" name="lsm_keyword_id" value="<?php echo esc_attr( $entry['id'] ); ?>" />
											<input type="hidden" name="lsm_active" value="<?php echo $entry['active'] ? '0' : '1'; ?>" />
											<?php wp_nonce_field( self::NONCE_KEYWORD, 'lsm_nonce' ); ?>
											<button type="submit"
													class="button lsm-toggle-btn <?php echo $entry['active'] ? 'lsm-active' : 'lsm-inactive'; ?>"
													aria-label="<?php echo $entry['active'] ? esc_attr__( 'Deactivate keyword', 'link-smartly' ) : esc_attr__( 'Activate keyword', 'link-smartly' ); ?>">
												<?php echo $entry['active'] ? esc_html__( 'Active', 'link-smartly' ) : esc_html__( 'Inactive', 'link-smartly' ); ?>
											</button>
										</form>
									</td>
									<td class="lsm-col-links">
										<?php echo esc_html( (string) ( $entry['link_count'] ?? 0 ) ); ?>
									</td>
									<td class="lsm-col-actions">
										<button type="button"
												class="button lsm-edit-btn"
												aria-label="<?php esc_attr_e( 'Edit this keyword mapping', 'link-smartly' ); ?>">
											<?php esc_html_e( 'Edit', 'link-smartly' ); ?>
										</button>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="lsm-inline-form lsm-edit-form" style="display:none;">
											<input type="hidden" name="action" value="lsm_edit_keyword" />
											<input type="hidden" name="lsm_keyword_id" value="<?php echo esc_attr( $entry['id'] ); ?>" />
											<input type="hidden" name="lsm_keyword" class="lsm-edit-keyword-hidden" value="" />
											<input type="hidden" name="lsm_url" class="lsm-edit-url-hidden" value="" />
											<input type="hidden" name="lsm_group" value="<?php echo esc_attr( $entry['group'] ?? '' ); ?>" />
											<input type="hidden" name="lsm_synonyms" value="<?php echo esc_attr( $entry['synonyms'] ?? '' ); ?>" />
											<input type="hidden" name="lsm_max_uses" value="<?php echo esc_attr( (string) ( $entry['max_uses'] ?? 0 ) ); ?>" />
											<input type="hidden" name="lsm_nofollow_kw" value="<?php echo esc_attr( $entry['nofollow'] ?? 'default' ); ?>" />
											<input type="hidden" name="lsm_new_tab_kw" value="<?php echo esc_attr( $entry['new_tab'] ?? 'default' ); ?>" />
											<input type="hidden" name="lsm_start_date" value="<?php echo esc_attr( $entry['start_date'] ?? '' ); ?>" />
											<input type="hidden" name="lsm_end_date" value="<?php echo esc_attr( $entry['end_date'] ?? '' ); ?>" />
											<?php wp_nonce_field( self::NONCE_KEYWORD, 'lsm_nonce' ); ?>
											<button type="submit"
													class="button button-primary lsm-save-edit-btn"
													aria-label="<?php esc_attr_e( 'Save changes', 'link-smartly' ); ?>">
												<?php esc_html_e( 'Save', 'link-smartly' ); ?>
											</button>
											<button type="button"
													class="button lsm-cancel-edit-btn"
													aria-label="<?php esc_attr_e( 'Cancel editing', 'link-smartly' ); ?>">
												<?php esc_html_e( 'Cancel', 'link-smartly' ); ?>
											</button>
										</form>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="lsm-inline-form lsm-delete-form">
											<input type="hidden" name="action" value="lsm_delete_keyword" />
											<input type="hidden" name="lsm_keyword_id" value="<?php echo esc_attr( $entry['id'] ); ?>" />
											<?php wp_nonce_field( self::NONCE_KEYWORD, 'lsm_nonce' ); ?>
											<button type="submit"
													class="button lsm-delete-btn"
													aria-label="<?php esc_attr_e( 'Delete this keyword mapping', 'link-smartly' ); ?>">
												<?php esc_html_e( 'Delete', 'link-smartly' ); ?>
											</button>
										</form>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				</form>

				<div class="lsm-table-footer">
					<div class="lsm-pagination"></div>
					<div class="lsm-per-page-wrap">
						<label for="lsm-per-page"><?php esc_html_e( 'Per page:', 'link-smartly' ); ?></label>
						<select id="lsm-per-page" class="lsm-per-page-select">
							<option value="25">25</option>
							<option value="50">50</option>
							<option value="100">100</option>
						</select>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render the analytics tab.
	 *
	 * @since 1.1.0
	 *
	 * @param array<int, array<string, mixed>> $keywords All keyword mappings.
	 * @return void
	 */
	private function render_analytics_tab( array $keywords ): void {
		$total_links  = 0;
		$linked_posts = $this->keywords->get_all_linked_posts();

		foreach ( $keywords as $entry ) {
			$total_links += (int) ( $entry['link_count'] ?? 0 );
		}

		// Count unique posts across all keywords.
		$unique_posts = array();

		foreach ( $linked_posts as $posts ) {
			if ( is_array( $posts ) ) {
				foreach ( array_keys( $posts ) as $pid ) {
					$unique_posts[ $pid ] = true;
				}
			}
		}

		// Sort by link_count descending.
		usort(
			$keywords,
			static function ( array $a, array $b ): int {
				return ( $b['link_count'] ?? 0 ) <=> ( $a['link_count'] ?? 0 );
			}
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$scanned = isset( $_GET['scanned'] ) ? absint( $_GET['scanned'] ) : -1;
		?>
		<div class="lsm-analytics-section">
			<h2><?php esc_html_e( 'Link Analytics', 'link-smartly' ); ?></h2>

			<?php if ( $scanned >= 0 ) : ?>
				<div class="notice notice-success is-dismissible">
					<p>
						<?php
						printf(
							/* translators: %d: number of posts scanned */
							esc_html__( 'Scan complete. %d posts scanned for keyword matches.', 'link-smartly' ),
							intval( $scanned )
						);
						?>
					</p>
				</div>
			<?php endif; ?>

			<div class="lsm-analytics-summary">
				<div class="lsm-stat-card">
					<span class="lsm-stat-number"><?php echo esc_html( (string) count( $keywords ) ); ?></span>
					<span class="lsm-stat-label"><?php esc_html_e( 'Total Keywords', 'link-smartly' ); ?></span>
				</div>
				<div class="lsm-stat-card">
					<span class="lsm-stat-number"><?php echo esc_html( (string) count( array_filter( $keywords, static fn( array $e ): bool => ! empty( $e['active'] ) ) ) ); ?></span>
					<span class="lsm-stat-label"><?php esc_html_e( 'Active Keywords', 'link-smartly' ); ?></span>
				</div>
				<div class="lsm-stat-card">
					<span class="lsm-stat-number"><?php echo esc_html( (string) $total_links ); ?></span>
					<span class="lsm-stat-label"><?php esc_html_e( 'Total Links Inserted', 'link-smartly' ); ?></span>
				</div>
				<div class="lsm-stat-card">
					<span class="lsm-stat-number"><?php echo esc_html( (string) count( $unique_posts ) ); ?></span>
					<span class="lsm-stat-label"><?php esc_html_e( 'Posts With Links', 'link-smartly' ); ?></span>
				</div>
			</div>

			<div class="lsm-analytics-actions">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="lsm-inline-form">
					<input type="hidden" name="action" value="lsm_scan_posts" />
					<?php wp_nonce_field( 'lsm_scan_posts', 'lsm_nonce' ); ?>
					<button type="submit" class="button button-primary" onclick="return window.confirm('<?php echo esc_js( __( 'Scan all published posts to discover where keywords are used? This may take a moment on large sites.', 'link-smartly' ) ); ?>');">
						<?php esc_html_e( 'Scan All Posts', 'link-smartly' ); ?>
					</button>
				</form>

				<?php if ( ! empty( $keywords ) ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="lsm-inline-form">
						<input type="hidden" name="action" value="lsm_reset_stats" />
						<?php wp_nonce_field( 'lsm_reset_stats', 'lsm_nonce' ); ?>
						<button type="submit" class="button" onclick="return window.confirm('<?php echo esc_js( __( 'Reset all link counts and post mappings to zero?', 'link-smartly' ) ); ?>');">
							<?php esc_html_e( 'Reset All Counts', 'link-smartly' ); ?>
						</button>
					</form>
				<?php endif; ?>
			</div>

			<?php if ( ! empty( $keywords ) ) : ?>
				<h3><?php esc_html_e( 'Keywords by Performance', 'link-smartly' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Click a keyword row to see which posts it appears in.', 'link-smartly' ); ?></p>
				<table class="widefat striped lsm-analytics-table">
					<thead>
						<tr>
							<th scope="col">#</th>
							<th scope="col"><?php esc_html_e( 'Keyword', 'link-smartly' ); ?></th>
							<th scope="col"><?php esc_html_e( 'URL', 'link-smartly' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Group', 'link-smartly' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Links', 'link-smartly' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Posts', 'link-smartly' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Max Uses', 'link-smartly' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Status', 'link-smartly' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $keywords as $index => $entry ) :
							$kw_posts = $linked_posts[ $entry['id'] ] ?? array();
							$post_count = is_array( $kw_posts ) ? count( $kw_posts ) : 0;
						?>
							<tr class="lsm-analytics-row <?php echo $post_count > 0 ? 'lsm-has-posts' : ''; ?>" data-keyword-id="<?php echo esc_attr( $entry['id'] ); ?>">
								<td><?php echo esc_html( (string) ( $index + 1 ) ); ?></td>
								<td><?php echo esc_html( $entry['keyword'] ); ?></td>
								<td><code><?php echo esc_html( $entry['url'] ); ?></code></td>
								<td><?php echo esc_html( $entry['group'] ?? '' ); ?></td>
								<td><strong><?php echo esc_html( (string) ( $entry['link_count'] ?? 0 ) ); ?></strong></td>
								<td>
									<?php if ( $post_count > 0 ) : ?>
										<span class="lsm-post-count-badge"><?php echo esc_html( (string) $post_count ); ?></span>
									<?php else : ?>
										<span class="lsm-no-posts">&mdash;</span>
									<?php endif; ?>
								</td>
								<td><?php echo 0 === (int) ( $entry['max_uses'] ?? 0 ) ? esc_html__( 'Unlimited', 'link-smartly' ) : esc_html( (string) $entry['max_uses'] ); ?></td>
								<td>
									<span class="lsm-status-dot <?php echo ! empty( $entry['active'] ) ? 'lsm-dot-active' : 'lsm-dot-inactive'; ?>"></span>
									<?php echo ! empty( $entry['active'] ) ? esc_html__( 'Active', 'link-smartly' ) : esc_html__( 'Inactive', 'link-smartly' ); ?>
								</td>
							</tr>
							<?php if ( $post_count > 0 ) : ?>
								<tr class="lsm-where-used-row" data-parent="<?php echo esc_attr( $entry['id'] ); ?>" style="display: none;">
									<td colspan="8">
										<div class="lsm-where-used-list">
											<strong><?php esc_html_e( 'Used in:', 'link-smartly' ); ?></strong>
											<ul>
												<?php foreach ( $kw_posts as $pid => $title ) : ?>
													<li>
														<a href="<?php echo esc_url( get_edit_post_link( (int) $pid ) ?? '' ); ?>">
															<?php echo esc_html( $title ); ?>
														</a>
														<span class="lsm-post-id">(#<?php echo esc_html( (string) $pid ); ?>)</span>
														<a href="<?php echo esc_url( (string) get_permalink( (int) $pid ) ); ?>" target="_blank" rel="noopener" class="lsm-view-link" aria-label="<?php esc_attr_e( 'View post', 'link-smartly' ); ?>">&#8599;</a>
													</li>
												<?php endforeach; ?>
											</ul>
										</div>
									</td>
								</tr>
							<?php endif; ?>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render the settings tab.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>              $settings   Current plugin settings.
	 * @param array<string, WP_Post_Type|object> $post_types Available post types.
	 * @return void
	 */
	private function render_settings_tab( array $settings, array $post_types ): void {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="lsm_save_settings" />
			<?php wp_nonce_field( self::NONCE_SETTINGS, 'lsm_nonce' ); ?>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable Auto-Linking', 'link-smartly' ); ?></th>
					<td>
						<label for="lsm-enabled">
							<input type="checkbox"
								   id="lsm-enabled"
								   name="lsm_enabled"
								   value="1"
								   <?php checked( $settings['enabled'] ); ?> />
							<?php esc_html_e( 'Automatically insert internal links into content', 'link-smartly' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Master switch. When checked, the plugin scans your posts and adds links based on your keyword mappings. Uncheck to pause all auto-linking without losing your settings. Default: On.', 'link-smartly' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="lsm-max-links"><?php esc_html_e( 'Max Links Per Post', 'link-smartly' ); ?></label>
					</th>
					<td>
						<input type="number"
							   id="lsm-max-links"
							   name="lsm_max_links"
							   value="<?php echo esc_attr( (string) $settings['max_links_per_post'] ); ?>"
							   min="1"
							   max="50"
							   step="1"
							   class="small-text" />
						<p class="description"><?php esc_html_e( 'How many auto-links can appear in a single post. Too many links look spammy to readers and search engines. Recommended: 3 for short posts, up to 5 for long-form content (2,000+ words). Default: 3.', 'link-smartly' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="lsm-min-words"><?php esc_html_e( 'Minimum Content Words', 'link-smartly' ); ?></label>
					</th>
					<td>
						<input type="number"
							   id="lsm-min-words"
							   name="lsm_min_words"
							   value="<?php echo esc_attr( (string) $settings['min_content_words'] ); ?>"
							   min="0"
							   max="5000"
							   step="1"
							   class="small-text" />
						<p class="description"><?php esc_html_e( 'Posts shorter than this word count will not get any auto-links. Short posts with too many links look unnatural. Set to 0 to allow links in all posts regardless of length. Default: 300 words.', 'link-smartly' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Post Types', 'link-smartly' ); ?></th>
					<td>
						<fieldset>
							<legend class="screen-reader-text"><span><?php esc_html_e( 'Post Types', 'link-smartly' ); ?></span></legend>
							<?php foreach ( $post_types as $pt ) : ?>
								<label>
									<input type="checkbox"
										   name="lsm_post_types[]"
										   value="<?php echo esc_attr( $pt->name ); ?>"
										   <?php checked( in_array( $pt->name, (array) $settings['post_types'], true ) ); ?> />
									<?php echo esc_html( $pt->labels->name ); ?>
								</label><br />
							<?php endforeach; ?>
						</fieldset>
						<p class="description"><?php esc_html_e( 'Choose which content types get auto-links. Most sites only need Posts and Pages. Default: Posts and Pages.', 'link-smartly' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="lsm-link-class"><?php esc_html_e( 'Link CSS Class', 'link-smartly' ); ?></label>
					</th>
					<td>
						<input type="text"
							   id="lsm-link-class"
							   name="lsm_link_class"
							   value="<?php echo esc_attr( $settings['link_class'] ); ?>"
							   class="regular-text" />
						<p class="description"><?php esc_html_e( 'A CSS class name added to every auto-generated link. Useful if you want to style them differently or track clicks in analytics. Leave the default unless you know what CSS classes are. Default: lsm-auto-link.', 'link-smartly' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Link Attributes', 'link-smartly' ); ?></th>
					<td>
						<fieldset>
							<legend class="screen-reader-text"><span><?php esc_html_e( 'Link Attributes', 'link-smartly' ); ?></span></legend>
							<label>
								<input type="checkbox"
									   name="lsm_title_attr"
									   value="1"
									   <?php checked( $settings['add_title_attr'] ); ?> />
								<?php esc_html_e( 'Add title attribute to links', 'link-smartly' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Shows a tooltip when visitors hover over a link. Helpful for accessibility. Default: On.', 'link-smartly' ); ?></p>
							<br />
							<label>
								<input type="checkbox"
									   name="lsm_nofollow"
									   value="1"
									   <?php checked( $settings['nofollow'] ); ?> />
								<?php esc_html_e( 'Add rel="nofollow" to links', 'link-smartly' ); ?>
							</label>
							<p class="description lsm-desc-warning"><?php esc_html_e( 'Tells search engines not to pass SEO value through these links. Do NOT check this for internal links — it blocks link equity flow, which is the whole point of internal linking. Only check this if all your keyword links go to external sites. Default: Off.', 'link-smartly' ); ?></p>
							<br />
							<label>
								<input type="checkbox"
									   name="lsm_new_tab"
									   value="1"
									   <?php checked( $settings['new_tab'] ); ?> />
								<?php esc_html_e( 'Open links in a new tab', 'link-smartly' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Internal links usually should NOT open in a new tab — it can annoy readers. Only enable this if your links go to external sites. Default: Off.', 'link-smartly' ); ?></p>
						</fieldset>
					</td>
				</tr>
			</table>

			<?php submit_button(); ?>
		</form>
		<?php
	}

	/**
	 * Render the import/export tab.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function render_import_export_tab(): void {
		?>
		<div class="lsm-import-export-section">
			<h2><?php esc_html_e( 'Export Keywords', 'link-smartly' ); ?></h2>
			<p><?php esc_html_e( 'Download all your keyword mappings as a CSV file. You can open it in Excel or Google Sheets, or use it as a backup before making bulk changes.', 'link-smartly' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="lsm_export_csv" />
				<?php wp_nonce_field( 'lsm_csv_action', 'lsm_nonce' ); ?>
				<?php submit_button( esc_html__( 'Export CSV', 'link-smartly' ), 'secondary', 'submit', true ); ?>
			</form>

			<hr />

			<h2><?php esc_html_e( 'Import Keywords', 'link-smartly' ); ?></h2>
			<p><?php esc_html_e( 'Upload a CSV file to add keywords in bulk. Your CSV file must have at least two columns: keyword and url. Additional optional columns: active (1 or 0), group, synonyms, nofollow, new_tab, max_uses, start_date, end_date.', 'link-smartly' ); ?></p>
			<form method="post"
				  action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
				  enctype="multipart/form-data">
				<input type="hidden" name="action" value="lsm_import_csv" />
				<?php wp_nonce_field( 'lsm_csv_action', 'lsm_nonce' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="lsm-csv-file"><?php esc_html_e( 'CSV File', 'link-smartly' ); ?></label>
						</th>
						<td>
							<input type="file"
								   id="lsm-csv-file"
								   name="lsm_csv_file"
								   accept=".csv"
								   required
								   aria-required="true" />
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Import Mode', 'link-smartly' ); ?></th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><span><?php esc_html_e( 'Import Mode', 'link-smartly' ); ?></span></legend>
								<label>
									<input type="radio" name="lsm_import_mode" value="append" checked="checked" />
									<?php esc_html_e( 'Append — add new keywords to your existing list (safe, recommended)', 'link-smartly' ); ?>
								</label><br />
								<label>
									<input type="radio" name="lsm_import_mode" value="replace" />
									<?php esc_html_e( 'Replace — delete ALL existing keywords first, then import (use with caution!)', 'link-smartly' ); ?>
								</label>
							</fieldset>
						</td>
					</tr>
				</table>

				<?php submit_button( esc_html__( 'Import CSV', 'link-smartly' ), 'primary', 'submit', true ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the preview/test tab.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function render_preview_tab(): void {
		$settings   = Lsm_Settings::get_all();
		$post_types = (array) ( $settings['post_types'] ?? array( 'post', 'page' ) );

		$posts = get_posts(
			array(
				'post_type'      => $post_types,
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);
		?>
		<div class="lsm-preview-section">
			<h2><?php esc_html_e( 'Preview Auto-Links', 'link-smartly' ); ?></h2>
			<p class="lsm-preview-description">
				<?php esc_html_e( 'Select a post or page to see which auto-links would be inserted. This is a dry-run — no changes are saved to your content.', 'link-smartly' ); ?>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="lsm-preview-form">
				<input type="hidden" name="action" value="lsm_preview" />
				<?php wp_nonce_field( 'lsm_preview_action', 'lsm_nonce' ); ?>

				<div class="lsm-preview-controls">
					<label for="lsm-preview-post-id" class="lsm-preview-label">
						<?php esc_html_e( 'Choose a post:', 'link-smartly' ); ?>
					</label>

					<?php if ( ! empty( $posts ) ) : ?>
						<select id="lsm-preview-post-id"
								name="lsm_post_id"
								class="lsm-preview-select"
								required
								aria-required="true">
							<option value=""><?php esc_html_e( '— Select a post or page —', 'link-smartly' ); ?></option>
							<?php
							$grouped = array();
							foreach ( $posts as $p ) {
								$type_obj   = get_post_type_object( $p->post_type );
								$type_label = $type_obj ? $type_obj->labels->singular_name : $p->post_type;
								$grouped[ $type_label ][] = $p;
							}
							ksort( $grouped );

							foreach ( $grouped as $type_label => $group_posts ) :
								?>
								<optgroup label="<?php echo esc_attr( $type_label ); ?>">
									<?php foreach ( $group_posts as $p ) : ?>
										<option value="<?php echo esc_attr( (string) $p->ID ); ?>">
											<?php echo esc_html( $p->post_title ); ?> (ID: <?php echo esc_html( (string) $p->ID ); ?>)
										</option>
									<?php endforeach; ?>
								</optgroup>
							<?php endforeach; ?>
						</select>
					<?php else : ?>
						<p class="description">
							<?php esc_html_e( 'No published posts found for the configured post types.', 'link-smartly' ); ?>
						</p>
					<?php endif; ?>

					<?php if ( ! empty( $posts ) ) : ?>
						<?php submit_button( esc_html__( 'Preview Links', 'link-smartly' ), 'primary', 'submit', false ); ?>
					<?php endif; ?>
				</div>
			</form>

			<?php
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( isset( $_GET['preview_results'] ) ) {
				$this->render_preview_results();
			}
			?>
		</div>
		<?php
	}

	/**
	 * Render preview results from transient data.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function render_preview_results(): void {
		$transient_key = 'lsm_preview_results_' . get_current_user_id();
		$results       = get_transient( $transient_key );
		delete_transient( $transient_key );

		if ( ! is_array( $results ) ) {
			return;
		}

		$links = $results['links'] ?? array();
		$title = $results['title'] ?? '';
		?>
		<div class="lsm-preview-results">
			<h3>
				<span class="dashicons dashicons-visibility lsm-preview-results-icon"></span>
				<?php
				printf(
					/* translators: %s: Post title. */
					esc_html__( 'Preview Results for "%s"', 'link-smartly' ),
					esc_html( $title )
				);
				?>
			</h3>

			<?php if ( empty( $links ) ) : ?>
				<div class="lsm-preview-empty">
					<span class="dashicons dashicons-info-outline lsm-preview-empty-icon"></span>
					<p><strong><?php esc_html_e( 'No auto-links would be inserted for this content.', 'link-smartly' ); ?></strong></p>
					<p><?php esc_html_e( 'Possible reasons:', 'link-smartly' ); ?></p>
					<ul>
						<li><?php esc_html_e( 'The content is shorter than the minimum word count setting.', 'link-smartly' ); ?></li>
						<li><?php esc_html_e( 'None of your keywords appear in the content.', 'link-smartly' ); ?></li>
						<li><?php esc_html_e( 'Matching keywords are inside headings, existing links, or code blocks.', 'link-smartly' ); ?></li>
						<li><?php esc_html_e( 'This post is excluded via the post-level setting.', 'link-smartly' ); ?></li>
					</ul>
				</div>
			<?php else : ?>
				<div class="lsm-preview-summary">
					<span class="dashicons dashicons-yes-alt lsm-preview-summary-icon"></span>
					<?php
					printf(
						/* translators: %d: Number of links that would be inserted. */
						esc_html( _n(
							'%d auto-link would be inserted into this content.',
							'%d auto-links would be inserted into this content.',
							count( $links ),
							'link-smartly'
						) ),
						count( $links )
					);
					?>
				</div>

				<table class="widefat striped lsm-preview-table">
					<thead>
						<tr>
							<th scope="col" class="lsm-col-num">#</th>
							<th scope="col"><?php esc_html_e( 'Keyword Found', 'link-smartly' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Would Link To', 'link-smartly' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $links as $index => $link ) : ?>
							<tr>
								<td class="lsm-col-num"><?php echo esc_html( (string) ( $index + 1 ) ); ?></td>
								<td><strong><?php echo esc_html( $link['keyword'] ); ?></strong></td>
								<td>
									<code><?php echo esc_html( $link['url'] ); ?></code>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}
}
