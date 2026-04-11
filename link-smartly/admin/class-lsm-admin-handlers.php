<?php
/**
 * Admin form handlers for keyword and settings CRUD.
 *
 * @package LinkSmartly
 * @since   1.3.0
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles all admin-post.php form submissions for settings,
 * keyword CRUD, bulk actions, undo, stats reset, and post scanning.
 *
 * @since 1.3.0
 */
class Lsm_Admin_Handlers {

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
	 * Register admin-post hooks.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_post_lsm_save_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_lsm_add_keyword', array( $this, 'handle_add_keyword' ) );
		add_action( 'admin_post_lsm_delete_keyword', array( $this, 'handle_delete_keyword' ) );
		add_action( 'admin_post_lsm_toggle_keyword', array( $this, 'handle_toggle_keyword' ) );
		add_action( 'admin_post_lsm_edit_keyword', array( $this, 'handle_edit_keyword' ) );
		add_action( 'admin_post_lsm_bulk_action', array( $this, 'handle_bulk_action' ) );
		add_action( 'admin_post_lsm_undo', array( $this, 'handle_undo' ) );
		add_action( 'admin_post_lsm_reset_stats', array( $this, 'handle_reset_stats' ) );
		add_action( 'admin_post_lsm_scan_posts', array( $this, 'handle_scan_posts' ) );
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

		check_admin_referer( Lsm_Admin::NONCE_SETTINGS, 'lsm_nonce' );

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
			'cron_health_check'  => ! empty( $_POST['lsm_cron_health_check'] ),
			'email_digest'       => ! empty( $_POST['lsm_email_digest'] ),
		);

		Lsm_Settings::save( $input );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => Lsm_Admin::PAGE_SLUG,
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

		check_admin_referer( Lsm_Admin::NONCE_KEYWORD, 'lsm_nonce' );

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
						'page'  => Lsm_Admin::PAGE_SLUG,
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
						'page'  => Lsm_Admin::PAGE_SLUG,
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
					'page'  => Lsm_Admin::PAGE_SLUG,
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

		check_admin_referer( Lsm_Admin::NONCE_KEYWORD, 'lsm_nonce' );

		$id = isset( $_POST['lsm_keyword_id'] )
			? sanitize_text_field( wp_unslash( $_POST['lsm_keyword_id'] ) )
			: '';

		if ( '' !== $id ) {
			// Store entry for undo before deleting.
			$all = $this->keywords->get_all();

			foreach ( $all as $entry ) {
				if ( $entry['id'] === $id ) {
					Lsm_Cache::set( 'undo_' . get_current_user_id(), array( $entry ), 300 );
					break;
				}
			}

			$this->keywords->delete( $id );
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => Lsm_Admin::PAGE_SLUG,
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

		check_admin_referer( Lsm_Admin::NONCE_KEYWORD, 'lsm_nonce' );

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
					'page'    => Lsm_Admin::PAGE_SLUG,
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

		check_admin_referer( Lsm_Admin::NONCE_KEYWORD, 'lsm_nonce' );

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
					'page'   => Lsm_Admin::PAGE_SLUG,
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

		check_admin_referer( Lsm_Admin::NONCE_KEYWORD, 'lsm_nonce' );

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
						'page' => Lsm_Admin::PAGE_SLUG,
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
					'page'  => Lsm_Admin::PAGE_SLUG,
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
					'page'    => Lsm_Admin::PAGE_SLUG,
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

		$linker      = new Lsm_Linker( $this->keywords, $settings );
		$keyword_map = $this->keywords->get_active();
		$batch_size  = 200;

		if ( empty( $keyword_map ) || empty( $allowed_types ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'    => Lsm_Admin::PAGE_SLUG,
						'tab'     => 'analytics',
						'scanned' => '0',
					),
					admin_url( 'options-general.php' )
				)
			);
			exit;
		}

		$linked_map = array();
		$scanned    = 0;
		$page       = 1;

		do {
			$query = new WP_Query(
				array(
					'post_type'              => $allowed_types,
					'post_status'            => 'publish',
					'posts_per_page'         => $batch_size,
					'paged'                  => $page,
					'fields'                 => 'ids',
					'orderby'                => 'ID',
					'order'                  => 'ASC',
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				)
			);

			foreach ( $query->posts as $post_id ) {
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

			++$page;
		} while ( count( $query->posts ) === $batch_size );

		wp_reset_postdata();

		$this->keywords->save_linked_posts( $linked_map );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => Lsm_Admin::PAGE_SLUG,
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

		$user_id   = get_current_user_id();
		$cache_key = 'undo_' . $user_id;
		$undo_data = Lsm_Cache::get( $cache_key );
		Lsm_Cache::delete( $cache_key );

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
					'page'  => Lsm_Admin::PAGE_SLUG,
					'tab'   => 'keywords',
					$notice => '1',
				),
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}
}
