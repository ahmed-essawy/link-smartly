<?php
/**
 * CSV import and export for keyword mappings.
 *
 * @package LinkSmartly
 * @since   1.0.0
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles CSV import and export of keyword-to-URL mappings.
 *
 * @since 1.0.0
 */
class Lsm_Csv {

	/**
	 * Keywords manager instance.
	 *
	 * @since 1.0.0
	 * @var Lsm_Keywords
	 */
	private Lsm_Keywords $keywords;

	/**
	 * Nonce action for CSV operations.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const NONCE_ACTION = 'lsm_csv_action';

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
		add_action( 'admin_post_lsm_export_csv', array( $this, 'handle_export' ) );
		add_action( 'admin_post_lsm_import_csv', array( $this, 'handle_import' ) );
		add_action( 'admin_post_lsm_export_analytics', array( $this, 'handle_export_analytics' ) );
	}

	/**
	 * Handle CSV export.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function handle_export(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'link-smartly' ), 403 );
		}

		check_admin_referer( self::NONCE_ACTION, 'lsm_nonce' );

		$keywords = $this->keywords->get_all();
		$filename = 'link-smartly-keywords-' . gmdate( 'Y-m-d' ) . '.csv';

		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$output = fopen( 'php://output', 'w' );

		if ( false === $output ) {
			wp_die( esc_html__( 'Failed to create export file.', 'link-smartly' ) );
		}

		fputcsv( $output, array( 'keyword', 'url', 'active', 'group', 'synonyms', 'nofollow', 'new_tab', 'max_uses', 'start_date', 'end_date' ) );

		foreach ( $keywords as $entry ) {
			fputcsv(
				$output,
				array(
					$entry['keyword'],
					$entry['url'],
					$entry['active'] ? '1' : '0',
					$entry['group'] ?? '',
					$entry['synonyms'] ?? '',
					$entry['nofollow'] ?? 'default',
					$entry['new_tab'] ?? 'default',
					(string) ( $entry['max_uses'] ?? 0 ),
					$entry['start_date'] ?? '',
					$entry['end_date'] ?? '',
				)
			);
		}

		fclose( $output ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	/**
	 * Handle CSV import.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function handle_import(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'link-smartly' ), 403 );
		}

		check_admin_referer( self::NONCE_ACTION, 'lsm_nonce' );

		if ( ! isset( $_FILES['lsm_csv_file'] ) || empty( $_FILES['lsm_csv_file']['tmp_name'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$this->redirect_with_error( 'no_file' );
			return;
		}

		$file = array_map( 'sanitize_text_field', wp_unslash( $_FILES['lsm_csv_file'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( ! isset( $file['error'] ) || UPLOAD_ERR_OK !== absint( $file['error'] ) ) {
			$this->redirect_with_error( 'upload_failed' );
			return;
		}

		$tmp_name = sanitize_text_field( $file['tmp_name'] );

		if ( ! is_uploaded_file( $tmp_name ) ) {
			$this->redirect_with_error( 'upload_failed' );
			return;
		}

		$file_name = isset( $file['name'] ) ? sanitize_file_name( $file['name'] ) : '';
		$extension = strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) );

		if ( 'csv' !== $extension ) {
			$this->redirect_with_error( 'invalid_csv' );
			return;
		}

		$entries = $this->parse_csv( $tmp_name );

		if ( false === $entries ) {
			$this->redirect_with_error( 'invalid_csv' );
			return;
		}

		$mode = isset( $_POST['lsm_import_mode'] )
			? sanitize_key( wp_unslash( $_POST['lsm_import_mode'] ) )
			: 'append';

		if ( 'replace' === $mode ) {
			$this->keywords->save_all( $entries );
		} else {
			$existing = $this->keywords->get_all();
			$merged   = array_merge( $existing, $entries );
			$this->keywords->save_all( $merged );
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'     => Lsm_Admin::PAGE_SLUG,
					'tab'      => 'keywords',
					'imported' => (string) count( $entries ),
				),
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}

	/**
	 * Parse a CSV file into keyword entries.
	 *
	 * Expects columns: keyword, url, active (optional).
	 *
	 * @since 1.0.0
	 *
	 * @param string $file_path Path to the CSV file.
	 * @return array<int, array<string, mixed>>|false Parsed entries, or false on failure.
	 */
	private function parse_csv( string $file_path ): array|false {
		$handle = fopen( $file_path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen

		if ( false === $handle ) {
			return false;
		}

		$header = fgetcsv( $handle );

		if ( ! is_array( $header ) ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return false;
		}

		$header = array_map( 'strtolower', array_map( 'trim', $header ) );

		$keyword_col = array_search( 'keyword', $header, true );
		$url_col     = array_search( 'url', $header, true );
		$active_col  = array_search( 'active', $header, true );
		$group_col     = array_search( 'group', $header, true );
		$synonyms_col  = array_search( 'synonyms', $header, true );
		$nofollow_col  = array_search( 'nofollow', $header, true );
		$new_tab_col   = array_search( 'new_tab', $header, true );
		$max_uses_col  = array_search( 'max_uses', $header, true );
		$start_date_col = array_search( 'start_date', $header, true );
		$end_date_col   = array_search( 'end_date', $header, true );

		if ( false === $keyword_col || false === $url_col ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return false;
		}

		$entries = array();
		$max_rows = 1000;
		$count    = 0;

		while ( false !== ( $row = fgetcsv( $handle ) ) && $count < $max_rows ) {
			if ( ! is_array( $row ) || count( $row ) <= max( $keyword_col, $url_col ) ) {
				continue;
			}

			$keyword = trim( $row[ $keyword_col ] ?? '' );
			$url     = trim( $row[ $url_col ] ?? '' );

			if ( '' === $keyword || '' === $url ) {
				continue;
			}

			$active = true;

			if ( false !== $active_col && isset( $row[ $active_col ] ) ) {
				$active = '0' !== trim( $row[ $active_col ] );
			}

			$entries[] = array(
				'id'         => wp_generate_uuid4(),
				'keyword'    => sanitize_text_field( $keyword ),
				'url'        => esc_url_raw( $url ),
				'active'     => $active,
				'group'      => false !== $group_col && isset( $row[ $group_col ] ) ? sanitize_text_field( trim( $row[ $group_col ] ) ) : '',
				'synonyms'   => false !== $synonyms_col && isset( $row[ $synonyms_col ] ) ? sanitize_text_field( trim( $row[ $synonyms_col ] ) ) : '',
				'nofollow'   => false !== $nofollow_col && isset( $row[ $nofollow_col ] ) ? sanitize_key( trim( $row[ $nofollow_col ] ) ) : 'default',
				'new_tab'    => false !== $new_tab_col && isset( $row[ $new_tab_col ] ) ? sanitize_key( trim( $row[ $new_tab_col ] ) ) : 'default',
				'max_uses'   => false !== $max_uses_col && isset( $row[ $max_uses_col ] ) ? absint( trim( $row[ $max_uses_col ] ) ) : 0,
				'start_date' => false !== $start_date_col && isset( $row[ $start_date_col ] ) ? sanitize_text_field( trim( $row[ $start_date_col ] ) ) : '',
				'end_date'   => false !== $end_date_col && isset( $row[ $end_date_col ] ) ? sanitize_text_field( trim( $row[ $end_date_col ] ) ) : '',
			);

			++$count;
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		return $entries;
	}

	/**
	 * Redirect back to the import/export tab with an error code.
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
					'tab'   => 'import-export',
					'error' => $code,
				),
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}

	/**
	 * Handle analytics CSV export.
	 *
	 * Exports keyword statistics including link counts, posts linked, and health status.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	public function handle_export_analytics(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'link-smartly' ), 403 );
		}

		check_admin_referer( self::NONCE_ACTION, 'lsm_nonce' );

		$keywords     = $this->keywords->get_all();
		$linked_posts = $this->keywords->get_all_linked_posts();
		$health       = new Lsm_Health( $this->keywords );
		$health_data  = $health->get_results();
		$filename     = 'link-smartly-analytics-' . gmdate( 'Y-m-d' ) . '.csv';

		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$output = fopen( 'php://output', 'w' );

		if ( false === $output ) {
			wp_die( esc_html__( 'Failed to create export file.', 'link-smartly' ) );
		}

		fputcsv( $output, array( 'keyword', 'url', 'group', 'status', 'link_count', 'posts_linked', 'max_uses', 'health_status', 'health_code', 'last_checked' ) );

		foreach ( $keywords as $entry ) {
			$url          = $entry['url'] ?? '';
			$posts_linked = isset( $linked_posts[ $entry['id'] ] ) ? count( $linked_posts[ $entry['id'] ] ) : 0;
			$h_status     = isset( $health_data[ $url ] ) ? $health_data[ $url ]['status'] : 'unknown';
			$h_code       = isset( $health_data[ $url ] ) ? $health_data[ $url ]['code'] : 0;
			$h_checked    = isset( $health_data[ $url ] ) ? $health_data[ $url ]['checked_at'] : '';

			fputcsv(
				$output,
				array(
					$entry['keyword'],
					$url,
					$entry['group'] ?? '',
					! empty( $entry['active'] ) ? 'active' : 'inactive',
					(string) ( $entry['link_count'] ?? 0 ),
					(string) $posts_linked,
					(string) ( $entry['max_uses'] ?? 0 ),
					$h_status,
					(string) $h_code,
					$h_checked,
				)
			);
		}

		fclose( $output ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}
}
