<?php
/**
 * AJAX handlers for keyword CRUD operations.
 *
 * Provides non-reloading keyword management. The existing admin-post.php
 * form handlers remain as a no-JS fallback.
 *
 * @package LinkSmartly
 * @since   1.2.0
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers wp_ajax_ handlers for keyword CRUD, toggle, bulk actions,
 * search/filter, and pagination.
 *
 * @since 1.2.0
 */
class Lsm_Ajax {

	/**
	 * AJAX nonce action name.
	 *
	 * @since 1.2.0
	 * @var string
	 */
	const NONCE_ACTION = 'lsm_ajax';

	/**
	 * Keywords manager instance.
	 *
	 * @since 1.2.0
	 * @var Lsm_Keywords
	 */
	private Lsm_Keywords $keywords;

	/**
	 * Constructor.
	 *
	 * @since 1.2.0
	 *
	 * @param Lsm_Keywords $keywords Keywords manager.
	 */
	public function __construct( Lsm_Keywords $keywords ) {
		$this->keywords = $keywords;
	}

	/**
	 * Register AJAX hooks.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_ajax_lsm_add_keyword', array( $this, 'handle_add' ) );
		add_action( 'wp_ajax_lsm_edit_keyword', array( $this, 'handle_edit' ) );
		add_action( 'wp_ajax_lsm_delete_keyword', array( $this, 'handle_delete' ) );
		add_action( 'wp_ajax_lsm_toggle_keyword', array( $this, 'handle_toggle' ) );
		add_action( 'wp_ajax_lsm_bulk_action', array( $this, 'handle_bulk' ) );
		add_action( 'wp_ajax_lsm_fetch_keywords', array( $this, 'handle_fetch' ) );
	}

	/**
	 * Verify AJAX request nonce and user capability.
	 *
	 * Sends a JSON error and terminates if invalid.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	private function verify_request(): void {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Security check failed. Please reload the page.', 'link-smartly' ) ),
				403
			);
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Unauthorized access.', 'link-smartly' ) ),
				403
			);
		}
	}

	/**
	 * Handle adding a keyword via AJAX.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function handle_add(): void {
		$this->verify_request();

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_request().
		$keyword = isset( $_POST['keyword'] )
			? sanitize_text_field( wp_unslash( $_POST['keyword'] ) )
			: '';
		$url     = isset( $_POST['url'] )
			? esc_url_raw( wp_unslash( $_POST['url'] ) )
			: '';

		if ( '' === $keyword || '' === $url ) {
			wp_send_json_error(
				array( 'message' => __( 'Both keyword and URL are required.', 'link-smartly' ) ),
				400
			);
		}

		$duplicates = $this->keywords->find_duplicates( $keyword );

		if ( ! empty( $duplicates ) ) {
			wp_send_json_error(
				array( 'message' => __( 'This keyword already exists. Duplicate keywords are not allowed.', 'link-smartly' ) ),
				409
			);
		}

		$group      = isset( $_POST['group'] ) ? sanitize_text_field( wp_unslash( $_POST['group'] ) ) : '';
		$synonyms   = isset( $_POST['synonyms'] ) ? sanitize_text_field( wp_unslash( $_POST['synonyms'] ) ) : '';
		$max_uses   = isset( $_POST['max_uses'] ) ? absint( wp_unslash( $_POST['max_uses'] ) ) : 0;
		$nofollow   = isset( $_POST['nofollow_kw'] ) ? sanitize_key( wp_unslash( $_POST['nofollow_kw'] ) ) : 'default';
		$new_tab    = isset( $_POST['new_tab_kw'] ) ? sanitize_key( wp_unslash( $_POST['new_tab_kw'] ) ) : 'default';
		$start_date = isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : '';
		$end_date   = isset( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$entry = $this->keywords->sanitize_entry(
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

		$all   = $this->keywords->get_all();
		$all[] = $entry;
		$this->keywords->save_all( $all );

		wp_send_json_success(
			array(
				'message' => __( 'Keyword mapping added.', 'link-smartly' ),
				'entry'   => $entry,
			)
		);
	}

	/**
	 * Handle editing a keyword via AJAX.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function handle_edit(): void {
		$this->verify_request();

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_request().
		$id      = isset( $_POST['id'] )
			? sanitize_text_field( wp_unslash( $_POST['id'] ) )
			: '';
		$keyword = isset( $_POST['keyword'] )
			? sanitize_text_field( wp_unslash( $_POST['keyword'] ) )
			: '';
		$url     = isset( $_POST['url'] )
			? esc_url_raw( wp_unslash( $_POST['url'] ) )
			: '';

		if ( '' === $id || '' === $keyword || '' === $url ) {
			wp_send_json_error(
				array( 'message' => __( 'ID, keyword, and URL are required.', 'link-smartly' ) ),
				400
			);
		}

		$duplicates = $this->keywords->find_duplicates( $keyword, $id );

		if ( ! empty( $duplicates ) ) {
			wp_send_json_error(
				array( 'message' => __( 'This keyword already exists. Duplicate keywords are not allowed.', 'link-smartly' ) ),
				409
			);
		}

		$data = array(
			'keyword'    => $keyword,
			'url'        => $url,
			'group'      => isset( $_POST['group'] ) ? sanitize_text_field( wp_unslash( $_POST['group'] ) ) : '',
			'synonyms'   => isset( $_POST['synonyms'] ) ? sanitize_text_field( wp_unslash( $_POST['synonyms'] ) ) : '',
			'max_uses'   => isset( $_POST['max_uses'] ) ? absint( wp_unslash( $_POST['max_uses'] ) ) : 0,
			'nofollow'   => isset( $_POST['nofollow_kw'] ) ? sanitize_key( wp_unslash( $_POST['nofollow_kw'] ) ) : 'default',
			'new_tab'    => isset( $_POST['new_tab_kw'] ) ? sanitize_key( wp_unslash( $_POST['new_tab_kw'] ) ) : 'default',
			'start_date' => isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : '',
			'end_date'   => isset( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) : '',
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( ! $this->keywords->update( $id, $data ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Keyword not found.', 'link-smartly' ) ),
				404
			);
		}

		wp_send_json_success(
			array( 'message' => __( 'Keyword mapping updated.', 'link-smartly' ) )
		);
	}

	/**
	 * Handle deleting a keyword via AJAX.
	 *
	 * Stores undo data for 5 minutes.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function handle_delete(): void {
		$this->verify_request();

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_request().
		$id = isset( $_POST['id'] )
			? sanitize_text_field( wp_unslash( $_POST['id'] ) )
			: '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( '' === $id ) {
			wp_send_json_error(
				array( 'message' => __( 'Keyword ID is required.', 'link-smartly' ) ),
				400
			);
		}

		// Store entry for undo before deleting.
		$all = $this->keywords->get_all();

		foreach ( $all as $entry ) {
			if ( $entry['id'] === $id ) {
				set_transient( 'lsm_undo_' . get_current_user_id(), array( $entry ), 300 );
				break;
			}
		}

		if ( ! $this->keywords->delete( $id ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Keyword not found.', 'link-smartly' ) ),
				404
			);
		}

		wp_send_json_success(
			array( 'message' => __( 'Keyword mapping deleted.', 'link-smartly' ) )
		);
	}

	/**
	 * Handle toggling a keyword active/inactive via AJAX.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function handle_toggle(): void {
		$this->verify_request();

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_request().
		$id     = isset( $_POST['id'] )
			? sanitize_text_field( wp_unslash( $_POST['id'] ) )
			: '';
		$active = ! empty( $_POST['active'] );
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( '' === $id ) {
			wp_send_json_error(
				array( 'message' => __( 'Keyword ID is required.', 'link-smartly' ) ),
				400
			);
		}

		if ( ! $this->keywords->update( $id, array( 'active' => $active ) ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Keyword not found.', 'link-smartly' ) ),
				404
			);
		}

		wp_send_json_success(
			array( 'message' => __( 'Keyword mapping updated.', 'link-smartly' ) )
		);
	}

	/**
	 * Handle bulk actions (activate, deactivate, delete) via AJAX.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function handle_bulk(): void {
		$this->verify_request();

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_request().
		$action = isset( $_POST['bulk_action'] )
			? sanitize_key( wp_unslash( $_POST['bulk_action'] ) )
			: '';
		$ids    = isset( $_POST['ids'] ) && is_array( $_POST['ids'] )
			? array_map( 'sanitize_text_field', wp_unslash( $_POST['ids'] ) )
			: array();
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( '' === $action || empty( $ids ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Action and keyword IDs are required.', 'link-smartly' ) ),
				400
			);
		}

		if ( ! in_array( $action, array( 'activate', 'deactivate', 'delete' ), true ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Invalid bulk action.', 'link-smartly' ) ),
				400
			);
		}

		$all   = $this->keywords->get_all();
		$count = 0;

		// Store entries for undo before deleting.
		if ( 'delete' === $action ) {
			$undo_entries = array();

			foreach ( $all as $entry ) {
				if ( in_array( $entry['id'], $ids, true ) ) {
					$undo_entries[] = $entry;
				}
			}

			if ( ! empty( $undo_entries ) ) {
				set_transient( 'lsm_undo_' . get_current_user_id(), $undo_entries, 300 );
			}
		}

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

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %d: number of keywords affected */
					_n( '%d keyword updated.', '%d keywords updated.', $count, 'link-smartly' ),
					$count
				),
				'count'   => $count,
			)
		);
	}

	/**
	 * Handle fetching keywords with pagination, search, filter, and sorting via AJAX.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function handle_fetch(): void {
		$this->verify_request();

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_request().
		$page     = isset( $_POST['page'] ) ? absint( wp_unslash( $_POST['page'] ) ) : 1;
		$per_page = isset( $_POST['per_page'] ) ? absint( wp_unslash( $_POST['per_page'] ) ) : 25;
		$search   = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
		$group    = isset( $_POST['group'] ) ? sanitize_text_field( wp_unslash( $_POST['group'] ) ) : '';
		$status   = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
		$orderby  = isset( $_POST['orderby'] ) ? sanitize_key( wp_unslash( $_POST['orderby'] ) ) : 'keyword';
		$order    = isset( $_POST['order'] ) ? sanitize_key( wp_unslash( $_POST['order'] ) ) : 'asc';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		// Clamp per_page.
		if ( $per_page < 1 ) {
			$per_page = 25;
		}

		if ( $per_page > 100 ) {
			$per_page = 100;
		}

		if ( $page < 1 ) {
			$page = 1;
		}

		$all = $this->keywords->get_all();

		// Apply search filter.
		if ( '' !== $search ) {
			$all = array_filter(
				$all,
				static function ( array $entry ) use ( $search ): bool {
					return false !== mb_stripos( $entry['keyword'], $search )
						|| false !== mb_stripos( $entry['url'], $search )
						|| false !== mb_stripos( $entry['group'] ?? '', $search )
						|| false !== mb_stripos( $entry['synonyms'] ?? '', $search );
				}
			);
		}

		// Apply group filter.
		if ( '' !== $group ) {
			$all = array_filter(
				$all,
				static function ( array $entry ) use ( $group ): bool {
					return ( $entry['group'] ?? '' ) === $group;
				}
			);
		}

		// Apply status filter.
		if ( '' !== $status ) {
			$all = array_filter(
				$all,
				static function ( array $entry ) use ( $status ): bool {
					if ( 'active' === $status ) {
						return ! empty( $entry['active'] );
					}
					return empty( $entry['active'] );
				}
			);
		}

		$all = array_values( $all );

		// Sorting.
		$allowed_orderby = array( 'keyword', 'url', 'group', 'status', 'link_count' );

		if ( in_array( $orderby, $allowed_orderby, true ) ) {
			$order_dir = ( 'desc' === $order ) ? -1 : 1;

			usort(
				$all,
				static function ( array $a, array $b ) use ( $orderby, $order_dir ): int {
					if ( 'link_count' === $orderby ) {
						$val_a = (int) ( $a['link_count'] ?? 0 );
						$val_b = (int) ( $b['link_count'] ?? 0 );
						return ( $val_a <=> $val_b ) * $order_dir;
					}

					if ( 'status' === $orderby ) {
						$val_a = empty( $a['active'] ) ? 0 : 1;
						$val_b = empty( $b['active'] ) ? 0 : 1;
						return ( $val_a <=> $val_b ) * $order_dir;
					}

					$val_a = mb_strtolower( $a[ $orderby ] ?? '' );
					$val_b = mb_strtolower( $b[ $orderby ] ?? '' );
					return strcmp( $val_a, $val_b ) * $order_dir;
				}
			);
		}

		$total      = count( $all );
		$total_pages = (int) ceil( $total / $per_page );
		$offset     = ( $page - 1 ) * $per_page;
		$items      = array_slice( $all, $offset, $per_page );

		wp_send_json_success(
			array(
				'items'       => $items,
				'total'       => $total,
				'total_pages' => $total_pages,
				'page'        => $page,
				'per_page'    => $per_page,
			)
		);
	}
}
